<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Concerns\StreamsInlineFiles;
use App\Http\Requests\RejectEmployeeDocumentRequest;
use App\Http\Resources\EmployeeDocumentResource;
use App\Models\EmployeeDocument;
use App\Services\EmployeeDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeDocumentController extends Controller
{
    use ApiResponse, StreamsInlineFiles;

    public function __construct(private EmployeeDocumentService $employeeDocumentService) {}

    public function pending(Request $request): JsonResponse
    {
        if (! $request->user()->canReviewEmployeeDocuments()) {
            return $this->error('You are not allowed to review documents.', null, 403);
        }

        $documents = $this->employeeDocumentService->pendingForReviewer($request->user());

        return $this->success([
            'documents' => EmployeeDocumentResource::collection($documents),
        ]);
    }

    public function approve(Request $request, EmployeeDocument $employeeDocument): JsonResponse
    {
        $this->employeeDocumentService->assertBelongsToCompany($request->user(), $employeeDocument);
        $document = $this->employeeDocumentService->approve($request->user(), $employeeDocument);

        return $this->success(
            ['document' => new EmployeeDocumentResource($document)],
            'Document approved successfully.'
        );
    }

    public function reject(RejectEmployeeDocumentRequest $request, EmployeeDocument $employeeDocument): JsonResponse
    {
        $this->employeeDocumentService->assertBelongsToCompany($request->user(), $employeeDocument);
        $document = $this->employeeDocumentService->reject(
            $request->user(),
            $employeeDocument,
            $request->validated('notes')
        );

        return $this->success(
            ['document' => new EmployeeDocumentResource($document)],
            'Document rejected. The employee can re-upload after reviewing your feedback.'
        );
    }

    public function destroy(Request $request, EmployeeDocument $employeeDocument): JsonResponse
    {
        $this->employeeDocumentService->assertBelongsToCompany($request->user(), $employeeDocument);
        $this->employeeDocumentService->delete($request->user(), $employeeDocument);

        return $this->success(null, 'Document deleted successfully.');
    }

    public function download(Request $request, EmployeeDocument $employeeDocument): BinaryFileResponse
    {
        $file = $this->employeeDocumentService->downloadForUser($request->user(), $employeeDocument);

        return $this->inlineFileResponse($file);
    }
}
