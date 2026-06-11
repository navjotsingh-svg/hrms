<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Concerns\StreamsInlineFiles;
use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Requests\StoreEmployeeDocumentRequest;
use App\Http\Requests\StoreEmployeeComplianceFieldRequest;
use App\Http\Requests\StoreEmployeeFamilyMemberRequest;
use App\Http\Requests\StoreEmployeePersonalSectionRequest;
use App\Http\Requests\StoreEmployeePaymentMethodRequest;
use App\Http\Resources\EmployeeFamilyMemberResource;
use App\Http\Resources\EmployeePersonalSectionResource;
use App\Http\Resources\DocumentTypeResource;
use App\Http\Resources\EmployeeComplianceFieldResource;
use App\Http\Resources\EmployeeDocumentResource;
use App\Http\Resources\EmployeePaymentMethodResource;
use App\Http\Resources\EmployeeProfileResource;
use App\Http\Resources\UserResource;
use App\Models\EmployeeDocument;
use App\Services\EmployeeProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfileController extends Controller
{
    use ApiResponse, StreamsInlineFiles;

    public function __construct(
        private EmployeeProfileService $employeeProfileService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return $this->success([
            'user' => new UserResource($request->user()->load(['company', 'role'])),
        ]);
    }

    public function showEmployee(Request $request): JsonResponse
    {
        $employee = $this->employeeProfileService->resolveEmployee($request->user());
        $employee = $this->employeeProfileService->loadProfile($employee);

        return $this->success([
            'employee' => new EmployeeProfileResource($employee),
            'document_types' => DocumentTypeResource::collection(
                $this->employeeProfileService->documentTypesForCompany($employee->company_id)
            ),
            'capabilities' => [
                'can_review_documents' => $request->user()->canReviewEmployeeDocuments(),
                'can_review_bank' => $request->user()->canReviewEmployeeDocuments(),
                'can_review_payment_methods' => $request->user()->canReviewEmployeeDocuments(),
                'can_review_compliances' => $request->user()->canReviewEmployeeDocuments(),
                'can_review_personal_sections' => $request->user()->canReviewEmployeeDocuments(),
                'can_review_family_members' => $request->user()->canReviewEmployeeDocuments(),
                'can_update_contact_info' => $request->user()->canUpdateEmployeeContactInfo(),
                'can_edit_without_approval' => $request->user()->canEditEmployeeProfileWithoutApproval($employee),
                'can_manage_salary' => $request->user()->canEditEmployeeProfileWithoutApproval($employee),
                'can_manage_assets' => $request->user()->canEditEmployeeProfileWithoutApproval($employee),
            ],
        ]);
    }

    public function storeFamilyMembers(StoreEmployeeFamilyMemberRequest $request): JsonResponse
    {
        $employee = $this->employeeProfileService->resolveEmployee($request->user());
        $results = $this->employeeProfileService->submitFamilyMembers(
            $request->user(),
            $employee,
            $request->validated()['members']
        );

        $employee = $this->employeeProfileService->loadProfile($employee->fresh());
        $autoApproved = collect($results)->contains(fn (array $result) => ! empty($result['auto_approved']));

        return $this->success(
            [
                'employee' => new EmployeeProfileResource($employee),
                'family_members' => EmployeeFamilyMemberResource::collection(
                    collect($results)->pluck('family_member')
                ),
            ],
            $autoApproved
                ? 'Family member(s) saved successfully.'
                : 'Family member(s) submitted successfully. They are pending approval.',
            201
        );
    }

    public function storePersonalSection(StoreEmployeePersonalSectionRequest $request): JsonResponse
    {
        $employee = $this->employeeProfileService->resolveEmployee($request->user());
        $validated = $request->validated();
        $result = $this->employeeProfileService->submitPersonalSection(
            $request->user(),
            $employee,
            $validated
        );

        $employee = $this->employeeProfileService->loadProfile($employee->fresh());

        return $this->success(
            [
                'employee' => new EmployeeProfileResource($employee),
                'personal_section' => new EmployeePersonalSectionResource($result['personal_section']),
            ],
            ! empty($result['auto_approved'])
                ? 'Personal section saved successfully.'
                : ($result['is_change']
                    ? 'Personal section updated successfully. Changes are pending HR approval.'
                    : ($result['is_resubmit']
                        ? 'Personal section re-submitted successfully. It is pending approval again.'
                        : 'Personal section submitted successfully. It is pending approval.')),
            201
        );
    }

    public function storePaymentMethod(StoreEmployeePaymentMethodRequest $request): JsonResponse
    {
        $employee = $this->employeeProfileService->resolveEmployee($request->user());
        $result = $this->employeeProfileService->submitPaymentMethod(
            $request->user(),
            $employee,
            $request->validated()
        );

        $employee = $this->employeeProfileService->loadProfile($employee->fresh());

        return $this->success(
            [
                'employee' => new EmployeeProfileResource($employee),
                'payment_method' => new EmployeePaymentMethodResource($result['payment_method']),
            ],
            ! empty($result['auto_approved'])
                ? 'Payment option saved successfully.'
                : ($result['is_change']
                    ? 'Payment option updated successfully. Changes are pending HR approval.'
                    : ($result['is_resubmit']
                        ? 'Payment option re-submitted successfully. It is pending approval again.'
                        : 'Payment option submitted successfully. It is pending approval.')),
            201
        );
    }

    public function storeComplianceField(StoreEmployeeComplianceFieldRequest $request): JsonResponse
    {
        $employee = $this->employeeProfileService->resolveEmployee($request->user());
        $result = $this->employeeProfileService->submitComplianceField(
            $request->user(),
            $employee,
            $request->validated()
        );

        $employee = $this->employeeProfileService->loadProfile($employee->fresh());

        return $this->success(
            [
                'employee' => new EmployeeProfileResource($employee),
                'compliance_field' => new EmployeeComplianceFieldResource($result['compliance_field']),
            ],
            ! empty($result['auto_approved'])
                ? 'Compliance field saved successfully.'
                : ($result['is_change']
                    ? 'Compliance field updated successfully. Changes are pending HR approval.'
                    : ($result['is_resubmit']
                        ? 'Compliance field re-submitted successfully. It is pending approval again.'
                        : 'Compliance field submitted successfully. It is pending approval.')),
            201
        );
    }

    public function storeDocument(StoreEmployeeDocumentRequest $request): JsonResponse
    {
        $employee = $this->employeeProfileService->resolveEmployee($request->user());
        $result = $this->employeeProfileService->storeDocument(
            $request->user(),
            $employee,
            (int) $request->input('document_type_id'),
            $request->uploadedFiles(),
        );

        return $this->success(
            [
                'document' => new EmployeeDocumentResource($result['document']),
                'documents' => EmployeeDocumentResource::collection(collect($result['documents'])),
            ],
            $this->documentUploadMessage($result),
            201
        );
    }

    private function documentUploadMessage(array $result): string
    {
        $count = (int) ($result['count'] ?? 1);
        $multiple = $count > 1;

        if (! empty($result['auto_approved'])) {
            if ($multiple) {
                return "{$count} documents uploaded successfully.";
            }

            return $result['is_reupload']
                ? 'Document updated successfully.'
                : 'Document uploaded successfully.';
        }

        if ($multiple) {
            return "{$count} documents uploaded successfully. They are pending approval.";
        }

        return $result['is_reupload']
            ? 'Document re-uploaded successfully. It is pending approval again.'
            : 'Document uploaded successfully. It is pending approval.';
    }

    public function downloadDocument(Request $request, EmployeeDocument $employeeDocument): BinaryFileResponse
    {
        $employee = $this->employeeProfileService->resolveEmployee($request->user());
        $file = $this->employeeProfileService->downloadDocument($request->user(), $employee, $employeeDocument);

        return $this->inlineFileResponse($file);
    }

    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (! $user->canUpdateEmployeeContactInfo() && array_key_exists('email', $validated)) {
            unset($validated['email']);
        }

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return $this->success(
            ['user' => new UserResource($user->load(['company', 'role']))],
            'Profile updated successfully.'
        );
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => $validated['password'],
        ]);

        return $this->success(null, 'Password updated successfully.');
    }
}
