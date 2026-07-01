<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentLetterResource;
use App\Models\DocumentLetter;
use App\Services\DocumentLetterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentLetterController extends Controller
{
    use ApiResponse;

    public function __construct(private DocumentLetterService $documentLetterService) {}

    public function summary(Request $request): JsonResponse
    {
        return $this->success([
            'pending_signature_count' => $this->documentLetterService->pendingSignatureCount($request->user()),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'status' => ['nullable', Rule::in(array_keys(config('document_letters.statuses', [])))],
            'category' => ['nullable', Rule::in(array_keys(config('document_letters.categories', [])))],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $letters = $this->documentLetterService->listForUser($request->user(), $validated);

        return $this->success([
            'letters' => DocumentLetterResource::collection($letters->items()),
            'pagination' => [
                'current_page' => $letters->currentPage(),
                'last_page' => $letters->lastPage(),
                'per_page' => $letters->perPage(),
                'total' => $letters->total(),
                'from' => $letters->firstItem(),
                'to' => $letters->lastItem(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'template_id' => ['nullable', 'integer', 'exists:document_letter_templates,id'],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', Rule::in(array_keys(config('document_letters.categories', [])))],
            'body_html' => ['nullable', 'string'],
            'requires_signature' => ['nullable', 'boolean'],
            'salary' => ['nullable', 'string', 'max:100'],
            'joining_date' => ['nullable', 'string', 'max:100'],
            'issue_now' => ['nullable', 'boolean'],
        ]);

        $letter = $this->documentLetterService->createDraft($request->user(), $validated);

        if ($request->boolean('issue_now')) {
            $letter = $this->documentLetterService->issue($request->user(), $letter);
        }

        return $this->success([
            'letter' => new DocumentLetterResource($letter->load(['employee', 'template', 'issuedBy'])),
        ], $request->boolean('issue_now') ? 'Document issued to employee.' : 'Document draft created.', 201);
    }

    public function show(Request $request, DocumentLetter $document_letter): JsonResponse
    {
        $letter = $this->documentLetterService->showForUser($request->user(), $document_letter);

        return $this->success([
            'letter' => new DocumentLetterResource($letter),
        ]);
    }

    public function issue(Request $request, DocumentLetter $document_letter): JsonResponse
    {
        $letter = $this->documentLetterService->issue($request->user(), $document_letter);

        return $this->success([
            'letter' => new DocumentLetterResource($letter),
        ], 'Document issued to employee.');
    }

    public function sign(Request $request, DocumentLetter $document_letter): JsonResponse
    {
        $validated = $request->validate([
            'signature_name' => ['required', 'string', 'max:255'],
            'signature_data_url' => ['nullable', 'string'],
            'signature_image' => ['nullable', 'image', 'max:2048'],
        ]);

        $letter = $this->documentLetterService->sign(
            $request->user(),
            $document_letter,
            $validated,
            $request->file('signature_image'),
        );

        return $this->success([
            'letter' => new DocumentLetterResource($letter),
        ], 'Document signed successfully.');
    }

    public function decline(Request $request, DocumentLetter $document_letter): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $letter = $this->documentLetterService->decline($request->user(), $document_letter, $validated['reason']);

        return $this->success([
            'letter' => new DocumentLetterResource($letter),
        ], 'Document declined.');
    }

    public function cancel(Request $request, DocumentLetter $document_letter): JsonResponse
    {
        $letter = $this->documentLetterService->cancel($request->user(), $document_letter);

        return $this->success([
            'letter' => new DocumentLetterResource($letter),
        ], 'Document cancelled.');
    }
}
