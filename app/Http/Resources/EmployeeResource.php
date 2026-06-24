<?php

namespace App\Http\Resources;

use App\Models\Role;
use App\Models\WeeklyOffDay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'user_id' => $this->user_id,
            'employee_code' => $this->employee_code,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'personal_email' => $this->personal_email,
            'phone' => $this->phone,
            'designation' => $this->designation,
            'joining_date' => $this->joining_date?->format('Y-m-d'),
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'employment_type' => $this->employment_type,
            'status' => $this->status,
            'probation_applicable' => $this->probation_applicable,
            'probation_period_months' => $this->probation_period_months,
            'probation_end_date' => $this->probation_end_date?->format('Y-m-d'),
            'probation_status' => $this->probation_status,
            'address_line_1' => $this->address_line_1,
            'address_line_2' => $this->address_line_2,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'postal_code' => $this->postal_code,
            'full_address' => $this->full_address,
            'pan_number' => $this->pan_number,
            'aadhaar_number' => $this->aadhaar_number,
            'uan' => $this->uan,
            'pf_number' => $this->pf_number,
            'esi_number' => $this->esi_number,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'emergency_contact_relation' => $this->emergency_contact_relation,
            'department_id' => $this->department_id,
            'department_ids' => $this->whenLoaded('departments', fn () => $this->departments->pluck('id')->values()),
            'role_id' => $this->role_id,
            'manager_id' => $this->manager_id,
            'shift_id' => $this->shift_id,
            'weekly_off_mode' => $this->weekly_off_mode ?? 'company_default',
            'weekly_off_weekdays' => $this->whenLoaded(
                'weeklyOffDays',
                fn () => $this->weeklyOffDays->pluck('weekday')->map(fn ($weekday) => (int) $weekday)->values()->all(),
                [],
            ),
            'weekly_off_labels' => $this->whenLoaded(
                'weeklyOffDays',
                fn () => $this->weeklyOffDays
                    ->pluck('weekday')
                    ->map(fn ($weekday) => WeeklyOffDay::label((int) $weekday))
                    ->values()
                    ->all(),
                [],
            ),
            'leave_type_ids' => $this->whenLoaded(
                'leaveTypes',
                fn () => $this->leaveTypes->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                [],
            ),
            'leave_types' => LeaveTypeResource::collection($this->whenLoaded('leaveTypes')),
            'has_portal_access' => ! is_null($this->user_id),
            'is_company_admin' => $this->relationLoaded('role')
                ? $this->role?->slug === Role::SLUG_COMPANY_ADMIN
                : null,
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'departments' => DepartmentResource::collection($this->whenLoaded('departments')),
            'shift' => new ShiftResource($this->whenLoaded('shift')),
            'role' => new RoleResource($this->whenLoaded('role')),
            'manager' => new EmployeeResource($this->whenLoaded('manager')),
            'company' => new CompanyResource($this->whenLoaded('company')),
            'salary' => new EmployeeSalaryResource($this->whenLoaded('salary')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
