<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\CandidateInterview;
use App\Models\CandidateStageLog;
use App\Models\CareersPageSetting;
use App\Models\Company;
use App\Models\HiringOffer;
use App\Models\HiringTemplate;
use App\Models\JobPosting;
use App\Models\JobRequisition;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HiringService
{
    public function overviewForUser(User $user): array
    {
        $companyId = $user->company_id;

        $openJobs = JobPosting::query()->where('company_id', $companyId)->where('status', JobPosting::STATUS_OPEN)->count();
        $pendingRequisitions = JobRequisition::query()
            ->where('company_id', $companyId)
            ->where('status', JobRequisition::STATUS_PENDING)
            ->count();
        $activeCandidates = Candidate::query()
            ->where('company_id', $companyId)
            ->whereNotIn('stage', [Candidate::STAGE_HIRED, Candidate::STAGE_REJECTED])
            ->count();
        $upcomingInterviews = CandidateInterview::query()
            ->where('company_id', $companyId)
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>=', now())
            ->where('scheduled_at', '<=', now()->addDays(14))
            ->count();

        $pipeline = Candidate::query()
            ->where('company_id', $companyId)
            ->selectRaw('stage, COUNT(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage')
            ->all();

        return [
            'open_jobs' => $openJobs,
            'pending_requisitions' => $pendingRequisitions,
            'active_candidates' => $activeCandidates,
            'upcoming_interviews' => $upcomingInterviews,
            'pipeline' => $pipeline,
        ];
    }

    public function listRequisitions(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = JobRequisition::query()
            ->with(['department', 'requestedBy', 'approver', 'job'])
            ->where('company_id', $user->company_id)
            ->latest();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (($filters['scope'] ?? null) === 'mine') {
            $query->where('requested_by_user_id', $user->id);
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function storeRequisition(User $user, array $data): JobRequisition
    {
        $this->assertCanCreateRequisition($user);

        return JobRequisition::query()->create([
            'company_id' => $user->company_id,
            'department_id' => $data['department_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'headcount' => $data['headcount'] ?? 1,
            'employment_type' => $data['employment_type'] ?? null,
            'budget_min' => $data['budget_min'] ?? null,
            'budget_max' => $data['budget_max'] ?? null,
            'urgency' => $data['urgency'] ?? 'normal',
            'status' => JobRequisition::STATUS_DRAFT,
            'requested_by_user_id' => $user->id,
            'approver_user_id' => $data['approver_user_id'] ?? null,
        ]);
    }

    public function updateRequisition(User $user, JobRequisition $requisition, array $data): JobRequisition
    {
        $this->assertSameCompany($user, $requisition);
        $this->assertRequisitionEditable($requisition, $user);

        $requisition->fill([
            'department_id' => $data['department_id'] ?? $requisition->department_id,
            'title' => $data['title'] ?? $requisition->title,
            'description' => $data['description'] ?? $requisition->description,
            'headcount' => $data['headcount'] ?? $requisition->headcount,
            'employment_type' => $data['employment_type'] ?? $requisition->employment_type,
            'budget_min' => $data['budget_min'] ?? $requisition->budget_min,
            'budget_max' => $data['budget_max'] ?? $requisition->budget_max,
            'urgency' => $data['urgency'] ?? $requisition->urgency,
            'approver_user_id' => $data['approver_user_id'] ?? $requisition->approver_user_id,
        ])->save();

        return $requisition->fresh(['department', 'requestedBy', 'approver', 'job']);
    }

    public function submitRequisition(User $user, JobRequisition $requisition): JobRequisition
    {
        $this->assertSameCompany($user, $requisition);

        if ($requisition->status !== JobRequisition::STATUS_DRAFT) {
            throw ValidationException::withMessages(['status' => 'Only draft requisitions can be submitted.']);
        }

        if ((int) $requisition->requested_by_user_id !== (int) $user->id && ! $user->canManageHiring()) {
            throw ValidationException::withMessages(['requisition' => 'You cannot submit this requisition.']);
        }

        $requisition->update(['status' => JobRequisition::STATUS_PENDING]);

        return $requisition->fresh(['department', 'requestedBy', 'approver']);
    }

    public function approveRequisition(User $user, JobRequisition $requisition, ?string $notes = null): JobRequisition
    {
        $this->assertCanApproveRequisition($user, $requisition);

        if ($requisition->status !== JobRequisition::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => 'Only pending requisitions can be approved.']);
        }

        return DB::transaction(function () use ($user, $requisition, $notes) {
            $requisition->update([
                'status' => JobRequisition::STATUS_APPROVED,
                'approver_user_id' => $user->id,
                'approved_at' => now(),
                'rejection_reason' => null,
                'approval_notes' => $notes ? trim($notes) : null,
            ]);

            $slug = $this->generateUniqueJobSlug($requisition->company_id, $requisition->title);

            $job = JobPosting::query()->create([
                'company_id' => $requisition->company_id,
                'requisition_id' => $requisition->id,
                'department_id' => $requisition->department_id,
                'title' => $requisition->title,
                'slug' => $slug,
                'description_html' => $requisition->description ? nl2br(e($requisition->description)) : null,
                'employment_type' => $requisition->employment_type,
                'status' => JobPosting::STATUS_DRAFT,
                'created_by_user_id' => $user->id,
            ]);

            $requisition->update(['job_id' => $job->id]);

            return $requisition->fresh(['department', 'requestedBy', 'approver', 'job']);
        });
    }

    public function rejectRequisition(User $user, JobRequisition $requisition, string $reason): JobRequisition
    {
        $this->assertCanApproveRequisition($user, $requisition);

        if ($requisition->status !== JobRequisition::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => 'Only pending requisitions can be rejected.']);
        }

        $requisition->update([
            'status' => JobRequisition::STATUS_REJECTED,
            'approver_user_id' => $user->id,
            'approved_at' => now(),
            'rejection_reason' => trim($reason),
            'approval_notes' => null,
        ]);

        return $requisition->fresh(['department', 'requestedBy', 'approver']);
    }

    public function pendingRequisitionsForReviewer(User $user): Collection
    {
        if (! $user->canApproveRequisitions()) {
            return collect();
        }

        return JobRequisition::query()
            ->with(['department', 'requestedBy'])
            ->where('company_id', $user->company_id)
            ->where('status', JobRequisition::STATUS_PENDING)
            ->latest()
            ->get()
            ->filter(fn (JobRequisition $requisition) => $user->canReviewRequisition($requisition));
    }

    public function listJobs(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = JobPosting::query()
            ->with(['department', 'hiringManager', 'requisition'])
            ->where('company_id', $user->company_id)
            ->latest();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function storeJob(User $user, array $data): JobPosting
    {
        $this->assertCanManageHiring($user);

        $slug = $data['slug'] ?? $this->generateUniqueJobSlug($user->company_id, $data['title']);

        return JobPosting::query()->create([
            'company_id' => $user->company_id,
            'requisition_id' => $data['requisition_id'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'hiring_manager_employee_id' => $data['hiring_manager_employee_id'] ?? null,
            'title' => $data['title'],
            'slug' => $slug,
            'description_html' => $data['description_html'] ?? null,
            'location' => $data['location'] ?? null,
            'employment_type' => $data['employment_type'] ?? null,
            'experience_min' => $data['experience_min'] ?? null,
            'salary_min' => $data['salary_min'] ?? null,
            'salary_max' => $data['salary_max'] ?? null,
            'status' => JobPosting::STATUS_DRAFT,
            'created_by_user_id' => $user->id,
        ]);
    }

    public function updateJob(User $user, JobPosting $job, array $data): JobPosting
    {
        $this->assertSameCompany($user, $job);
        $this->assertCanManageHiring($user);

        if (isset($data['title']) && ! isset($data['slug'])) {
            $data['slug'] = $this->generateUniqueJobSlug($user->company_id, $data['title'], $job->id);
        }

        $job->fill([
            'department_id' => $data['department_id'] ?? $job->department_id,
            'hiring_manager_employee_id' => $data['hiring_manager_employee_id'] ?? $job->hiring_manager_employee_id,
            'title' => $data['title'] ?? $job->title,
            'slug' => $data['slug'] ?? $job->slug,
            'description_html' => $data['description_html'] ?? $job->description_html,
            'location' => $data['location'] ?? $job->location,
            'employment_type' => $data['employment_type'] ?? $job->employment_type,
            'experience_min' => $data['experience_min'] ?? $job->experience_min,
            'salary_min' => $data['salary_min'] ?? $job->salary_min,
            'salary_max' => $data['salary_max'] ?? $job->salary_max,
        ])->save();

        return $job->fresh(['department', 'hiringManager', 'requisition']);
    }

    public function publishJob(User $user, JobPosting $job): JobPosting
    {
        $this->assertSameCompany($user, $job);
        $this->assertCanManageHiring($user);

        $job->update([
            'status' => JobPosting::STATUS_OPEN,
            'published_at' => now(),
        ]);

        return $job->fresh(['department', 'hiringManager']);
    }

    public function closeJob(User $user, JobPosting $job): JobPosting
    {
        $this->assertSameCompany($user, $job);
        $this->assertCanManageHiring($user);

        $job->update(['status' => JobPosting::STATUS_CLOSED]);

        return $job->fresh(['department', 'hiringManager']);
    }

    public function listCandidates(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Candidate::query()
            ->with(['job', 'assignedRecruiter'])
            ->where('company_id', $user->company_id)
            ->latest('applied_at')
            ->latest();

        if (! empty($filters['stage'])) {
            $query->where('stage', $filters['stage']);
        }

        if (! empty($filters['job_id'])) {
            $query->where('job_id', $filters['job_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function storeCandidate(User $user, array $data): Candidate
    {
        $this->assertCanManageHiring($user);

        $candidate = Candidate::query()->create([
            'company_id' => $user->company_id,
            'job_id' => $data['job_id'] ?? null,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'resume_path' => $data['resume_path'] ?? null,
            'source' => $data['source'] ?? 'manual',
            'stage' => Candidate::STAGE_APPLIED,
            'assigned_recruiter_user_id' => $data['assigned_recruiter_user_id'] ?? $user->id,
            'notes' => $data['notes'] ?? null,
            'applied_at' => now(),
        ]);

        $this->logStageChange($candidate, null, Candidate::STAGE_APPLIED, $user);

        return $candidate->fresh(['job', 'assignedRecruiter']);
    }

    public function candidateDetail(User $user, Candidate $candidate): Candidate
    {
        $this->assertSameCompany($user, $candidate);
        $this->assertCanManageHiring($user);

        return $candidate->load([
            'job',
            'assignedRecruiter',
            'employee',
            'stageLogs.actor',
            'interviews' => fn ($query) => $query->orderByDesc('scheduled_at'),
            'offers' => fn ($query) => $query->orderByDesc('created_at'),
        ]);
    }

    public function updateCandidateStage(User $user, Candidate $candidate, string $stage, ?string $notes = null): Candidate
    {
        $this->assertSameCompany($user, $candidate);
        $this->assertCanManageHiring($user);

        $from = $candidate->stage;
        $candidate->stage = $stage;

        if ($stage === Candidate::STAGE_REJECTED) {
            $candidate->rejected_at = now();
            $candidate->rejection_reason = $notes;
        }

        if ($stage === Candidate::STAGE_HIRED) {
            $candidate->hired_at = now();
        }

        $candidate->save();
        $this->logStageChange($candidate, $from, $stage, $user, $notes);

        return $candidate->fresh(['job', 'assignedRecruiter']);
    }

    public function listInterviews(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = CandidateInterview::query()
            ->with(['candidate', 'job'])
            ->where('company_id', $user->company_id)
            ->orderBy('scheduled_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['candidate_id'])) {
            $query->where('candidate_id', $filters['candidate_id']);
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function storeInterview(User $user, array $data): CandidateInterview
    {
        $this->assertCanInterview($user);

        $candidate = Candidate::query()->findOrFail($data['candidate_id']);
        $this->assertSameCompany($user, $candidate);

        return CandidateInterview::query()->create([
            'company_id' => $user->company_id,
            'candidate_id' => $candidate->id,
            'job_id' => $data['job_id'] ?? $candidate->job_id,
            'title' => $data['title'],
            'scheduled_at' => $data['scheduled_at'],
            'duration_minutes' => $data['duration_minutes'] ?? 60,
            'location' => $data['location'] ?? null,
            'meeting_link' => $data['meeting_link'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? 'scheduled',
            'panel_user_ids' => $data['panel_user_ids'] ?? null,
            'created_by_user_id' => $user->id,
        ]);
    }

    public function updateInterview(User $user, CandidateInterview $interview, array $data): CandidateInterview
    {
        $this->assertSameCompany($user, $interview);
        $this->assertCanInterview($user);

        $interview->fill([
            'title' => $data['title'] ?? $interview->title,
            'scheduled_at' => $data['scheduled_at'] ?? $interview->scheduled_at,
            'duration_minutes' => $data['duration_minutes'] ?? $interview->duration_minutes,
            'location' => $data['location'] ?? $interview->location,
            'meeting_link' => $data['meeting_link'] ?? $interview->meeting_link,
            'notes' => $data['notes'] ?? $interview->notes,
            'status' => $data['status'] ?? $interview->status,
            'panel_user_ids' => $data['panel_user_ids'] ?? $interview->panel_user_ids,
        ])->save();

        return $interview->fresh(['candidate', 'job']);
    }

    public function listOffers(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = HiringOffer::query()
            ->with(['candidate', 'job', 'template'])
            ->where('company_id', $user->company_id)
            ->latest();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function storeOffer(User $user, array $data): HiringOffer
    {
        $this->assertCanManageHiring($user);

        $candidate = Candidate::query()->findOrFail($data['candidate_id']);
        $this->assertSameCompany($user, $candidate);

        return HiringOffer::query()->create([
            'company_id' => $user->company_id,
            'candidate_id' => $candidate->id,
            'job_id' => $data['job_id'] ?? $candidate->job_id,
            'template_id' => $data['template_id'] ?? null,
            'title' => $data['title'],
            'offered_ctc' => $data['offered_ctc'] ?? null,
            'joining_date' => $data['joining_date'] ?? null,
            'letter_html' => $data['letter_html'] ?? null,
            'status' => HiringOffer::STATUS_DRAFT,
            'created_by_user_id' => $user->id,
        ]);
    }

    public function sendOffer(User $user, HiringOffer $offer): HiringOffer
    {
        $this->assertSameCompany($user, $offer);
        $this->assertCanManageHiring($user);

        $offer->update([
            'status' => HiringOffer::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $this->updateCandidateStage($user, $offer->candidate, Candidate::STAGE_OFFER, 'Offer sent');

        return $offer->fresh(['candidate', 'job', 'template']);
    }

    public function listTemplates(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = HiringTemplate::query()
            ->where('company_id', $user->company_id)
            ->latest();

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function storeTemplate(User $user, array $data): HiringTemplate
    {
        $this->assertCanManageHiring($user);

        return HiringTemplate::query()->create([
            'company_id' => $user->company_id,
            'name' => $data['name'],
            'type' => $data['type'] ?? 'offer_letter',
            'description' => $data['description'] ?? null,
            'body_html' => $data['body_html'] ?? null,
            'is_default' => $data['is_default'] ?? false,
            'created_by_user_id' => $user->id,
        ]);
    }

    public function updateTemplate(User $user, HiringTemplate $template, array $data): HiringTemplate
    {
        $this->assertSameCompany($user, $template);
        $this->assertCanManageHiring($user);

        $template->fill([
            'name' => $data['name'] ?? $template->name,
            'type' => $data['type'] ?? $template->type,
            'description' => $data['description'] ?? $template->description,
            'body_html' => $data['body_html'] ?? $template->body_html,
            'is_default' => $data['is_default'] ?? $template->is_default,
        ])->save();

        return $template->fresh();
    }

    public function careersSettingsForUser(User $user): CareersPageSetting
    {
        return CareersPageSetting::query()->firstOrCreate(
            ['company_id' => $user->company_id],
            ['is_published' => false]
        );
    }

    public function updateCareersSettings(User $user, array $data, ?UploadedFile $banner = null): CareersPageSetting
    {
        $this->assertCanPublishCareers($user);

        $settings = $this->careersSettingsForUser($user);

        if ($banner) {
            $data['banner_path'] = $this->storePublicUpload(
                $banner,
                'uploads/careers/banners/'.$user->company_id
            );
        }

        $settings->fill([
            'hero_title' => $data['hero_title'] ?? $settings->hero_title,
            'hero_subtitle' => $data['hero_subtitle'] ?? $settings->hero_subtitle,
            'about_html' => $data['about_html'] ?? $settings->about_html,
            'header_html' => $data['header_html'] ?? $settings->header_html,
            'footer_html' => $data['footer_html'] ?? $settings->footer_html,
            'banner_path' => $data['banner_path'] ?? $settings->banner_path,
            'logo_path' => $data['logo_path'] ?? $settings->logo_path,
            'is_published' => array_key_exists('is_published', $data) ? (bool) $data['is_published'] : $settings->is_published,
            'embed_snippet' => $data['embed_snippet'] ?? $settings->embed_snippet,
            'meta_title' => $data['meta_title'] ?? $settings->meta_title,
            'meta_description' => $data['meta_description'] ?? $settings->meta_description,
        ])->save();

        return $settings->fresh();
    }

    public function publicCareersPage(Company $company): array
    {
        $settings = CareersPageSetting::query()->where('company_id', $company->id)->first();

        if (! $settings?->is_published) {
            throw ValidationException::withMessages(['careers' => 'This careers page is not published.']);
        }

        $jobs = JobPosting::query()
            ->with('department')
            ->where('company_id', $company->id)
            ->where('status', JobPosting::STATUS_OPEN)
            ->latest('published_at')
            ->get();

        return [
            'company' => $company,
            'settings' => $settings,
            'jobs' => $jobs,
        ];
    }

    public function applyFromCareers(Company $company, ?JobPosting $job, array $data, UploadedFile $resume): Candidate
    {
        if ($job && ((int) $job->company_id !== (int) $company->id || $job->status !== JobPosting::STATUS_OPEN)) {
            throw ValidationException::withMessages(['job' => 'This job is not accepting applications.']);
        }

        $settings = CareersPageSetting::query()->where('company_id', $company->id)->first();
        if (! $settings?->is_published) {
            throw ValidationException::withMessages(['careers' => 'Applications are closed.']);
        }

        $resumePath = $this->storePublicUpload($resume, 'uploads/careers/resumes/'.$company->id);

        $candidate = Candidate::query()->create([
            'company_id' => $company->id,
            'job_id' => $job?->id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'resume_path' => $resumePath,
            'source' => 'careers_page',
            'stage' => Candidate::STAGE_APPLIED,
            'applied_at' => now(),
        ]);

        CandidateStageLog::query()->create([
            'candidate_id' => $candidate->id,
            'from_stage' => null,
            'to_stage' => Candidate::STAGE_APPLIED,
            'notes' => 'Applied via careers page',
        ]);

        return $candidate;
    }

    public function candidateSummaryReport(User $user): array
    {
        $rows = Candidate::query()
            ->with(['job'])
            ->where('company_id', $user->company_id)
            ->latest('applied_at')
            ->limit(500)
            ->get()
            ->map(fn (Candidate $c) => [
                'name' => trim($c->first_name.' '.$c->last_name),
                'email' => $c->email,
                'phone' => $c->phone,
                'job' => $c->job?->title,
                'stage' => $c->stage,
                'source' => $c->source,
                'applied_at' => $c->applied_at?->format('Y-m-d'),
            ])
            ->all();

        return [
            'headers' => ['Name', 'Email', 'Phone', 'Job', 'Stage', 'Source', 'Applied At'],
            'rows' => array_map(fn ($r) => array_values($r), $rows),
        ];
    }

    private function logStageChange(Candidate $candidate, ?string $from, string $to, User $user, ?string $notes = null): void
    {
        CandidateStageLog::query()->create([
            'candidate_id' => $candidate->id,
            'from_stage' => $from,
            'to_stage' => $to,
            'actor_user_id' => $user->id,
            'notes' => $notes,
        ]);
    }

    private function generateUniqueJobSlug(int $companyId, string $title, ?int $ignoreId = null): string
    {
        $slug = Str::slug($title);
        $original = $slug;
        $count = 1;

        while (JobPosting::query()
            ->where('company_id', $companyId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $original.'-'.$count;
            $count++;
        }

        return $slug;
    }

    private function storePublicUpload(UploadedFile $file, string $directory): string
    {
        $directory = trim(str_replace('\\', '/', $directory), '/');
        $absoluteDir = public_path($directory);

        if (! is_dir($absoluteDir) && ! mkdir($absoluteDir, 0755, true) && ! is_dir($absoluteDir)) {
            throw ValidationException::withMessages(['file' => 'Unable to prepare upload directory.']);
        }

        $extension = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin';
        $filename = Str::uuid().'.'.strtolower($extension);
        $file->move($absoluteDir, $filename);

        return $directory.'/'.$filename;
    }

    private function assertSameCompany(User $user, $model): void
    {
        if ((int) $model->company_id !== (int) $user->company_id) {
            abort(404);
        }
    }

    private function assertCanManageHiring(User $user): void
    {
        if (! $user->canManageHiring()) {
            abort(403);
        }
    }

    private function assertCanCreateRequisition(User $user): void
    {
        if (! $user->canCreateRequisition()) {
            abort(403);
        }
    }

    private function assertCanInterview(User $user): void
    {
        if (! $user->canInterviewCandidates()) {
            abort(403);
        }
    }

    private function assertCanPublishCareers(User $user): void
    {
        if (! $user->canPublishCareers()) {
            abort(403);
        }
    }

    private function assertCanApproveRequisition(User $user, JobRequisition $requisition): void
    {
        $this->assertSameCompany($user, $requisition);

        if (! $user->canReviewRequisition($requisition)) {
            abort(403);
        }
    }

    private function assertRequisitionEditable(JobRequisition $requisition, User $user): void
    {
        if (! in_array($requisition->status, [JobRequisition::STATUS_DRAFT, JobRequisition::STATUS_REJECTED], true)) {
            throw ValidationException::withMessages(['status' => 'This requisition cannot be edited.']);
        }

        if ((int) $requisition->requested_by_user_id !== (int) $user->id && ! $user->canManageHiring()) {
            abort(403);
        }
    }
}
