<?php

namespace App\Services;

use App\Http\Resources\EmployeePersonalSectionResource;
use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeePaymentMethodProof;
use App\Models\User;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EmployeeProfileService
{
    public function __construct(
        private EmployeeDocumentService $employeeDocumentService,
        private EmployeePaymentMethodService $paymentMethodService,
        private EmployeeComplianceFieldService $complianceFieldService,
        private EmployeePersonalSectionService $personalSectionService,
        private EmployeeFamilyMemberService $familyMemberService,
        private EmployeeService $employeeService,
        private EmployeeAssetService $employeeAssetService,
    ) {}

    public function resolveEmployee(User $user): Employee
    {
        $employee = $user->employee;

        if (! $employee) {
            throw new NotFoundHttpException('No employee profile is linked to your account.');
        }

        return $employee;
    }

    public function resolveEmployeeForActor(User $user, Employee $employee): Employee
    {
        if ((int) $employee->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Employee not found.');
        }

        if (! $user->canEditEmployeeProfileWithoutApproval($employee)) {
            throw new AccessDeniedHttpException('You are not allowed to update this employee profile.');
        }

        return $employee;
    }

    public function loadProfile(Employee $employee): Employee
    {
        return $employee->load([
            'department',
            'departments',
            'role',
            'manager',
            'shift',
            'company',
            'salary',
            'salaryRevisions.revisedBy',
            'paymentMethods.submittedBy.role',
            'paymentMethods.reviewedBy',
            'paymentMethods.proofs',
            'complianceFields.submittedBy.role',
            'complianceFields.reviewedBy',
            'familyMembers.submittedBy.role',
            'familyMembers.reviewedBy',
            'personalSections.submittedBy.role',
            'personalSections.reviewedBy',
            'familyMembers',
            'emergencyContactFamilyMember',
            'documents.documentType',
            'documents.uploadedBy.role',
            'documents.reviewedBy',
            'employeeAssets.assetType',
        ]);
    }

    public function documentTypesForCompany(int $companyId)
    {
        return DocumentType::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    public function submitFamilyMembers(User $user, Employee $employee, array $members): array
    {
        return $this->familyMemberService->submitMany($user, $employee, $members);
    }

    public function assetsForEmployee(Employee $employee): Collection
    {
        return $this->employeeAssetService->assignmentsForEmployee($employee);
    }

    public function updateAssets(User $user, Employee $employee, array $assets): Employee
    {
        $employee = $this->resolveEmployeeForActor($user, $employee);
        $this->employeeAssetService->syncAssignments($employee, $assets);

        return $this->loadProfile($employee->fresh());
    }

    public function updateSalary(User $user, Employee $employee, array $data): Employee
    {
        $employee = $this->resolveEmployeeForActor($user, $employee);

        $this->employeeService->reviseSalary(
            $employee,
            collect($data)->only([
                'annual_ctc',
                'basic_salary',
                'hra_percent',
                'special_allowance_percent',
                'conveyance_allowance',
                'medical_allowance',
                'other_allowance',
                'pf_applicable',
                'esi_applicable',
                'professional_tax_applicable',
                'salary_effective_from',
                'salary_payout_from',
            ])->all(),
            $user,
            $data['revision_notes'] ?? null,
        );

        return $this->loadProfile($employee->fresh());
    }

    public function submitPersonalSection(User $user, Employee $employee, array $data): array
    {
        return $this->personalSectionService->submit(
            $user,
            $employee,
            $data['section_type'],
            $data['payload']
        );
    }

    public function submitPaymentMethod(User $user, Employee $employee, array $data, array $proofFiles = []): array
    {
        return $this->paymentMethodService->submit($user, $employee, $data, $proofFiles);
    }

    public function downloadPaymentMethodProof(User $user, Employee $employee, EmployeePaymentMethodProof $proof): array
    {
        if ((int) $proof->employee_id !== (int) $employee->id) {
            throw new NotFoundHttpException('Bank proof not found.');
        }

        return $this->paymentMethodService->downloadProofForUser($user, $proof);
    }

    public function submitComplianceField(User $user, Employee $employee, array $data): array
    {
        return $this->complianceFieldService->submit(
            $user,
            $employee,
            $data['field_type'],
            $data['value']
        );
    }

    public function storeDocument(User $user, Employee $employee, int $documentTypeId, array $files): array
    {
        return $this->employeeDocumentService->store($user, $employee, $documentTypeId, $files);
    }

    public function downloadDocument(User $user, Employee $employee, EmployeeDocument $document): array
    {
        if ((int) $document->employee_id !== (int) $employee->id) {
            throw new NotFoundHttpException('Document not found.');
        }

        return $this->employeeDocumentService->downloadForUser($user, $document);
    }

    public function pendingReviewsForEmployee(User $user, Employee $employee): array
    {
        $items = [];

        foreach ($employee->familyMembers->where('status', 'pending') as $member) {
            $items[] = [
                'type' => 'family_member',
                'id' => $member->id,
                'label' => 'Family Member',
                'summary' => trim("{$member->name} ({$member->relation})"),
                'submitted_at' => $member->submitted_at?->toIso8601String(),
                'can_review' => $user->canReviewFamilyMember($member),
            ];
        }

        foreach ($employee->personalSections->where('status', 'pending') as $section) {
            $items[] = [
                'type' => 'personal_section',
                'id' => $section->id,
                'label' => $section->label(),
                'summary' => (new EmployeePersonalSectionResource($section))->resolve()['summary'] ?? '—',
                'submitted_at' => $section->submitted_at?->toIso8601String(),
                'can_review' => $user->canReviewPersonalSection($section),
            ];
        }

        foreach ($employee->paymentMethods->where('status', 'pending') as $method) {
            $items[] = [
                'type' => 'payment_method',
                'id' => $method->id,
                'label' => 'Payment Method',
                'summary' => ucfirst(str_replace('_', ' ', $method->payment_mode)),
                'submitted_at' => $method->submitted_at?->toIso8601String(),
                'can_review' => $user->canReviewPaymentMethod($method),
            ];
        }

        foreach ($employee->complianceFields->where('status', 'pending') as $field) {
            $items[] = [
                'type' => 'compliance_field',
                'id' => $field->id,
                'label' => strtoupper($field->field_type),
                'summary' => $field->value,
                'submitted_at' => $field->submitted_at?->toIso8601String(),
                'can_review' => $user->canReviewComplianceField($field),
            ];
        }

        foreach ($employee->documents->where('status', 'pending') as $document) {
            $items[] = [
                'type' => 'document',
                'id' => $document->id,
                'label' => $document->documentType?->name ?? 'Document',
                'summary' => 'Uploaded by '.($document->uploadedBy?->name ?? '—'),
                'submitted_at' => $document->created_at?->toIso8601String(),
                'can_review' => $user->canReviewDocument($document),
            ];
        }

        return collect($items)
            ->sortByDesc('submitted_at')
            ->values()
            ->all();
    }
}
