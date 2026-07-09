<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Concerns\StreamsInlineFiles;
use App\Http\Requests\StoreEmployeeDocumentRequest;
use App\Http\Requests\StoreEmployeeComplianceFieldRequest;
use App\Http\Requests\StoreEmployeeFamilyMemberRequest;
use App\Http\Requests\StoreEmployeePersonalSectionRequest;
use App\Http\Requests\StoreEmployeePaymentMethodRequest;
use App\Http\Requests\StoreEmployeeProfilePhotoRequest;
use App\Http\Requests\StoreEmployeeAssetsRequest;
use App\Http\Requests\StoreEmployeeSalaryRequest;
use App\Http\Resources\EmployeeAssetResource;
use App\Http\Resources\EmployeeFamilyMemberResource;
use App\Http\Resources\EmployeePersonalSectionResource;
use App\Http\Resources\EmployeeComplianceFieldResource;
use App\Http\Resources\EmployeeDocumentResource;
use App\Http\Resources\EmployeePaymentMethodResource;
use App\Http\Resources\EmployeeProfilePhotoResource;
use App\Http\Resources\EmployeeProfileResource;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeePaymentMethodProof;
use App\Services\EmployeeProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeProfileController extends Controller
{
    use ApiResponse, StreamsInlineFiles;

    public function __construct(private EmployeeProfileService $employeeProfileService) {}

    public function storeFamilyMembers(StoreEmployeeFamilyMemberRequest $request, Employee $employee): JsonResponse
    {
        $employee = $this->employeeProfileService->resolveEmployeeForActor($request->user(), $employee);
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

    public function storePersonalSection(StoreEmployeePersonalSectionRequest $request, Employee $employee): JsonResponse
    {
        $employee = $this->employeeProfileService->resolveEmployeeForActor($request->user(), $employee);
        $result = $this->employeeProfileService->submitPersonalSection(
            $request->user(),
            $employee,
            $request->validated()
        );

        $employee = $this->employeeProfileService->loadProfile($employee->fresh());

        return $this->success(
            [
                'employee' => new EmployeeProfileResource($employee),
                'personal_section' => new EmployeePersonalSectionResource($result['personal_section']),
            ],
            $this->personalSectionMessage($result),
            201
        );
    }

    public function updateSalary(StoreEmployeeSalaryRequest $request, Employee $employee): JsonResponse
    {
        $employee = $this->employeeProfileService->updateSalary(
            $request->user(),
            $employee,
            $request->validated()
        );

        $message = match ($request->input('salary_action')) {
            'increment' => 'Salary updated successfully.',
            'revise' => 'Salary revised successfully.',
            default => 'Salary saved successfully.',
        };

        return $this->success(
            ['employee' => new EmployeeProfileResource($employee)],
            $message
        );
    }

    public function updateAssets(StoreEmployeeAssetsRequest $request, Employee $employee): JsonResponse
    {
        $employee = $this->employeeProfileService->updateAssets(
            $request->user(),
            $employee,
            $request->validated()['assets']
        );

        return $this->success(
            [
                'employee' => new EmployeeProfileResource($employee),
                'assets' => EmployeeAssetResource::collection(
                    $this->employeeProfileService->assetsForEmployee($employee)
                ),
            ],
            'Assigned assets updated successfully.'
        );
    }

    public function storePaymentMethod(StoreEmployeePaymentMethodRequest $request, Employee $employee): JsonResponse
    {
        $employee = $this->employeeProfileService->resolveEmployeeForActor($request->user(), $employee);
        $result = $this->employeeProfileService->submitPaymentMethod(
            $request->user(),
            $employee,
            $request->validated(),
            $request->proofFiles(),
        );

        $employee = $this->employeeProfileService->loadProfile($employee->fresh());

        return $this->success(
            [
                'employee' => new EmployeeProfileResource($employee),
                'payment_method' => new EmployeePaymentMethodResource($result['payment_method']),
            ],
            $this->paymentMethodMessage($result),
            201
        );
    }

    public function storeProfilePhoto(StoreEmployeeProfilePhotoRequest $request, Employee $employee): JsonResponse
    {
        $employee = $this->employeeProfileService->resolveEmployeeForActor($request->user(), $employee);
        $result = $this->employeeProfileService->submitProfilePhoto(
            $request->user(),
            $employee,
            $request->file('photo'),
        );

        $employee = $this->employeeProfileService->loadProfile($employee->fresh());

        return $this->success(
            [
                'employee' => new EmployeeProfileResource($employee),
                'profile_photo' => new EmployeeProfilePhotoResource($result['profile_photo']),
            ],
            ! empty($result['auto_approved'])
                ? 'Profile photo saved successfully.'
                : 'Profile photo submitted. Pending for approval from management.',
            201
        );
    }

    public function storeComplianceField(StoreEmployeeComplianceFieldRequest $request, Employee $employee): JsonResponse
    {
        $employee = $this->employeeProfileService->resolveEmployeeForActor($request->user(), $employee);
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
            $this->complianceFieldMessage($result),
            201
        );
    }

    public function storeDocument(StoreEmployeeDocumentRequest $request, Employee $employee): JsonResponse
    {
        $employee = $this->employeeProfileService->resolveEmployeeForActor($request->user(), $employee);
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

    public function downloadDocument(Request $request, Employee $employee, EmployeeDocument $employeeDocument): BinaryFileResponse
    {
        $employee = $this->employeeProfileService->resolveEmployeeForActor($request->user(), $employee);
        $file = $this->employeeProfileService->downloadDocument($request->user(), $employee, $employeeDocument);

        return $this->inlineFileResponse($file);
    }

    public function downloadPaymentMethodProof(
        Request $request,
        Employee $employee,
        EmployeePaymentMethodProof $employeePaymentMethodProof,
    ): BinaryFileResponse {
        $employee = $this->employeeProfileService->resolveEmployeeForActor($request->user(), $employee);
        $file = $this->employeeProfileService->downloadPaymentMethodProof(
            $request->user(),
            $employee,
            $employeePaymentMethodProof,
        );

        return $this->inlineFileResponse($file);
    }

    private function personalSectionMessage(array $result): string
    {
        if (! empty($result['auto_approved'])) {
            return 'Personal section saved successfully.';
        }

        return $result['is_change']
            ? 'Personal section updated successfully. Changes are pending HR approval.'
            : ($result['is_resubmit']
                ? 'Personal section re-submitted successfully. It is pending approval again.'
                : 'Personal section submitted successfully. It is pending approval.');
    }

    private function paymentMethodMessage(array $result): string
    {
        if (! empty($result['auto_approved'])) {
            return 'Payment option saved successfully.';
        }

        return $result['is_change']
            ? 'Payment option updated successfully. Changes are pending HR approval.'
            : ($result['is_resubmit']
                ? 'Payment option re-submitted successfully. It is pending approval again.'
                : 'Payment option submitted successfully. It is pending approval.');
    }

    private function complianceFieldMessage(array $result): string
    {
        if (! empty($result['auto_approved'])) {
            return 'Compliance field saved successfully.';
        }

        return $result['is_change']
            ? 'Compliance field updated successfully. Changes are pending HR approval.'
            : ($result['is_resubmit']
                ? 'Compliance field re-submitted successfully. It is pending approval again.'
                : 'Compliance field submitted successfully. It is pending approval.');
    }
}
