<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ValidatesReviewNotes;
use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\RejectEmployeeDocumentRequest;
use App\Http\Resources\EmployeeComplianceFieldResource;
use App\Models\EmployeeComplianceField;
use App\Services\EmployeeComplianceFieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeComplianceController extends Controller
{
    use ApiResponse, ValidatesReviewNotes;

    public function __construct(private EmployeeComplianceFieldService $complianceFieldService) {}

    public function pending(Request $request): JsonResponse
    {
        if (! $request->user()->canReviewEmployeeDocuments()) {
            return $this->error('You are not allowed to review compliance fields.', null, 403);
        }

        $fields = $this->complianceFieldService->pendingForReviewer($request->user());

        return $this->success([
            'compliance_fields' => EmployeeComplianceFieldResource::collection($fields),
        ]);
    }

    public function approve(Request $request, EmployeeComplianceField $employeeComplianceField): JsonResponse
    {
        $this->complianceFieldService->assertBelongsToCompany($request->user(), $employeeComplianceField);
        $field = $this->complianceFieldService->approve(
            $request->user(),
            $employeeComplianceField,
            $this->optionalReviewNotes($request),
        );

        return $this->success(
            ['compliance_field' => new EmployeeComplianceFieldResource($field)],
            'Compliance field approved successfully.'
        );
    }

    public function reject(RejectEmployeeDocumentRequest $request, EmployeeComplianceField $employeeComplianceField): JsonResponse
    {
        $this->complianceFieldService->assertBelongsToCompany($request->user(), $employeeComplianceField);
        $field = $this->complianceFieldService->reject(
            $request->user(),
            $employeeComplianceField,
            $request->validated('notes')
        );

        return $this->success(
            ['compliance_field' => new EmployeeComplianceFieldResource($field)],
            'Compliance field rejected. The employee can re-submit after reviewing your feedback.'
        );
    }
}
