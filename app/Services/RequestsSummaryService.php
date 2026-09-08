<?php

namespace App\Services;

use App\Models\AttendanceRegularizationRequest;
use App\Models\EmployeeComplianceField;
use App\Models\EmployeeDocument;
use App\Models\EmployeeFamilyMember;
use App\Models\EmployeePaymentMethod;
use App\Models\EmployeePersonalSection;
use App\Models\Expense;
use App\Models\ExpenseGroup;
use App\Models\JobRequisition;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RequestsSummaryService
{
    /** @return array{headings: string[], rows: array<int, array<int|string|null>>, pagination: array<string, int|null>} */
    public function report(User $user, array $filters = []): array
    {
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;

        if (! $fromDate || ! $toDate) {
            throw ValidationException::withMessages([
                'from_date' => ['From date is required.'],
                'to_date' => ['To date is required.'],
            ]);
        }

        $from = Carbon::parse($fromDate)->startOfDay();
        $to = Carbon::parse($toDate)->endOfDay();
        $types = collect($filters['request_types'] ?? [])->filter()->values()->all();

        $rows = collect()
            ->merge($this->leaveRows($user, $from, $to, $types))
            ->merge($this->regularizationRows($user, $from, $to, $types))
            ->merge($this->expenseRows($user, $from, $to, $types))
            ->merge($this->expenseGroupRows($user, $from, $to, $types))
            ->merge($this->profileUpdateRows($user, $from, $to, $types))
            ->merge($this->requisitionRows($user, $from, $to, $types))
            ->sortByDesc('sort_at')
            ->values();

        $headings = [
            'Request Type',
            'Request By',
            'Employee',
            'Requested On',
            'Status',
            'Details',
            'Actioned By',
            'Actioned On',
            'Action Note',
        ];

        $mapped = $rows->map(fn (array $row) => [
            $row['request_type'],
            $row['request_by'],
            $row['employee'],
            $row['requested_on'],
            $row['status'],
            $row['details'],
            $row['actioned_by'],
            $row['actioned_on'],
            $row['action_note'],
        ]);

        return $this->paginateRows($headings, $mapped, $filters);
    }

    private function leaveRows(User $user, Carbon $from, Carbon $to, array $types): Collection
    {
        if ($types !== [] && ! in_array('leave', $types, true)) {
            return collect();
        }

        return LeaveRequest::query()
            ->with(['employee', 'leaveType', 'appliedBy', 'reviewedBy'])
            ->where('company_id', $user->company_id)
            ->whereBetween('created_at', [$from, $to])
            ->get()
            ->map(fn (LeaveRequest $request) => [
                'sort_at' => $request->created_at?->timestamp ?? 0,
                'request_type' => 'Leave',
                'request_by' => $request->appliedBy?->name ?? '—',
                'employee' => $request->employee?->full_name ?? '—',
                'requested_on' => $request->created_at?->labelStack(),
                'status' => ucfirst(str_replace('_', ' ', (string) $request->status)),
                'details' => trim(($request->leaveType?->name ?? 'Leave').' · '.$request->from_date?->format('d M Y').' to '.$request->to_date?->format('d M Y')),
                'actioned_by' => $request->reviewedBy?->name ?? '—',
                'actioned_on' => $request->reviewed_at?->labelStack() ?? '—',
                'action_note' => $request->review_notes ?? '—',
            ]);
    }

    private function regularizationRows(User $user, Carbon $from, Carbon $to, array $types): Collection
    {
        if ($types !== [] && ! in_array('attendance_regularization', $types, true)) {
            return collect();
        }

        return AttendanceRegularizationRequest::query()
            ->with(['employee', 'appliedBy', 'reviewedBy'])
            ->where('company_id', $user->company_id)
            ->whereBetween('created_at', [$from, $to])
            ->get()
            ->map(fn (AttendanceRegularizationRequest $request) => [
                'sort_at' => $request->created_at?->timestamp ?? 0,
                'request_type' => 'Attendance Regularization',
                'request_by' => $request->appliedBy?->name ?? '—',
                'employee' => $request->employee?->full_name ?? '—',
                'requested_on' => $request->created_at?->labelStack(),
                'status' => ucfirst(str_replace('_', ' ', (string) $request->status)),
                'details' => trim(($request->attendance_date?->format('d M Y') ?? '—').' · '.($request->reason ?? '')),
                'actioned_by' => $request->reviewedBy?->name ?? '—',
                'actioned_on' => $request->reviewed_at?->labelStack() ?? '—',
                'action_note' => $request->review_notes ?? '—',
            ]);
    }

    private function expenseRows(User $user, Carbon $from, Carbon $to, array $types): Collection
    {
        if ($types !== [] && ! in_array('expense', $types, true)) {
            return collect();
        }

        return Expense::query()
            ->with(['employee', 'submittedBy', 'reviewedBy', 'expenseType'])
            ->where('company_id', $user->company_id)
            ->whereNull('expense_group_id')
            ->whereBetween('created_at', [$from, $to])
            ->get()
            ->map(fn (Expense $expense) => [
                'sort_at' => $expense->created_at?->timestamp ?? 0,
                'request_type' => 'Expense',
                'request_by' => $expense->submittedBy?->name ?? $expense->employee?->full_name ?? '—',
                'employee' => $expense->employee?->full_name ?? '—',
                'requested_on' => $expense->created_at?->labelStack(),
                'status' => ucfirst(str_replace('_', ' ', (string) $expense->status)),
                'details' => trim(($expense->expenseType?->name ?? 'Expense').' · ₹'.number_format((float) $expense->amount, 2)),
                'actioned_by' => $expense->reviewedBy?->name ?? '—',
                'actioned_on' => $expense->reviewed_at?->labelStack() ?? '—',
                'action_note' => $expense->review_notes ?? '—',
            ]);
    }

    private function expenseGroupRows(User $user, Carbon $from, Carbon $to, array $types): Collection
    {
        if ($types !== [] && ! in_array('expense_group', $types, true)) {
            return collect();
        }

        return ExpenseGroup::query()
            ->with(['employee', 'submittedBy', 'reviewedBy'])
            ->where('company_id', $user->company_id)
            ->whereBetween('created_at', [$from, $to])
            ->get()
            ->map(fn (ExpenseGroup $group) => [
                'sort_at' => $group->created_at?->timestamp ?? 0,
                'request_type' => 'Expense Group',
                'request_by' => $group->submittedBy?->name ?? $group->employee?->full_name ?? '—',
                'employee' => $group->employee?->full_name ?? '—',
                'requested_on' => $group->created_at?->labelStack(),
                'status' => ucfirst(str_replace('_', ' ', (string) $group->status)),
                'details' => trim(($group->title ?? 'Expense group').' · ₹'.number_format((float) $group->total_amount, 2)),
                'actioned_by' => $group->reviewedBy?->name ?? '—',
                'actioned_on' => $group->reviewed_at?->labelStack() ?? '—',
                'action_note' => $group->review_notes ?? '—',
            ]);
    }

    private function profileUpdateRows(User $user, Carbon $from, Carbon $to, array $types): Collection
    {
        if ($types !== [] && ! in_array('employee_core_update', $types, true)) {
            return collect();
        }

        $rows = collect();

        EmployeeDocument::query()
            ->with(['employee', 'uploadedBy', 'reviewedBy', 'documentType'])
            ->where('company_id', $user->company_id)
            ->whereBetween('created_at', [$from, $to])
            ->get()
            ->each(function (EmployeeDocument $item) use ($rows) {
                $rows->push($this->profileRow($item, 'Document', $item->documentType?->name ?? 'Document', 'uploadedBy'));
            });

        EmployeePaymentMethod::query()
            ->with(['employee', 'submittedBy', 'reviewedBy'])
            ->where('company_id', $user->company_id)
            ->whereBetween('created_at', [$from, $to])
            ->get()
            ->each(function (EmployeePaymentMethod $item) use ($rows) {
                $rows->push($this->profileRow($item, 'Bank Details', ucfirst(str_replace('_', ' ', (string) $item->payment_mode))));
            });

        EmployeeFamilyMember::query()
            ->with(['employee', 'submittedBy', 'reviewedBy'])
            ->where('company_id', $user->company_id)
            ->whereBetween('created_at', [$from, $to])
            ->get()
            ->each(function (EmployeeFamilyMember $item) use ($rows) {
                $rows->push($this->profileRow($item, 'Family Member', $item->name ?? 'Family member'));
            });

        EmployeePersonalSection::query()
            ->with(['employee', 'submittedBy', 'reviewedBy'])
            ->where('company_id', $user->company_id)
            ->whereBetween('created_at', [$from, $to])
            ->get()
            ->each(function (EmployeePersonalSection $item) use ($rows) {
                $rows->push($this->profileRow($item, 'Personal Info', EmployeePersonalSection::SECTION_LABELS[$item->section_type] ?? ucfirst(str_replace('_', ' ', (string) $item->section_type))));
            });

        EmployeeComplianceField::query()
            ->with(['employee', 'submittedBy', 'reviewedBy'])
            ->where('company_id', $user->company_id)
            ->whereBetween('created_at', [$from, $to])
            ->get()
            ->each(function (EmployeeComplianceField $item) use ($rows) {
                $rows->push($this->profileRow($item, 'Compliance', strtoupper((string) $item->field_type)));
            });

        return $rows;
    }

    private function profileRow(object $item, string $label, string $detail, string $submitterRelation = 'submittedBy'): array
    {
        $submitter = $item->{$submitterRelation} ?? null;

        return [
            'sort_at' => $item->created_at?->timestamp ?? 0,
            'request_type' => 'Employee Core Update',
            'request_by' => $submitter?->name ?? $item->employee?->full_name ?? '—',
            'employee' => $item->employee?->full_name ?? '—',
            'requested_on' => $item->created_at?->labelStack(),
            'status' => ucfirst(str_replace('_', ' ', (string) $item->status)),
            'details' => trim($label.' · '.$detail),
            'actioned_by' => $item->reviewedBy?->name ?? '—',
            'actioned_on' => $item->reviewed_at?->labelStack() ?? '—',
            'action_note' => $item->notes ?? '—',
        ];
    }

    private function requisitionRows(User $user, Carbon $from, Carbon $to, array $types): Collection
    {
        if ($types !== [] && ! in_array('job_requisition', $types, true)) {
            return collect();
        }

        return JobRequisition::query()
            ->with(['requestedBy', 'approver', 'department'])
            ->where('company_id', $user->company_id)
            ->whereBetween('created_at', [$from, $to])
            ->get()
            ->map(fn (JobRequisition $requisition) => [
                'sort_at' => $requisition->created_at?->timestamp ?? 0,
                'request_type' => 'Job Requisition',
                'request_by' => $requisition->requestedBy?->name ?? '—',
                'employee' => '—',
                'requested_on' => $requisition->created_at?->labelStack(),
                'status' => ucfirst(str_replace('_', ' ', (string) $requisition->status)),
                'details' => trim(($requisition->title ?? 'Requisition').' · '.($requisition->department?->name ?? '')),
                'actioned_by' => $requisition->approver?->name ?? '—',
                'actioned_on' => $requisition->approved_at?->labelStack() ?? '—',
                'action_note' => $requisition->rejection_reason ?? '—',
            ]);
    }

    /** @param  Collection<int, array<int|string|null>>  $rows */
    private function paginateRows(array $headings, Collection $rows, array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = (int) ($filters['per_page'] ?? 25);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;
        $total = $rows->count();
        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return [
            'headings' => $headings,
            'rows' => $items->all(),
            'pagination' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total ? (($page - 1) * $perPage) + 1 : 0,
                'to' => min($page * $perPage, $total),
            ],
        ];
    }
}
