<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_code' => $this->employee_code,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'personal_email' => $this->personal_email,
            'phone' => $this->phone,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'designation' => $this->designation,
            'profile_photo_url' => $this->profilePhotoUrl(),
            'profile_photo_submission' => new EmployeeProfilePhotoResource($this->whenLoaded('profilePhotoSubmission')),
            'joining_date' => $this->joining_date?->format('Y-m-d'),
            'employment_type' => $this->employment_type,
            'is_paid_employee' => $this->isPaidEmployee(),
            'is_paid_employee_label' => $this->isPaidEmployee() ? 'Paid' : 'Non-paid',
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
            'temp_address_line_1' => $this->temp_address_line_1,
            'temp_address_line_2' => $this->temp_address_line_2,
            'temp_city' => $this->temp_city,
            'temp_state' => $this->temp_state,
            'temp_country' => $this->temp_country,
            'temp_postal_code' => $this->temp_postal_code,
            'temp_full_address' => $this->temp_full_address,
            'pan_number' => $this->pan_number,
            'aadhaar_number' => $this->aadhaar_number,
            'uan' => $this->uan,
            'pf_number' => $this->pf_number,
            'esi_number' => $this->esi_number,
            'compliance_fields' => EmployeeComplianceFieldResource::collection($this->whenLoaded('complianceFields')),
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'emergency_contact_relation' => $this->emergency_contact_relation,
            'emergency_contact_family_member_id' => $this->emergency_contact_family_member_id,
            'family_members' => EmployeeFamilyMemberResource::collection($this->whenLoaded('familyMembers')),
            'personal_sections' => EmployeePersonalSectionResource::collection($this->whenLoaded('personalSections')),
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'departments' => DepartmentResource::collection($this->whenLoaded('departments')),
            'role' => new RoleResource($this->whenLoaded('role')),
            'manager' => $this->whenLoaded('manager', fn () => [
                'id' => $this->manager->id,
                'full_name' => $this->manager->full_name,
                'employee_code' => $this->manager->employee_code,
                'designation' => $this->manager->designation,
                'profile_photo_url' => $this->manager->profilePhotoUrl(),
            ]),
            'direct_reports' => $this->whenLoaded('directReports', fn () => $this->directReports->map(fn ($report) => [
                'id' => $report->id,
                'full_name' => $report->full_name,
                'employee_code' => $report->employee_code,
                'designation' => $report->designation,
                'profile_photo_url' => $report->profilePhotoUrl(),
            ])->values()),
            'shift' => new ShiftResource($this->whenLoaded('shift')),
            'company' => new CompanyResource($this->whenLoaded('company')),
            'salary' => new EmployeeSalaryResource($this->whenLoaded('salary')),
            'salary_revisions' => EmployeeSalaryRevisionResource::collection($this->whenLoaded('salaryRevisions')),
            'payment_methods' => EmployeePaymentMethodResource::collection($this->whenLoaded('paymentMethods')),
            'documents' => EmployeeDocumentResource::collection($this->whenLoaded('documents')),
            'assets' => EmployeeAssetResource::collection(
                $this->when(
                    $this->relationLoaded('employeeAssets'),
                    fn () => app(\App\Services\EmployeeAssetService::class)->assignmentsForEmployee($this->resource)
                )
            ),
        ];
    }
}
