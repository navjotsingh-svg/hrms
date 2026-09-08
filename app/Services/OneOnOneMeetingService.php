<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\OneOnOneMeeting;
use App\Models\User;
use App\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OneOnOneMeetingService
{
    public function __construct(
        private EmployeeAccessService $employeeAccessService,
        private GoogleMeetService $googleMeetService,
    ) {}

    public function listForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $this->assertParticipate($user);

        $query = OneOnOneMeeting::query()
            ->with(['employee', 'organizer.employee'])
            ->where('company_id', $user->company_id)
            ->orderByDesc('scheduled_at');

        $this->applyVisibilityScope($user, $query);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', (int) $filters['employee_id']);
        }

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhereHas('employee', function ($employeeQuery) use ($search) {
                        $employeeQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('employee_code', 'like', "%{$search}%");
                    });
            });
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function store(User $user, array $data): OneOnOneMeeting
    {
        $this->assertCanSchedule($user);

        $employee = Employee::query()
            ->where('company_id', $user->company_id)
            ->where('status', 'active')
            ->findOrFail((int) $data['employee_id']);

        $this->assertCanScheduleWithEmployee($user, $employee);

        $scheduledAt = Carbon::parse($data['scheduled_at'], $user->company?->timezone ?: config('app.timezone'));
        $durationMinutes = (int) ($data['duration_minutes'] ?? 30);
        $meetLink = $this->resolveMeetLinkFromInput($data);
        $calendarLink = $this->googleMeetService->calendarUrl(
            trim($data['title']),
            $scheduledAt,
            $durationMinutes,
            $data['agenda'] ?? null,
            $meetLink,
            array_filter([$user->email, $employee->email]),
        );
        return DB::transaction(function () use ($user, $employee, $data, $scheduledAt, $durationMinutes, $meetLink, $calendarLink) {
            $meeting = OneOnOneMeeting::query()->create([
                'company_id' => $user->company_id,
                'organizer_user_id' => $user->id,
                'employee_id' => $employee->id,
                'title' => trim($data['title']),
                'scheduled_at' => $scheduledAt,
                'duration_minutes' => $durationMinutes,
                'agenda' => $data['agenda'] ?? null,
                'google_meet_link' => $meetLink,
                'google_calendar_link' => $calendarLink,
                'status' => OneOnOneMeeting::STATUS_SCHEDULED,
                'created_by_user_id' => $user->id,
            ]);

            $this->notifyEmployee($meeting->load('company'), $employee);

            return $meeting->fresh(['employee', 'organizer.employee']);
        });
    }

    public function resolveMeeting(User $user, OneOnOneMeeting $meeting): OneOnOneMeeting
    {
        if ((int) $meeting->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Meeting not found.');
        }

        $this->assertCanViewMeeting($user, $meeting);

        return $meeting->load(['employee', 'organizer.employee']);
    }

    public function update(User $user, OneOnOneMeeting $meeting, array $data): OneOnOneMeeting
    {
        $meeting = $this->resolveMeeting($user, $meeting);
        $this->assertCanManageMeeting($user, $meeting);

        if ($meeting->status === OneOnOneMeeting::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'status' => ['Cancelled meetings cannot be edited.'],
            ]);
        }

        $scheduledAt = isset($data['scheduled_at'])
            ? Carbon::parse($data['scheduled_at'], $user->company?->timezone ?: config('app.timezone'))
            : $meeting->scheduled_at;
        $durationMinutes = (int) ($data['duration_minutes'] ?? $meeting->duration_minutes);
        $title = trim($data['title'] ?? $meeting->title);
        $agenda = array_key_exists('agenda', $data) ? $data['agenda'] : $meeting->agenda;
        $meetLink = $meeting->google_meet_link;

        if (array_key_exists('meeting_link', $data) || array_key_exists('google_meet_link', $data)) {
            $link = trim((string) ($data['meeting_link'] ?? $data['google_meet_link'] ?? ''));
            $meetLink = $link === '' ? null : $this->googleMeetService->normalizeMeetingLink($link);
        }
        $employee = $meeting->employee;
        $calendarLink = $this->googleMeetService->calendarUrl(
            $title,
            $scheduledAt,
            $durationMinutes,
            $agenda,
            $meetLink,
            array_filter([$user->email, $employee?->email]),
        );

        $meeting->update([
            'title' => $title,
            'scheduled_at' => $scheduledAt,
            'duration_minutes' => $durationMinutes,
            'agenda' => $agenda,
            'meeting_notes' => array_key_exists('meeting_notes', $data) ? $data['meeting_notes'] : $meeting->meeting_notes,
            'action_items' => array_key_exists('action_items', $data) ? $this->normalizeActionItems($data['action_items']) : $meeting->action_items,
            'google_meet_link' => $meetLink,
            'google_calendar_link' => $calendarLink,
            'status' => $data['status'] ?? $meeting->status,
        ]);

        return $meeting->fresh(['employee', 'organizer.employee']);
    }

    public function complete(User $user, OneOnOneMeeting $meeting, array $data = []): OneOnOneMeeting
    {
        $meeting = $this->resolveMeeting($user, $meeting);
        $this->assertCanManageMeeting($user, $meeting);

        $meeting->update([
            'status' => OneOnOneMeeting::STATUS_COMPLETED,
            'meeting_notes' => $data['meeting_notes'] ?? $meeting->meeting_notes,
            'action_items' => array_key_exists('action_items', $data)
                ? $this->normalizeActionItems($data['action_items'])
                : $meeting->action_items,
        ]);

        return $meeting->fresh(['employee', 'organizer.employee']);
    }

    public function updateMeetLink(User $user, OneOnOneMeeting $meeting, ?string $meetLink): OneOnOneMeeting
    {
        $meeting = $this->resolveMeeting($user, $meeting);
        $this->assertCanManageMeeting($user, $meeting);

        if ($meeting->status === OneOnOneMeeting::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'meeting_link' => ['Cancelled meetings cannot be updated.'],
            ]);
        }

        $normalized = $meetLink !== null && trim($meetLink) !== ''
            ? $this->googleMeetService->normalizeMeetLink($meetLink)
            : null;

        $employee = $meeting->employee;
        $calendarLink = $this->googleMeetService->calendarUrl(
            $meeting->title,
            $meeting->scheduled_at,
            $meeting->duration_minutes,
            $meeting->agenda,
            $normalized,
            array_filter([$user->email, $employee?->email]),
        );

        $meeting->update([
            'google_meet_link' => $normalized,
            'google_calendar_link' => $calendarLink,
        ]);

        return $meeting->fresh(['employee', 'organizer.employee']);
    }

    public function cancel(User $user, OneOnOneMeeting $meeting): OneOnOneMeeting
    {
        $meeting = $this->resolveMeeting($user, $meeting);
        $this->assertCanManageMeeting($user, $meeting);

        $meeting->update(['status' => OneOnOneMeeting::STATUS_CANCELLED]);

        return $meeting->fresh(['employee', 'organizer.employee']);
    }

    private function resolveMeetLinkFromInput(array $data): ?string
    {
        $link = trim((string) ($data['meeting_link'] ?? $data['google_meet_link'] ?? ''));

        if ($link === '') {
            return null;
        }

        return $this->googleMeetService->normalizeMeetingLink($link);
    }

    public function canSchedule(User $user): bool
    {
        if (! $user->hasPermission('performance.participate')) {
            return false;
        }

        return $user->canManagePerformance()
            || $user->canReviewPerformance()
            || $this->employeeAccessService->subordinateIdsForUser($user) !== [];
    }

    /** @return array<int, string> */
    public function normalizeActionItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->map(function ($item) {
                if (is_string($item)) {
                    return ['text' => trim($item), 'is_done' => false];
                }

                if (! is_array($item)) {
                    return null;
                }

                $text = trim((string) ($item['text'] ?? ''));

                if ($text === '') {
                    return null;
                }

                return [
                    'text' => $text,
                    'is_done' => (bool) ($item['is_done'] ?? false),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function applyVisibilityScope(User $user, $query): void
    {
        if ($user->canManagePerformance()) {
            return;
        }

        $employee = $this->employeeAccessService->linkedEmployee($user);
        $subordinateIds = $this->employeeAccessService->subordinateIdsForUser($user);

        $query->where(function ($builder) use ($user, $employee, $subordinateIds) {
            $builder->where('organizer_user_id', $user->id);

            if ($employee) {
                $builder->orWhere('employee_id', $employee->id);
            }

            if ($subordinateIds !== []) {
                $builder->orWhereIn('employee_id', $subordinateIds);
            }
        });
    }

    private function assertParticipate(User $user): void
    {
        if (! $user->hasPermission('performance.participate')) {
            throw new AccessDeniedHttpException('You are not allowed to access one-on-one meetings.');
        }
    }

    private function assertCanSchedule(User $user): void
    {
        $this->assertParticipate($user);

        if (! $this->canSchedule($user)) {
            throw new AccessDeniedHttpException('You are not allowed to schedule one-on-one meetings.');
        }
    }

    private function assertCanScheduleWithEmployee(User $user, Employee $employee): void
    {
        if ($user->canManagePerformance()) {
            return;
        }

        if ($this->employeeAccessService->managesEmployee($user, $employee)) {
            return;
        }

        throw new AccessDeniedHttpException('You can only schedule one-on-one meetings with your team members.');
    }

    private function assertCanViewMeeting(User $user, OneOnOneMeeting $meeting): void
    {
        if ($user->canManagePerformance()) {
            return;
        }

        if ((int) $meeting->organizer_user_id === (int) $user->id) {
            return;
        }

        $employee = $this->employeeAccessService->linkedEmployee($user);

        if ($employee && (int) $meeting->employee_id === (int) $employee->id) {
            return;
        }

        if ($this->employeeAccessService->managesEmployee($user, $meeting->employee)) {
            return;
        }

        throw new AccessDeniedHttpException('You are not allowed to view this meeting.');
    }

    private function assertCanManageMeeting(User $user, OneOnOneMeeting $meeting): void
    {
        if ((int) $meeting->organizer_user_id === (int) $user->id) {
            return;
        }

        if ($user->canManagePerformance()) {
            return;
        }

        throw new AccessDeniedHttpException('You are not allowed to manage this meeting.');
    }

    private function notifyEmployee(OneOnOneMeeting $meeting, Employee $employee): void
    {
        $employeeUser = $employee->user_id
            ? User::query()->find($employee->user_id)
            : User::query()->where('company_id', $employee->company_id)->where('email', $employee->email)->first();

        if (! $employeeUser) {
            return;
        }

        $organizerName = $meeting->organizer?->employee?->full_name ?: $meeting->organizer?->name ?: 'Your manager';
        $when = $meeting->scheduled_at?->timezone($meeting->company?->timezone ?: config('app.timezone'))->labelStack() ?: '—';

        UserNotification::query()->create([
            'company_id' => $meeting->company_id,
            'user_id' => $employeeUser->id,
            'type' => UserNotification::TYPE_ONE_ON_ONE_SCHEDULED,
            'title' => 'One-on-one meeting scheduled',
            'body' => "{$organizerName} scheduled a one-on-one with you on {$when}.",
            'action_url' => '/performance/one-on-one',
            'metadata' => [
                'meeting_id' => $meeting->id,
                'google_meet_link' => $meeting->google_meet_link,
            ],
        ]);
    }
}
