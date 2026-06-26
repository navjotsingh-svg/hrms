<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ValidatesReviewNotes;
use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\RejectEmployeeDocumentRequest;
use App\Http\Resources\EmployeePersonalSectionResource;
use App\Models\EmployeePersonalSection;
use App\Services\EmployeePersonalSectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeePersonalSectionController extends Controller
{
    use ApiResponse, ValidatesReviewNotes;

    public function __construct(private EmployeePersonalSectionService $personalSectionService) {}

    public function pending(Request $request): JsonResponse
    {
        if (! $request->user()->canReviewEmployeeDocuments()) {
            return $this->error('You are not allowed to review personal sections.', null, 403);
        }

        $sections = $this->personalSectionService->pendingForReviewer($request->user());

        return $this->success([
            'personal_sections' => EmployeePersonalSectionResource::collection($sections),
        ]);
    }

    public function approve(Request $request, EmployeePersonalSection $employeePersonalSection): JsonResponse
    {
        $this->personalSectionService->assertBelongsToCompany($request->user(), $employeePersonalSection);
        $section = $this->personalSectionService->approve(
            $request->user(),
            $employeePersonalSection,
            $this->optionalReviewNotes($request),
        );

        return $this->success(
            ['personal_section' => new EmployeePersonalSectionResource($section)],
            'Personal section approved successfully.'
        );
    }

    public function reject(RejectEmployeeDocumentRequest $request, EmployeePersonalSection $employeePersonalSection): JsonResponse
    {
        $this->personalSectionService->assertBelongsToCompany($request->user(), $employeePersonalSection);
        $section = $this->personalSectionService->reject(
            $request->user(),
            $employeePersonalSection,
            $request->validated('notes')
        );

        return $this->success(
            ['personal_section' => new EmployeePersonalSectionResource($section)],
            'Personal section rejected. The employee can re-submit after reviewing your feedback.'
        );
    }
}
