<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ValidatesReviewNotes;
use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\RejectEmployeeDocumentRequest;
use App\Http\Resources\EmployeeFamilyMemberResource;
use App\Models\EmployeeFamilyMember;
use App\Services\EmployeeFamilyMemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeFamilyMemberController extends Controller
{
    use ApiResponse, ValidatesReviewNotes;

    public function __construct(private EmployeeFamilyMemberService $familyMemberService) {}

    public function pending(Request $request): JsonResponse
    {
        if (! $request->user()->canReviewEmployeeDocuments()) {
            return $this->error('You are not allowed to review family members.', null, 403);
        }

        $members = $this->familyMemberService->pendingForReviewer($request->user());

        return $this->success([
            'family_members' => EmployeeFamilyMemberResource::collection($members),
        ]);
    }

    public function approve(Request $request, EmployeeFamilyMember $employeeFamilyMember): JsonResponse
    {
        $this->familyMemberService->assertBelongsToCompany($request->user(), $employeeFamilyMember);
        $member = $this->familyMemberService->approve(
            $request->user(),
            $employeeFamilyMember,
            $this->optionalReviewNotes($request),
        );

        return $this->success(
            ['family_member' => new EmployeeFamilyMemberResource($member)],
            'Family member approved successfully.'
        );
    }

    public function reject(RejectEmployeeDocumentRequest $request, EmployeeFamilyMember $employeeFamilyMember): JsonResponse
    {
        $this->familyMemberService->assertBelongsToCompany($request->user(), $employeeFamilyMember);
        $member = $this->familyMemberService->reject(
            $request->user(),
            $employeeFamilyMember,
            $request->validated('notes')
        );

        return $this->success(
            ['family_member' => new EmployeeFamilyMemberResource($member)],
            'Family member rejected. The employee can re-submit after reviewing your feedback.'
        );
    }
}
