<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\EmployeeComplianceField;
use App\Models\EmployeeDocument;
use App\Models\EmployeeFamilyMember;
use App\Models\EmployeePaymentMethod;
use App\Models\EmployeePersonalSection;
use App\Models\EmployeeProfilePhoto;
use App\Models\EmployeeSalaryRevision;
use App\Models\User;
use App\Support\ArrayPaginator;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EmployeeJourneyService
{
    public function __construct(private DateRangePresetService $dateRangePresetService) {}

    /** @param  array<string, mixed>  $rangeInput */
    public function forEmployee(User $viewer, Employee $employee, array $rangeInput = [], int $page = 1, int $perPage = 10): array
    {
        $this->authorize($viewer, $employee);

        $employee->loadMissing('department');
        $range = $this->dateRangePresetService->resolve($rangeInput);
        $from = $range['from']->copy()->startOfDay();
        $to = $range['to']->copy()->endOfDay();

        $entries = collect()
            ->merge($this->milestoneEntries($employee, $from, $to))
            ->merge($this->activityLogEntries($employee, $from, $to))
            ->merge($this->profileSubmissionEntries($employee, $from, $to))
            ->merge($this->salaryRevisionEntries($employee, $from, $to))
            ->sortByDesc(fn (array $entry) => $entry['occurred_at'])
            ->values()
            ->all();

        $paginated = ArrayPaginator::paginate($entries, $page, $perPage);

        return [
            'date_range' => [
                'preset' => $range['preset'],
                'from_date' => $range['from_date'],
                'to_date' => $range['to_date'],
            ],
            'date_range_presets' => $this->dateRangePresetService->presets(),
            'entries' => $paginated['items'],
            'pagination' => $paginated['pagination'],
            'total' => count($entries),
        ];
    }

    private function authorize(User $viewer, Employee $employee): void
    {
        if ((int) $employee->company_id !== (int) $viewer->company_id && ! $viewer->isSuperAdmin()) {
            throw new AccessDeniedHttpException('You cannot view this employee journey.');
        }

        if (! $viewer->canViewEmployees() && (int) $viewer->employee?->id !== (int) $employee->id) {
            throw new AccessDeniedHttpException('You cannot view this employee journey.');
        }
    }

    /** @return Collection<int, array<string, mixed>> */
    private function milestoneEntries(Employee $employee, Carbon $from, Carbon $to): Collection
    {
        $entries = collect();

        if ($employee->joining_date) {
            $joinedAt = Carbon::parse($employee->joining_date)->startOfDay();

            if ($joinedAt->betweenIncluded($from, $to)) {
                $entries->push($this->entry([
                    'id' => 'milestone-joined-'.$employee->id,
                    'occurred_at' => $joinedAt,
                    'category' => 'milestone',
                    'kind' => 'joined',
                    'title' => 'Joined the company',
                    'subtitle' => 'Employee onboarding',
                    'status' => 'success',
                    'status_label' => 'Joined',
                    'actor' => ['name' => $employee->full_name, 'email' => $employee->email],
                    'marker' => 'milestone',
                    'details' => array_filter([
                        'Employee ID' => $employee->employee_code,
                        'Department' => $employee->department?->name,
                        'Designation' => $employee->designation,
                    ]),
                ]));
            }
        }

        return $entries;
    }

    /** @return Collection<int, array<string, mixed>> */
    private function activityLogEntries(Employee $employee, Carbon $from, Carbon $to): Collection
    {
        $employeeId = (int) $employee->id;
        $userId = $employee->user_id ? (int) $employee->user_id : null;

        return ActivityLog::query()
            ->where('company_id', $employee->company_id)
            ->whereBetween('logged_at', [$from, $to])
            ->where(function ($builder) use ($employeeId, $userId) {
                $builder->where('employee_id', $employeeId)
                    ->orWhere(function ($nested) use ($employeeId) {
                        $nested
                            ->where('subject_type', Employee::class)
                            ->where('subject_id', $employeeId);
                    });

                if ($userId) {
                    $builder->orWhere('user_id', $userId);
                }
            })
            ->orderByDesc('logged_at')
            ->get()
            ->map(fn (ActivityLog $log) => $this->entryFromActivityLog($log, $employee));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function profileSubmissionEntries(Employee $employee, Carbon $from, Carbon $to): Collection
    {
        $entries = collect();

        $this->appendProfileRows($entries, EmployeeDocument::class, 'document', $employee->id, $from, $to, function ($row) {
            return [
                'title' => 'Document submitted',
                'subtitle' => $row->documentType?->name ?? 'Document',
                'actor' => $this->actorFromUser($row->uploadedBy),
                'details' => ['File' => $row->original_name],
            ];
        }, function ($row) {
            return [
                'title' => 'Document '.($row->status === 'approved' ? 'approved' : 'rejected'),
                'subtitle' => $row->documentType?->name ?? 'Document',
                'actor' => $this->actorFromUser($row->reviewedBy),
                'note' => $row->notes,
                'details' => ['File' => $row->original_name, 'Reviewed by' => $row->reviewedBy?->name],
            ];
        }, ['documentType', 'uploadedBy', 'reviewedBy']);

        $this->appendProfileRows($entries, EmployeePaymentMethod::class, 'payment_method', $employee->id, $from, $to, function ($row) {
            return [
                'title' => 'Bank details submitted',
                'subtitle' => ucwords(str_replace('_', ' ', (string) $row->payment_mode)),
                'actor' => $this->actorFromUser($row->submittedBy),
                'details' => ['Account holder' => $row->account_holder_name],
            ];
        }, function ($row) {
            return [
                'title' => 'Bank details '.($row->status === 'approved' ? 'approved' : 'rejected'),
                'subtitle' => ucwords(str_replace('_', ' ', (string) $row->payment_mode)),
                'actor' => $this->actorFromUser($row->reviewedBy),
                'note' => $row->notes,
                'details' => ['Reviewed by' => $row->reviewedBy?->name],
            ];
        }, ['submittedBy', 'reviewedBy']);

        $this->appendProfileRows($entries, EmployeeComplianceField::class, 'compliance', $employee->id, $from, $to, function ($row) {
            return [
                'title' => 'Compliance field submitted',
                'subtitle' => strtoupper((string) $row->field_type),
                'actor' => $this->actorFromUser($row->submittedBy),
                'details' => ['Field' => strtoupper((string) $row->field_type)],
            ];
        }, function ($row) {
            return [
                'title' => 'Compliance field '.($row->status === 'approved' ? 'approved' : 'rejected'),
                'subtitle' => strtoupper((string) $row->field_type),
                'actor' => $this->actorFromUser($row->reviewedBy),
                'note' => $row->notes,
                'details' => ['Reviewed by' => $row->reviewedBy?->name],
            ];
        }, ['submittedBy', 'reviewedBy']);

        $this->appendProfileRows($entries, EmployeePersonalSection::class, 'personal_section', $employee->id, $from, $to, function ($row) {
            return [
                'title' => 'Personal section submitted',
                'subtitle' => ucwords(str_replace('_', ' ', (string) $row->section_type)),
                'actor' => $this->actorFromUser($row->submittedBy),
                'details' => ['Section' => ucwords(str_replace('_', ' ', (string) $row->section_type))],
            ];
        }, function ($row) {
            return [
                'title' => 'Personal section '.($row->status === 'approved' ? 'approved' : 'rejected'),
                'subtitle' => ucwords(str_replace('_', ' ', (string) $row->section_type)),
                'actor' => $this->actorFromUser($row->reviewedBy),
                'note' => $row->notes,
                'details' => ['Reviewed by' => $row->reviewedBy?->name],
            ];
        }, ['submittedBy', 'reviewedBy']);

        $this->appendProfileRows($entries, EmployeeFamilyMember::class, 'family_member', $employee->id, $from, $to, function ($row) {
            return [
                'title' => 'Family member submitted',
                'subtitle' => $row->name,
                'actor' => $this->actorFromUser($row->submittedBy),
                'details' => ['Relation' => $row->relation],
            ];
        }, function ($row) {
            return [
                'title' => 'Family member '.($row->status === 'approved' ? 'approved' : 'rejected'),
                'subtitle' => $row->name,
                'actor' => $this->actorFromUser($row->reviewedBy),
                'note' => $row->notes,
                'details' => ['Reviewed by' => $row->reviewedBy?->name],
            ];
        }, ['submittedBy', 'reviewedBy']);

        $this->appendProfileRows($entries, EmployeeProfilePhoto::class, 'profile_photo', $employee->id, $from, $to, function ($row) {
            return [
                'title' => 'Profile photo submitted',
                'subtitle' => 'Profile photo',
                'actor' => $this->actorFromUser($row->submittedBy),
                'details' => [],
            ];
        }, function ($row) {
            return [
                'title' => 'Profile photo '.($row->status === 'approved' ? 'approved' : 'rejected'),
                'subtitle' => 'Profile photo',
                'actor' => $this->actorFromUser($row->reviewedBy),
                'note' => $row->notes,
                'details' => ['Reviewed by' => $row->reviewedBy?->name],
            ];
        }, ['submittedBy', 'reviewedBy']);

        return $entries;
    }

    /**
     * @param  class-string  $modelClass
     * @param  array<int, string>  $with
     */
    private function appendProfileRows(
        Collection $entries,
        string $modelClass,
        string $prefix,
        int $employeeId,
        Carbon $from,
        Carbon $to,
        callable $submitShape,
        callable $reviewShape,
        array $with = [],
    ): void {
        $modelClass::query()
            ->with($with)
            ->where('employee_id', $employeeId)
            ->get()
            ->each(function ($row) use ($entries, $prefix, $from, $to, $submitShape, $reviewShape) {
                if ($row->created_at && Carbon::parse($row->created_at)->betweenIncluded($from, $to)) {
                    $shape = $submitShape($row);
                    $entries->push($this->entry(array_merge($shape, [
                        'id' => $prefix.'-submit-'.$row->id,
                        'occurred_at' => $row->created_at,
                        'category' => 'profile',
                        'kind' => $prefix.'_submitted',
                        'status' => $row->status ?? 'pending',
                        'status_label' => ucfirst((string) ($row->status ?? 'pending')),
                        'marker' => $this->statusMarker($row->status ?? 'pending'),
                    ])));
                }

                if ($row->reviewed_at && Carbon::parse($row->reviewed_at)->betweenIncluded($from, $to)) {
                    $shape = $reviewShape($row);
                    $entries->push($this->entry(array_merge($shape, [
                        'id' => $prefix.'-review-'.$row->id,
                        'occurred_at' => $row->reviewed_at,
                        'category' => 'profile',
                        'kind' => $prefix.'_'.$row->status,
                        'status' => $row->status,
                        'status_label' => ucfirst((string) $row->status),
                        'marker' => $this->statusMarker($row->status),
                    ])));
                }
            });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function salaryRevisionEntries(Employee $employee, Carbon $from, Carbon $to): Collection
    {
        return EmployeeSalaryRevision::query()
            ->with('revisedBy')
            ->where('employee_id', $employee->id)
            ->whereBetween('revised_at', [$from, $to])
            ->orderByDesc('revised_at')
            ->get()
            ->map(function (EmployeeSalaryRevision $revision) {
                $typeLabel = match ($revision->revision_type) {
                    'increment' => 'Salary increment',
                    'correction' => 'Salary correction',
                    default => 'Salary revised',
                };

                return $this->entry([
                    'id' => 'salary-revision-'.$revision->id,
                    'occurred_at' => $revision->revised_at,
                    'category' => 'salary',
                    'kind' => 'salary_revision',
                    'title' => $typeLabel,
                    'subtitle' => 'Compensation update',
                    'status' => 'success',
                    'status_label' => 'Updated',
                    'actor' => $this->actorFromUser($revision->revisedBy),
                    'note' => $revision->revision_notes,
                    'marker' => 'success',
                    'details' => array_filter([
                        'Annual CTC' => $revision->annual_ctc ? '₹'.number_format((float) $revision->annual_ctc, 0) : null,
                        'Effective from' => $revision->salary_effective_from?->format('d M Y'),
                        'Revised by' => $revision->revisedBy?->name,
                    ]),
                ]);
            });
    }

    private function entryFromActivityLog(ActivityLog $log, Employee $employee): array
    {
        $labels = $this->activityLabels($log, $employee);

        return $this->entry([
            'id' => 'activity-'.$log->uuid,
            'occurred_at' => $log->logged_at,
            'category' => $log->module === 'requests' ? 'request' : $log->module,
            'kind' => $log->request_type ?: $log->action,
            'title' => $labels['title'],
            'subtitle' => $labels['subtitle'],
            'status' => $log->status,
            'status_label' => $labels['status_label'],
            'actor' => [
                'name' => $log->user_name,
                'email' => $log->user_email,
            ],
            'note' => $log->action_note,
            'marker' => $log->status === ActivityLog::STATUS_FAILURE ? 'failure' : $this->actionMarker($log->action),
            'details' => $this->activityDetails($log),
            'context' => $log->metadata,
        ]);
    }

    /** @return array{title: string, subtitle: string, status_label: string} */
    private function activityLabels(ActivityLog $log, Employee $employee): array
    {
        $requestType = $this->requestTypeLabel($log->request_type);
        $action = (string) $log->action;
        $performedByEmployee = $log->user_id && (int) $log->user_id === (int) $employee->user_id;

        if ($log->module === 'requests') {
            $title = match ($action) {
                'submitted' => "{$requestType} submitted",
                'approved' => $performedByEmployee ? "Approved {$requestType}" : "{$requestType} approved",
                'rejected' => $performedByEmployee ? "Rejected {$requestType}" : "{$requestType} rejected",
                'cancelled' => "{$requestType} cancelled",
                default => $log->message ?: "{$requestType} {$action}",
            };

            return [
                'title' => $title,
                'subtitle' => $performedByEmployee ? 'Review action' : 'Employee request',
                'status_label' => ucfirst($action),
            ];
        }

        if ($log->module === 'employees') {
            return [
                'title' => $log->message ?: 'Employee record updated',
                'subtitle' => 'HR / profile update',
                'status_label' => ucfirst(str_replace('.', ' ', $action)),
            ];
        }

        if ($log->module === 'attendance') {
            return [
                'title' => $log->message ?: 'Attendance activity',
                'subtitle' => 'Attendance',
                'status_label' => $log->status === ActivityLog::STATUS_FAILURE ? 'Failed' : 'Recorded',
            ];
        }

        if ($log->module === 'auth') {
            return [
                'title' => $log->message ?: 'Portal sign-in activity',
                'subtitle' => 'Authentication',
                'status_label' => $log->status === ActivityLog::STATUS_FAILURE ? 'Failed' : 'Success',
            ];
        }

        return [
            'title' => $log->message ?: ucfirst(str_replace('.', ' ', $action)),
            'subtitle' => ucfirst((string) $log->module),
            'status_label' => ucfirst((string) $log->status),
        ];
    }

    /** @return array<string, string|null> */
    private function activityDetails(ActivityLog $log): array
    {
        $details = [];

        if ($log->user_name) {
            $details['Performed by'] = $log->user_name;
        }

        if ($log->request_type) {
            $details['Request type'] = $this->requestTypeLabel($log->request_type);
        }

        if ($log->action) {
            $details['Action'] = ucfirst(str_replace('_', ' ', (string) $log->action));
        }

        if ($log->failure_reason) {
            $details['Reason'] = $log->failure_reason;
        }

        if (is_array($log->new_values) && $log->new_values !== []) {
            foreach ($log->new_values as $key => $value) {
                if (is_scalar($value) || $value === null) {
                    $details[(string) $key] = $value === null ? null : (string) $value;
                }
            }
        }

        return array_filter($details, fn ($value) => $value !== null && $value !== '');
    }

    private function requestTypeLabel(?string $requestType): string
    {
        return match ($requestType) {
            'leave' => 'Leave request',
            'wfh_request' => 'WFH request',
            'attendance_regularization' => 'Regularization request',
            'expense' => 'Expense claim',
            'asset_request' => 'Asset request',
            'asset_request_item' => 'Asset item request',
            'resignation_request' => 'Resignation request',
            default => $requestType ? ucwords(str_replace('_', ' ', $requestType)) : 'Request',
        };
    }

    private function actionMarker(string $action): string
    {
        return match ($action) {
            'submitted', 'punch.in', 'punch.out', 'login' => 'success',
            'approved' => 'approved',
            'rejected' => 'rejected',
            'cancelled' => 'pending',
            default => 'success',
        };
    }

    private function statusMarker(?string $status): string
    {
        return match ($status) {
            'approved' => 'approved',
            'rejected' => 'rejected',
            'pending' => 'pending',
            'cancelled' => 'pending',
            default => 'success',
        };
    }

    /** @param  array<string, mixed>  $payload */
    private function entry(array $payload): array
    {
        /** @var Carbon|\DateTimeInterface|string $occurredAt */
        $occurredAt = $payload['occurred_at'];

        return [
            'id' => (string) $payload['id'],
            'occurred_at' => Carbon::parse($occurredAt)->toIso8601String(),
            'category' => (string) ($payload['category'] ?? 'activity'),
            'kind' => (string) ($payload['kind'] ?? 'activity'),
            'title' => (string) ($payload['title'] ?? 'Activity'),
            'subtitle' => (string) ($payload['subtitle'] ?? ''),
            'status' => $payload['status'] ?? null,
            'status_label' => (string) ($payload['status_label'] ?? ''),
            'actor' => $payload['actor'] ?? ['name' => null, 'email' => null],
            'note' => $payload['note'] ?? null,
            'marker' => (string) ($payload['marker'] ?? 'success'),
            'details' => $payload['details'] ?? [],
            'context' => $payload['context'] ?? null,
        ];
    }

    /** @return array{name: ?string, email: ?string} */
    private function actorFromUser(?User $user): array
    {
        return [
            'name' => $user?->name,
            'email' => $user?->email,
        ];
    }
}
