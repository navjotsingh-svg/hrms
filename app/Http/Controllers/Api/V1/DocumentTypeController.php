<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\StoreDocumentTypeRequest;
use App\Http\Requests\UpdateDocumentTypeRequest;
use App\Http\Resources\DocumentTypeResource;
use App\Models\DocumentType;
use App\Services\DocumentTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentTypeController extends Controller
{
    use ApiResponse;

    public function __construct(private DocumentTypeService $documentTypeService) {}

    public function index(Request $request): JsonResponse
    {
        $documentTypes = $this->documentTypeService->listForCompany(
            $request->user()->company_id,
            $request->only(['search', 'status', 'per_page'])
        );

        return $this->success([
            'document_types' => DocumentTypeResource::collection($documentTypes->items()),
            'pagination' => [
                'current_page' => $documentTypes->currentPage(),
                'last_page' => $documentTypes->lastPage(),
                'per_page' => $documentTypes->perPage(),
                'total' => $documentTypes->total(),
                'from' => $documentTypes->firstItem(),
                'to' => $documentTypes->lastItem(),
            ],
        ]);
    }

    public function store(StoreDocumentTypeRequest $request): JsonResponse
    {
        $documentType = $this->documentTypeService->create(
            $request->user()->company_id,
            $request->validated()
        );

        return $this->success(
            ['document_type' => new DocumentTypeResource($documentType)],
            'Document type created successfully.',
            201
        );
    }

    public function show(Request $request, DocumentType $documentType): JsonResponse
    {
        $this->ensureCompanyDocumentType($request, $documentType);

        return $this->success([
            'document_type' => new DocumentTypeResource($documentType),
        ]);
    }

    public function update(UpdateDocumentTypeRequest $request, DocumentType $documentType): JsonResponse
    {
        $this->ensureCompanyDocumentType($request, $documentType);

        $documentType = $this->documentTypeService->update($documentType, $request->validated());

        return $this->success(
            ['document_type' => new DocumentTypeResource($documentType)],
            'Document type updated successfully.'
        );
    }

    public function destroy(Request $request, DocumentType $documentType): JsonResponse
    {
        $this->ensureCompanyDocumentType($request, $documentType);

        $this->documentTypeService->delete($documentType);

        return $this->success(null, 'Document type deleted successfully.');
    }

    public function updateStatus(Request $request, DocumentType $documentType): JsonResponse
    {
        $this->ensureCompanyDocumentType($request, $documentType);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $documentType = $this->documentTypeService->update($documentType, $validated);

        return $this->success(
            ['document_type' => new DocumentTypeResource($documentType)],
            'Document type status updated successfully.'
        );
    }

    private function ensureCompanyDocumentType(Request $request, DocumentType $documentType): void
    {
        if (! $this->documentTypeService->belongsToCompany($documentType, $request->user()->company_id)) {
            abort(404);
        }
    }
}
