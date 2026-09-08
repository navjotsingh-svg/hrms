<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\OneOnOneMeeting;
use App\Models\User;
use App\Services\GoogleMeetService;
use App\Services\OneOnOneMeetingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OneOnOneMeetingController extends Controller
{
    use ApiResponse;

    public function __construct(
        private OneOnOneMeetingService $meetingService,
        private GoogleMeetService $googleMeetService,
    ) {}

    public function meta(Request $request): JsonResponse
    {
        return $this->success([
            'can_schedule' => $this->meetingService->canSchedule($request->user()),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['scheduled', 'completed', 'cancelled'])],
            'search' => ['nullable', 'string', 'max:255'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'per_page' => ['nullable', 'integer', Rule::in([5, 10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $meetings = $this->meetingService->listForUser($request->user(), $validated);

        return $this->success([
            'meetings' => $meetings->getCollection()->map(fn (OneOnOneMeeting $meeting) => $this->formatMeeting($meeting, false, $request->user()))->values(),
            'pagination' => [
                'current_page' => $meetings->currentPage(),
                'last_page' => $meetings->lastPage(),
                'per_page' => $meetings->perPage(),
                'total' => $meetings->total(),
                'from' => $meetings->firstItem(),
                'to' => $meetings->lastItem(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $meeting = $this->meetingService->store($request->user(), $validated);

        return $this->success(
            ['meeting' => $this->formatMeeting($meeting, true, $request->user())],
            'One-on-one meeting scheduled.',
            201,
        );
    }

    public function show(Request $request, OneOnOneMeeting $one_on_one_meeting): JsonResponse
    {
        $meeting = $this->meetingService->resolveMeeting($request->user(), $one_on_one_meeting);

        return $this->success([
            'meeting' => $this->formatMeeting($meeting, true, $request->user()),
        ]);
    }

    public function update(Request $request, OneOnOneMeeting $one_on_one_meeting): JsonResponse
    {
        $validated = $this->validatePayload($request, true);

        $meeting = $this->meetingService->update($request->user(), $one_on_one_meeting, $validated);

        return $this->success(
            ['meeting' => $this->formatMeeting($meeting, true, $request->user())],
            'Meeting updated.',
        );
    }

    public function complete(Request $request, OneOnOneMeeting $one_on_one_meeting): JsonResponse
    {
        $validated = $request->validate([
            'meeting_notes' => ['nullable', 'string', 'max:10000'],
            'action_items' => ['nullable', 'array'],
            'action_items.*.text' => ['nullable', 'string', 'max:500'],
            'action_items.*.is_done' => ['nullable', 'boolean'],
        ]);

        $meeting = $this->meetingService->complete($request->user(), $one_on_one_meeting, $validated);

        return $this->success(
            ['meeting' => $this->formatMeeting($meeting, true, $request->user())],
            'Meeting marked as completed.',
        );
    }

    public function cancel(Request $request, OneOnOneMeeting $one_on_one_meeting): JsonResponse
    {
        $meeting = $this->meetingService->cancel($request->user(), $one_on_one_meeting);

        return $this->success(
            ['meeting' => $this->formatMeeting($meeting, true, $request->user())],
            'Meeting cancelled.',
        );
    }

    public function updateMeetLink(Request $request, OneOnOneMeeting $one_on_one_meeting): JsonResponse
    {
        $validated = $request->validate([
            'meeting_link' => ['required', 'string', 'max:500'],
        ]);

        $meeting = $this->meetingService->updateMeetLink(
            $request->user(),
            $one_on_one_meeting,
            $validated['meeting_link'],
        );

        return $this->success(
            ['meeting' => $this->formatMeeting($meeting, true, $request->user())],
            'Meeting link saved.',
        );
    }

    private function validatePayload(Request $request, bool $partial = false): array
    {
        $rules = [
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'employee_id' => [$partial ? 'sometimes' : 'required', 'integer', 'exists:employees,id'],
            'scheduled_at' => [$partial ? 'sometimes' : 'required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:240'],
            'agenda' => ['nullable', 'string', 'max:5000'],
            'meeting_notes' => ['nullable', 'string', 'max:10000'],
            'action_items' => ['nullable', 'array'],
            'action_items.*.text' => ['nullable', 'string', 'max:500'],
            'action_items.*.is_done' => ['nullable', 'boolean'],
            'meeting_link' => ['nullable', 'string', 'max:500'],
            'google_meet_link' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', Rule::in(['scheduled', 'completed', 'cancelled'])],
        ];

        return $request->validate($rules);
    }

    private function formatMeeting(OneOnOneMeeting $meeting, bool $detailed = false, ?User $viewer = null): array
    {
        $viewer ??= auth()->user();
        $meeting->loadMissing(['employee', 'organizer.employee', 'company']);
        $timezone = $meeting->company?->timezone ?: config('app.timezone');
        $scheduledLocal = $meeting->scheduled_at?->timezone($timezone);

        $payload = [
            'id' => $meeting->id,
            'title' => $meeting->title,
            'scheduled_at' => $meeting->scheduled_at?->toIso8601String(),
            'scheduled_at_label' => $scheduledLocal?->labelStack() ?: '—',
            'duration_minutes' => $meeting->duration_minutes,
            'status' => $meeting->status,
            'status_label' => ucfirst($meeting->status),
            'meeting_link' => $meeting->google_meet_link,
            'meeting_link_valid' => $this->googleMeetService->isValidMeetingLink($meeting->google_meet_link),
            'google_calendar_link' => $meeting->google_calendar_link,
            'employee' => $meeting->employee ? [
                'id' => $meeting->employee->id,
                'full_name' => $meeting->employee->full_name,
                'employee_code' => $meeting->employee->employee_code,
            ] : null,
            'organizer' => [
                'id' => $meeting->organizer?->id,
                'name' => $meeting->organizer?->employee?->full_name ?: $meeting->organizer?->name,
            ],
        ];

        if ($detailed) {
            $payload['agenda'] = $meeting->agenda;
            $payload['meeting_notes'] = $meeting->meeting_notes;
            $payload['action_items'] = $meeting->action_items ?? [];
            $payload['can_manage'] = $this->canManageMeeting($viewer, $meeting);
        }

        return $payload;
    }

    private function canManageMeeting($user, OneOnOneMeeting $meeting): bool
    {
        if ($meeting->status !== OneOnOneMeeting::STATUS_SCHEDULED) {
            return false;
        }

        if ((int) $meeting->organizer_user_id === (int) $user->id) {
            return true;
        }

        return $user->canManagePerformance();
    }
}
