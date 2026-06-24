<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\StoreTimesheetCommentRequest;
use App\Http\Requests\StoreTimesheetDayRequest;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\TimesheetCommentResource;
use App\Http\Resources\TimesheetEntryResource;
use App\Services\TimesheetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimesheetController extends Controller
{
    use ApiResponse;

    public function __construct(private TimesheetService $timesheetService) {}

    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->canAccessTimesheets()) {
            abort(403);
        }

        $validated = $request->validate([
            'work_date' => ['required', 'date'],
            'employee_id' => ['nullable', 'integer'],
        ]);

        $employeeId = isset($validated['employee_id']) ? (int) $validated['employee_id'] : null;
        $entries = $this->timesheetService->entriesForDate(
            $request->user(),
            $validated['work_date'],
            $employeeId,
        );

        $totalHours = round($entries->sum('hours'), 2);
        $targetEmployeeId = $employeeId ?? (int) $request->user()->employee?->id;

        return $this->success([
            'entries' => TimesheetEntryResource::collection($entries),
            'summary' => [
                'work_date' => $validated['work_date'],
                'employee_id' => $targetEmployeeId,
                'entry_count' => $entries->count(),
                'total_hours' => $totalHours,
            ],
            'capabilities' => $this->capabilities($request, $targetEmployeeId),
        ]);
    }

    public function recent(Request $request): JsonResponse
    {
        if (! $request->user()->canAccessTimesheets()) {
            abort(403);
        }

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:90'],
            'employee_id' => ['nullable', 'integer'],
        ]);

        $limit = min((int) ($validated['limit'] ?? 30), 90);
        $employeeId = isset($validated['employee_id']) ? (int) $validated['employee_id'] : null;
        $grouped = $this->timesheetService->recentDays($request->user(), $limit, $employeeId);

        $days = $grouped->map(function ($entries, $date) {
            return [
                'work_date' => $date,
                'total_hours' => round($entries->sum('hours'), 2),
                'entries' => TimesheetEntryResource::collection($entries),
            ];
        })->values();

        return $this->success(['days' => $days]);
    }

    public function teamEmployees(Request $request): JsonResponse
    {
        if (! $request->user()->canReviewTeamTimesheets()) {
            abort(403);
        }

        return $this->success(
            $this->timesheetService->teamEmployeesPayload($request->user()),
        );
    }

    public function comments(Request $request): JsonResponse
    {
        if (! $request->user()->canAccessTimesheets()) {
            abort(403);
        }

        $validated = $request->validate([
            'work_date' => ['required', 'date'],
            'employee_id' => ['required', 'integer'],
        ]);

        $employeeId = (int) $validated['employee_id'];

        return $this->success([
            'by_project' => $this->timesheetService->commentsGroupedByProject(
                $request->user(),
                $employeeId,
                $validated['work_date'],
            ),
            'capabilities' => $this->capabilities($request, $employeeId),
        ]);
    }

    public function storeComment(StoreTimesheetCommentRequest $request): JsonResponse
    {
        if (! $request->user()->canAccessTimesheets()) {
            abort(403);
        }

        $validated = $request->validated();
        $parentId = isset($validated['parent_id']) ? (int) $validated['parent_id'] : null;

        $comment = $this->timesheetService->addComment(
            $request->user(),
            (int) $validated['employee_id'],
            $validated['work_date'],
            $validated['body'],
            $parentId,
            isset($validated['project_id']) ? (int) $validated['project_id'] : null,
        );

        return $this->success(
            ['comment' => new TimesheetCommentResource($comment)],
            $parentId ? 'Reply posted.' : 'Comment added.',
            201,
        );
    }

    public function projectOptions(Request $request): JsonResponse
    {
        if (! $request->user()->canSubmitTimesheets()) {
            abort(403);
        }

        $projects = $this->timesheetService->projectOptions($request->user());

        return $this->success([
            'projects' => ProjectResource::collection($projects),
        ]);
    }

    public function store(StoreTimesheetDayRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $entries = $this->timesheetService->submitDay(
            $request->user(),
            $validated['work_date'],
            $validated['entries'],
        );

        return $this->success(
            [
                'entries' => TimesheetEntryResource::collection($entries),
                'summary' => [
                    'work_date' => $validated['work_date'],
                    'entry_count' => $entries->count(),
                    'total_hours' => round($entries->sum('hours'), 2),
                ],
            ],
            'Timesheet submitted successfully.',
            201,
        );
    }

    private function capabilities(Request $request, ?int $employeeId): array
    {
        $user = $request->user();
        $employeeId = $employeeId ?? (int) $user->employee?->id;

        return [
            'can_submit' => $user->canSubmitTimesheets()
                && $employeeId
                && (int) $user->employee?->id === $employeeId,
            'can_comment' => $employeeId
                && (int) $user->employee?->id !== $employeeId
                && $this->timesheetService->canReviewEmployeeTimesheet($user, $employeeId),
            'can_reply' => $employeeId
                && $this->timesheetService->canReplyToTimesheet($user, $employeeId),
            'is_viewing_team_member' => $employeeId
                && (int) $user->employee?->id !== $employeeId,
        ];
    }
}
