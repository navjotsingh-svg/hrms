<?php

namespace App\Services;

use App\Models\CompanyMoment;
use App\Models\CompanyMomentAttachment;
use App\Models\CompanyMomentComment;
use App\Models\CompanyMomentReaction;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MomentService
{
    public function __construct(private MomentAttachmentService $attachmentService) {}

    public function feedForUser(User $user, array $filters = [], int $page = 1, int $perPage = 15): array
    {
        if (! $user->company_id) {
            return $this->emptyFeed($page, $perPage);
        }

        $this->syncTodaySystemMoments((int) $user->company_id);

        $query = CompanyMoment::query()
            ->with([
                'author.employee',
                'reactions.user.employee',
                'attachments',
                'comments' => fn ($query) => $query->latest()->limit(5)->with('user.employee'),
            ])
            ->withCount('comments')
            ->where('company_id', $user->company_id)
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $paginator = $query->paginate(max(1, min(50, $perPage)), ['*'], 'page', max(1, $page));

        return [
            'moments' => collect($paginator->items())
                ->map(fn (CompanyMoment $moment) => $this->transformMoment($moment, $user))
                ->values()
                ->all(),
            'pagination' => $this->paginationMeta($paginator),
        ];
    }

    public function syncTodaySystemMoments(int $companyId): void
    {
        $today = $this->today();
        $todayString = $today->toDateString();

        $employees = Employee::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->get(['id', 'first_name', 'last_name', 'employee_code', 'date_of_birth', 'joining_date']);

        foreach ($employees as $employee) {
            if ($employee->date_of_birth && $this->matchesMonthDay($employee->date_of_birth, $today)) {
                $this->upsertSystemMoment(
                    $companyId,
                    CompanyMoment::TYPE_BIRTHDAY,
                    $todayString,
                    $employee,
                    sprintf('%s is celebrating a birthday today.', $employee->full_name),
                );
            }

            if ($employee->joining_date) {
                if ($employee->joining_date->isSameDay($today)) {
                    $this->upsertSystemMoment(
                        $companyId,
                        CompanyMoment::TYPE_NEW_JOINEE,
                        $todayString,
                        $employee,
                        sprintf('Welcome aboard, %s!', $employee->full_name),
                    );
                } elseif ($employee->joining_date->lt($today) && $this->matchesMonthDay($employee->joining_date, $today)) {
                    $years = $this->workAnniversaryYears($employee, $today);

                    if ($years >= 1) {
                        $this->upsertSystemMoment(
                            $companyId,
                            CompanyMoment::TYPE_WORK_ANNIVERSARY,
                            $todayString,
                            $employee,
                            sprintf('%s is celebrating %d year(s) with us today.', $employee->full_name, $years),
                            ['years' => $years],
                        );
                    }
                }
            }
        }
    }

    /** @param  array<int, \Illuminate\Http\UploadedFile>  $attachments */
    public function createPost(User $user, string $content, array $attachments = []): array
    {
        if (! $user->hasPermission('home.moments.post')) {
            throw new AccessDeniedHttpException('You are not allowed to post moments.');
        }

        if (! $user->company_id) {
            throw ValidationException::withMessages([
                'content' => ['You must belong to a company to post moments.'],
            ]);
        }

        $content = trim($content);
        $hasAttachments = collect($attachments)->contains(fn ($file) => $file instanceof \Illuminate\Http\UploadedFile);

        if ($content === '' && ! $hasAttachments) {
            throw ValidationException::withMessages([
                'content' => ['Write a message or attach at least one file.'],
            ]);
        }

        $moment = CompanyMoment::query()->create([
            'company_id' => $user->company_id,
            'type' => CompanyMoment::TYPE_POST,
            'author_user_id' => $user->id,
            'content' => $content !== '' ? $content : null,
            'published_at' => now(),
        ]);

        if ($hasAttachments) {
            $this->attachmentService->storeMany($moment, $attachments);
        }

        $moment->load(['author.employee', 'reactions.user.employee', 'attachments', 'comments.user.employee']);
        $moment->loadCount('comments');

        return $this->transformMoment($moment, $user);
    }

    public function addComment(User $user, int $momentId, string $content): array
    {
        if (! $user->hasPermission('home.moments.comment') && ! $user->hasPermission('home.moments.view')) {
            throw new AccessDeniedHttpException('You are not allowed to comment on moments.');
        }

        if (! $user->company_id) {
            throw ValidationException::withMessages([
                'content' => ['You must belong to a company to comment on moments.'],
            ]);
        }

        $content = trim($content);

        if ($content === '') {
            throw ValidationException::withMessages([
                'content' => ['Comment cannot be empty.'],
            ]);
        }

        $moment = CompanyMoment::query()
            ->where('company_id', $user->company_id)
            ->find($momentId);

        if (! $moment) {
            throw new NotFoundHttpException('Moment not found.');
        }

        $comment = CompanyMomentComment::query()->create([
            'company_moment_id' => $moment->id,
            'user_id' => $user->id,
            'content' => $content,
        ]);

        $comment->load('user.employee');

        return $this->transformComment($comment);
    }

    public function commentsForMoment(User $user, int $momentId, int $page = 1, int $perPage = 20): array
    {
        if (! $user->hasPermission('home.moments.view')) {
            throw new AccessDeniedHttpException('You are not allowed to view moments.');
        }

        $moment = CompanyMoment::query()
            ->where('company_id', $user->company_id)
            ->find($momentId);

        if (! $moment) {
            throw new NotFoundHttpException('Moment not found.');
        }

        $paginator = CompanyMomentComment::query()
            ->with('user.employee')
            ->where('company_moment_id', $moment->id)
            ->latest()
            ->paginate(max(1, min(50, $perPage)), ['*'], 'page', max(1, $page));

        return [
            'comments' => collect($paginator->items())
                ->map(fn (CompanyMomentComment $comment) => $this->transformComment($comment))
                ->values()
                ->all(),
            'pagination' => $this->paginationMeta($paginator),
        ];
    }

    public function toggleReaction(User $user, int $momentId, ?string $reaction): array
    {
        if (! $user->hasPermission('home.moments.view')) {
            throw new AccessDeniedHttpException('You are not allowed to react to moments.');
        }

        $moment = CompanyMoment::query()
            ->with(['author.employee', 'reactions.user.employee'])
            ->where('company_id', $user->company_id)
            ->find($momentId);

        if (! $moment) {
            throw new NotFoundHttpException('Moment not found.');
        }

        $existing = CompanyMomentReaction::query()
            ->where('company_moment_id', $moment->id)
            ->where('user_id', $user->id)
            ->first();

        if ($reaction === null) {
            $existing?->delete();
        } elseif ($existing) {
            if ($existing->reaction === $reaction) {
                $existing->delete();
            } else {
                $existing->update(['reaction' => $reaction]);
            }
        } else {
            CompanyMomentReaction::query()->create([
                'company_moment_id' => $moment->id,
                'user_id' => $user->id,
                'reaction' => $reaction,
            ]);
        }

        $moment = CompanyMoment::query()
            ->with([
                'author.employee',
                'reactions.user.employee',
                'attachments',
                'comments' => fn ($query) => $query->latest()->limit(5)->with('user.employee'),
            ])
            ->withCount('comments')
            ->where('company_id', $user->company_id)
            ->find($momentId);

        return $this->transformMoment($moment, $user);
    }

    private function upsertSystemMoment(
        int $companyId,
        string $type,
        string $occasionDate,
        Employee $employee,
        string $content,
        array $extraMetadata = [],
    ): void {
        $metadata = array_merge([
            'employee_id' => $employee->id,
            'employee_name' => $employee->full_name,
            'employee_code' => $employee->employee_code,
        ], $extraMetadata);

        CompanyMoment::query()->firstOrCreate(
            [
                'company_id' => $companyId,
                'type' => $type,
                'occasion_date' => $occasionDate,
                'metadata->employee_id' => $employee->id,
            ],
            [
                'content' => $content,
                'metadata' => $metadata,
                'published_at' => now(),
            ]
        );
    }

    private function transformMoment(CompanyMoment $moment, User $viewer): array
    {
        $reactions = $moment->reactions;
        $counts = [];

        foreach (CompanyMomentReaction::REACTIONS as $reaction) {
            $counts[$reaction] = $reactions->where('reaction', $reaction)->count();
        }

        $viewerReaction = $reactions->firstWhere('user_id', $viewer->id);

        return [
            'id' => $moment->id,
            'type' => $moment->type,
            'content' => $moment->content,
            'metadata' => $moment->metadata ?? [],
            'occasion_date' => $moment->occasion_date?->format('Y-m-d'),
            'published_at' => $moment->published_at?->toIso8601String(),
            'author' => $this->transformAuthor($moment),
            'reactions' => [
                'counts' => $counts,
                'total' => $reactions->count(),
                'viewer_reaction' => $viewerReaction?->reaction,
            ],
            'comments' => [
                'count' => (int) ($moment->comments_count ?? $moment->comments->count()),
                'items' => $moment->comments
                    ->map(fn (CompanyMomentComment $comment) => $this->transformComment($comment))
                    ->values()
                    ->all(),
            ],
            'attachments' => $moment->attachments
                ->map(fn (CompanyMomentAttachment $attachment) => $this->transformAttachment($attachment))
                ->values()
                ->all(),
        ];
    }

    private function transformAttachment(CompanyMomentAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'url' => $attachment->fileUrl(),
            'mime_type' => $attachment->mime_type,
            'file_size' => $attachment->file_size,
            'is_image' => $attachment->isImage(),
            'is_pdf' => $attachment->isPdf(),
        ];
    }

    private function transformComment(CompanyMomentComment $comment): array
    {
        $author = $comment->user;

        return [
            'id' => $comment->id,
            'content' => $comment->content,
            'created_at' => $comment->created_at?->toIso8601String(),
            'author' => [
                'id' => $author?->id,
                'name' => $author?->employee?->full_name ?: $author?->name,
            ],
        ];
    }

    private function transformAuthor(CompanyMoment $moment): ?array
    {
        if ($moment->isSystemMoment()) {
            $metadata = $moment->metadata ?? [];

            return [
                'type' => 'system',
                'name' => $metadata['employee_name'] ?? 'Team Member',
                'employee_id' => $metadata['employee_id'] ?? null,
                'employee_code' => $metadata['employee_code'] ?? null,
            ];
        }

        $author = $moment->author;

        if (! $author) {
            return null;
        }

        return [
            'type' => 'user',
            'id' => $author->id,
            'name' => $author->employee?->full_name ?: $author->name,
            'employee_id' => $author->employee?->id,
            'employee_code' => $author->employee?->employee_code,
        ];
    }

    private function today(): Carbon
    {
        return Carbon::today(config('app.timezone'));
    }

    private function matchesMonthDay(Carbon $date, Carbon $today): bool
    {
        return (int) $date->format('m') === (int) $today->format('m')
            && (int) $date->format('d') === (int) $today->format('d');
    }

    private function workAnniversaryYears(Employee $employee, Carbon $occasion): int
    {
        $joiningDate = Carbon::parse(
            $employee->joining_date->toDateString(),
            config('app.timezone')
        )->startOfDay();

        return max(0, (int) $joiningDate->diffInYears($occasion));
    }

    /** @return array<string, int|null> */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    private function emptyFeed(int $page, int $perPage): array
    {
        return [
            'moments' => [],
            'pagination' => [
                'current_page' => max(1, $page),
                'last_page' => 1,
                'per_page' => max(1, min(50, $perPage)),
                'total' => 0,
                'from' => null,
                'to' => null,
            ],
        ];
    }
}
