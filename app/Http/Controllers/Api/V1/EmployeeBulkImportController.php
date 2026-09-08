<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Models\BulkImport;
use App\Services\EmployeeBulkImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeBulkImportController extends Controller
{
    use ApiResponse;

    public function __construct(private EmployeeBulkImportService $bulkImportService) {}

    public function fields(Request $request): JsonResponse
    {
        return $this->success([
            'fields' => \App\Services\EmployeeBulkImportFieldCatalog::fields(),
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ]);

        $import = $this->bulkImportService->upload($request->user(), $validated['file']);

        return $this->success(
            $this->bulkImportService->mappingPayload($import),
            'File uploaded. Map columns before importing.',
            201,
        );
    }

    public function show(Request $request, BulkImport $bulkImport): JsonResponse
    {
        $import = $this->bulkImportService->resolveForUser($request->user(), $bulkImport);

        if ($import->status === BulkImport::STATUS_MAPPING) {
            return $this->success($this->bulkImportService->mappingPayload($import));
        }

        return $this->success($this->bulkImportService->resultPayload($import));
    }

    public function confirm(Request $request, BulkImport $bulkImport): JsonResponse
    {
        $validated = $request->validate([
            'mapping' => ['required', 'array'],
            'mapping.*' => ['required', 'string', 'max:100'],
        ]);

        $import = $this->bulkImportService->confirm(
            $request->user(),
            $bulkImport,
            $validated['mapping'],
        );

        return $this->success(
            $this->bulkImportService->resultPayload($import),
            $import->summary_message ?? 'Import completed.',
        );
    }
}
