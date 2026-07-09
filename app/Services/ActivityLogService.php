<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class ActivityLogService
{
    private const FILE_BASE_DIR = 'activity-logs';

    /**
     * @param  array{
     *     user?: User|null,
     *     company_id?: int|null,
     *     employee_id?: int|null,
     *     module?: string,
     *     action?: string,
     *     status?: string,
     *     subject?: Model|null,
     *     request_type?: string|null,
     *     message?: string|null,
     *     failure_reason?: string|null,
     *     action_note?: string|null,
     *     old_values?: array<string, mixed>|null,
     *     new_values?: array<string, mixed>|null,
     *     metadata?: array<string, mixed>,
     *     request?: Request|null,
     *     logged_at?: Carbon|null,
     * }  $payload
     */
    public function write(array $payload): ?ActivityLog
    {
        $loggedAt = ($payload['logged_at'] ?? now())->copy();
        $user = $payload['user'] ?? null;
        $companyId = $payload['company_id'] ?? $user?->company_id;
        $subject = $payload['subject'] ?? null;

        $entry = ActivityLog::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $companyId,
            'user_id' => $user?->id,
            'employee_id' => $payload['employee_id'] ?? null,
            'user_name' => $user?->name,
            'user_email' => $user?->email,
            'role_slug' => $user?->role?->slug,
            'module' => $payload['module'] ?? 'system',
            'action' => $payload['action'] ?? 'activity',
            'status' => $payload['status'] ?? ActivityLog::STATUS_SUCCESS,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'request_type' => $payload['request_type'] ?? null,
            'message' => $payload['message'] ?? null,
            'failure_reason' => $payload['failure_reason'] ?? null,
            'action_note' => $payload['action_note'] ?? null,
            'old_values' => $this->sanitizeValues($payload['old_values'] ?? null),
            'new_values' => $this->sanitizeValues($payload['new_values'] ?? null),
            'metadata' => $this->sanitizeValues($payload['metadata'] ?? []),
            'ip_address' => $payload['request']?->ip(),
            'user_agent' => Str::limit((string) $payload['request']?->userAgent(), 500, ''),
            'logged_at' => $loggedAt,
        ]);

        $this->appendDailyArchive($entry);

        return $entry;
    }

    public function logChange(
        User $user,
        string $module,
        string $action,
        Model $subject,
        ?int $employeeId,
        string $message,
        array $oldValues = [],
        array $newValues = [],
        ?Request $request = null,
        ?string $actionNote = null,
        array $metadata = [],
    ): ?ActivityLog {
        return $this->write([
            'user' => $user,
            'employee_id' => $employeeId,
            'module' => $module,
            'action' => $action,
            'subject' => $subject,
            'message' => $message,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'action_note' => $actionNote,
            'metadata' => $metadata,
            'request' => $request,
        ]);
    }

    public function logWorkflowRequest(
        User $user,
        string $requestType,
        Model $subject,
        int $employeeId,
        string $action,
        string $message,
        ?string $actionNote = null,
        ?Request $request = null,
        array $metadata = [],
    ): ?ActivityLog {
        return $this->write([
            'user' => $user,
            'employee_id' => $employeeId,
            'module' => 'requests',
            'action' => $action,
            'request_type' => $requestType,
            'subject' => $subject,
            'message' => $message,
            'action_note' => $actionNote,
            'metadata' => $metadata,
            'request' => $request,
        ]);
    }

    public function logAuthAttempt(?User $user, Request $request, bool $success, ?string $failureReason = null, ?string $email = null): ?ActivityLog
    {
        return $this->write([
            'user' => $user,
            'company_id' => $user?->company_id,
            'module' => 'auth',
            'action' => 'login',
            'status' => $success ? ActivityLog::STATUS_SUCCESS : ActivityLog::STATUS_FAILURE,
            'message' => $success ? 'User logged in successfully.' : 'Login attempt failed.',
            'failure_reason' => $failureReason,
            'metadata' => ['email' => $email ?? $user?->email],
            'request' => $request,
        ]);
    }

    public function logLogout(User $user, Request $request): ?ActivityLog
    {
        return $this->write([
            'user' => $user,
            'module' => 'auth',
            'action' => 'logout',
            'message' => 'User logged out successfully.',
            'request' => $request,
        ]);
    }

    public function logAttendancePunch(User $user, Request $request, bool $success, ?array $result = null, ?Throwable $error = null): ?ActivityLog
    {
        $punchType = $result['punch']['punch_type'] ?? null;
        $action = $punchType ? "punch.{$punchType}" : 'punch';

        return $this->write([
            'user' => $user,
            'employee_id' => $user->employee?->id,
            'module' => 'attendance',
            'action' => $action,
            'status' => $success ? ActivityLog::STATUS_SUCCESS : ActivityLog::STATUS_FAILURE,
            'message' => $success
                ? (($punchType === 'in' ? 'Punch in' : 'Punch out').' recorded successfully.')
                : 'Attendance punch failed.',
            'failure_reason' => $error ? $this->throwableMessage($error) : null,
            'metadata' => array_filter([
                'employee_code' => $user->employee?->employee_code,
                'punch_type' => $punchType,
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
                'location_name' => $request->input('location_name'),
                'punch_id' => $result['punch']['id'] ?? null,
                'next_punch_type' => $result['next_punch_type'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''),
            'request' => $request,
        ]);
    }

    /** @return array{entries: array<int, array<string, mixed>>, total: int, page: int, per_page: int, last_page: int} */
    public function listForViewer(User $viewer, array $filters = []): array
    {
        $query = $this->viewerQuery($viewer, $filters);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(200, max(10, (int) ($filters['per_page'] ?? 50)));

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'entries' => collect($paginator->items())->map(fn (ActivityLog $log) => $this->serialize($log))->all(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => max(1, $paginator->lastPage()),
        ];
    }

    /**
     * @return array{preset: string, from_date: string, to_date: string, from: Carbon, to: Carbon}
     */
    public function resolveViewerDateRange(array $filters = []): array
    {
        if (! empty($filters['date']) && empty($filters['range']) && empty($filters['from_date'])) {
            $date = Carbon::parse($filters['date']);

            return [
                'preset' => DateRangePresetService::PRESET_CUSTOM,
                'from_date' => $date->toDateString(),
                'to_date' => $date->toDateString(),
                'from' => $date->copy()->startOfDay(),
                'to' => $date->copy()->endOfDay(),
            ];
        }

        return app(DateRangePresetService::class)->resolve([
            'range' => $filters['range'] ?? DateRangePresetService::PRESET_TODAY,
            'from_date' => $filters['from_date'] ?? null,
            'to_date' => $filters['to_date'] ?? null,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function timelineForEmployee(User $viewer, int $employeeId, array $filters = []): array
    {
        $employee = \App\Models\Employee::query()->findOrFail($employeeId);

        if ((int) $employee->company_id !== (int) $viewer->company_id && ! $viewer->isSuperAdmin()) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('You cannot view this employee timeline.');
        }

        if (! $viewer->canViewEmployees() && (int) $viewer->employee?->id !== $employeeId) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('You cannot view this employee timeline.');
        }

        $query = ActivityLog::query()
            ->where('company_id', $employee->company_id)
            ->where(function ($builder) use ($employeeId) {
                $builder
                    ->where('employee_id', $employeeId)
                    ->orWhere(function ($nested) use ($employeeId) {
                        $nested
                            ->where('subject_type', \App\Models\Employee::class)
                            ->where('subject_id', $employeeId);
                    });
            })
            ->orderByDesc('logged_at');

        if ($module = $filters['module'] ?? null) {
            $query->where('module', $module);
        }

        if ($from = $filters['from_date'] ?? null) {
            $query->whereDate('logged_at', '>=', $from);
        }

        if ($to = $filters['to_date'] ?? null) {
            $query->whereDate('logged_at', '<=', $to);
        }

        $limit = min(200, max(10, (int) ($filters['limit'] ?? 100)));

        return $query->limit($limit)->get()->map(fn (ActivityLog $log) => $this->serialize($log))->all();
    }

    /** @return array<int, string> */
    public function availableDates(User $viewer, ?int $month = null, ?int $year = null, ?int $companyId = null): array
    {
        $companyId = $this->resolveViewerCompanyId($viewer, $companyId);
        $year = $year ?? (int) now()->format('Y');
        $month = $month ?? (int) now()->format('n');

        return ActivityLog::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->whereYear('logged_at', $year)
            ->whereMonth('logged_at', $month)
            ->selectRaw('DATE(logged_at) as log_date')
            ->distinct()
            ->orderByDesc('log_date')
            ->pluck('log_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();
    }

    private function viewerQuery(User $viewer, array $filters): \Illuminate\Database\Eloquent\Builder
    {
        $companyId = $this->resolveViewerCompanyId($viewer, $filters['company_id'] ?? null);
        $range = $this->resolveViewerDateRange($filters);

        $query = ActivityLog::query()
            ->when($companyId, fn ($builder) => $builder->where('company_id', $companyId))
            ->whereBetween('logged_at', [$range['from'], $range['to']])
            ->orderByDesc('logged_at');

        if ($module = $filters['module'] ?? null) {
            $query->where('module', $module);
        }

        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }

        if ($userId = $filters['user_id'] ?? null) {
            $query->where('user_id', $userId);
        }

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($builder) use ($search) {
                $like = '%'.$search.'%';
                $builder
                    ->where('user_name', 'like', $like)
                    ->orWhere('user_email', 'like', $like)
                    ->orWhere('action', 'like', $like)
                    ->orWhere('message', 'like', $like)
                    ->orWhere('failure_reason', 'like', $like)
                    ->orWhere('action_note', 'like', $like);
            });
        }

        return $query;
    }

    private function serialize(ActivityLog $log): array
    {
        return [
            'id' => $log->uuid,
            'logged_at' => $log->logged_at?->toIso8601String(),
            'company_id' => $log->company_id,
            'user_id' => $log->user_id,
            'employee_id' => $log->employee_id,
            'user_name' => $log->user_name,
            'user_email' => $log->user_email,
            'role' => $log->role_slug,
            'module' => $log->module,
            'action' => $log->action,
            'request_type' => $log->request_type,
            'status' => $log->status,
            'message' => $log->message,
            'failure_reason' => $log->failure_reason,
            'action_note' => $log->action_note,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'context' => $log->metadata,
            'request' => [
                'ip' => $log->ip_address,
                'user_agent' => $log->user_agent,
            ],
        ];
    }

    private function resolveViewerCompanyId(User $viewer, ?int $requestedCompanyId): ?int
    {
        if ($viewer->isSuperAdmin()) {
            return $requestedCompanyId;
        }

        return $viewer->company_id;
    }

    private function appendDailyArchive(ActivityLog $entry): void
    {
        try {
            $path = $this->archivePath($entry->company_id, $entry->logged_at->toDateString());
            $line = json_encode($this->serialize($entry), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($line === false) {
                return;
            }

            $directory = dirname($path);

            if (! is_dir($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            file_put_contents($path, $line.PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
            // Archive failure must not break the app.
        }
    }

    private function archivePath(?int $companyId, string $date): string
    {
        $segment = $companyId ? 'company-'.$companyId : 'platform';

        return storage_path('app/'.self::FILE_BASE_DIR.'/'.$segment.'/'.$date.'.jsonl');
    }

    /** @param  array<string, mixed>|null  $values */
    private function sanitizeValues(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $hiddenKeys = ['password', 'password_confirmation', 'token', 'selfie', 'resume', 'banner', 'file'];
        $sanitized = [];

        foreach ($values as $key => $value) {
            if (in_array(strtolower((string) $key), $hiddenKeys, true)) {
                $sanitized[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeValues($value);
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private function throwableMessage(Throwable $error): string
    {
        if (method_exists($error, 'errors')) {
            $errors = $error->errors();
            $first = collect($errors)->flatten()->first();

            if (is_string($first) && $first !== '') {
                return $first;
            }
        }

        return $error->getMessage() ?: 'Unknown error';
    }
}
