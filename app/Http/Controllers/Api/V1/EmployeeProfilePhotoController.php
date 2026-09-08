<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ValidatesReviewNotes;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\StreamsInlineFiles;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\RejectEmployeeDocumentRequest;
use App\Http\Resources\EmployeeProfilePhotoResource;
use App\Models\EmployeeProfilePhoto;
use App\Services\EmployeeProfilePhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeProfilePhotoController extends Controller
{
    use ApiResponse, StreamsInlineFiles, ValidatesReviewNotes;

    public function __construct(private EmployeeProfilePhotoService $profilePhotoService) {}

    public function pending(Request $request): JsonResponse
    {
        if (! $request->user()->canReviewEmployeeDocuments()) {
            return $this->error('You are not allowed to review profile photos.', null, 403);
        }

        $photos = $this->profilePhotoService->pendingForReviewer($request->user());

        return $this->success([
            'profile_photos' => EmployeeProfilePhotoResource::collection($photos),
        ]);
    }

    public function download(Request $request, EmployeeProfilePhoto $employeeProfilePhoto): BinaryFileResponse
    {
        $file = $this->profilePhotoService->downloadForUser($request->user(), $employeeProfilePhoto);

        return $this->inlineFileResponse($file);
    }

    public function approve(Request $request, EmployeeProfilePhoto $employeeProfilePhoto): JsonResponse
    {
        $this->profilePhotoService->assertBelongsToCompany($request->user(), $employeeProfilePhoto);
        $photo = $this->profilePhotoService->approve(
            $request->user(),
            $employeeProfilePhoto,
            $this->optionalReviewNotes($request),
        );

        return $this->success(
            ['profile_photo' => new EmployeeProfilePhotoResource($photo)],
            'Profile photo approved for face recognition use.'
        );
    }

    public function reject(RejectEmployeeDocumentRequest $request, EmployeeProfilePhoto $employeeProfilePhoto): JsonResponse
    {
        $this->profilePhotoService->assertBelongsToCompany($request->user(), $employeeProfilePhoto);
        $photo = $this->profilePhotoService->reject(
            $request->user(),
            $employeeProfilePhoto,
            $request->validated('notes')
        );

        return $this->success(
            ['profile_photo' => new EmployeeProfilePhotoResource($photo)],
            'Profile photo rejected. The employee can upload a new photo after reviewing your feedback.'
        );
    }
}
