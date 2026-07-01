<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentLetterTemplateResource;
use App\Models\DocumentLetterTemplate;
use App\Services\DocumentLetterTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentLetterTemplateController extends Controller
{
    use ApiResponse;

    public function __construct(private DocumentLetterTemplateService $templateService) {}

    public function meta(): JsonResponse
    {
        return $this->success([
            'categories' => collect(config('document_letters.categories', []))->map(fn ($label, $value) => [
                'value' => $value,
                'label' => $label,
            ])->values(),
            'placeholders' => config('document_letters.placeholders', []),
            'sample_templates' => config('document_letters.sample_templates', []),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['nullable', Rule::in(array_keys(config('document_letters.categories', [])))],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $templates = $this->templateService->listForCompany($request->user()->company_id, $validated);

        return $this->success([
            'templates' => DocumentLetterTemplateResource::collection($templates->items()),
            'pagination' => [
                'current_page' => $templates->currentPage(),
                'last_page' => $templates->lastPage(),
                'per_page' => $templates->perPage(),
                'total' => $templates->total(),
                'from' => $templates->firstItem(),
                'to' => $templates->lastItem(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(config('document_letters.categories', [])))],
            'subject' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'body_html' => ['required', 'string'],
            'requires_signature' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $template = $this->templateService->create($request->user(), $validated);

        return $this->success([
            'template' => new DocumentLetterTemplateResource($template),
        ], 'Template created.', 201);
    }

    public function show(Request $request, DocumentLetterTemplate $document_letter_template): JsonResponse
    {
        if ((int) $document_letter_template->company_id !== (int) $request->user()->company_id) {
            abort(404);
        }

        return $this->success([
            'template' => new DocumentLetterTemplateResource($document_letter_template),
        ]);
    }

    public function update(Request $request, DocumentLetterTemplate $document_letter_template): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', Rule::in(array_keys(config('document_letters.categories', [])))],
            'subject' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'body_html' => ['sometimes', 'string'],
            'requires_signature' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $template = $this->templateService->update($request->user(), $document_letter_template, $validated);

        return $this->success([
            'template' => new DocumentLetterTemplateResource($template),
        ], 'Template updated.');
    }

    public function preview(Request $request, DocumentLetterTemplate $document_letter_template): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'salary' => ['nullable', 'string', 'max:100'],
            'joining_date' => ['nullable', 'string', 'max:100'],
        ]);

        $preview = $this->templateService->preview(
            $request->user(),
            $document_letter_template,
            $validated['employee_id'] ?? null,
            $validated,
        );

        return $this->success(['preview' => $preview]);
    }
}
