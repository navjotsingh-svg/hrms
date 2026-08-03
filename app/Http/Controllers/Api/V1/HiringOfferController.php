<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Models\HiringOffer;
use App\Models\HiringTemplate;
use App\Services\CandidateOfferService;
use App\Services\HiringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class HiringOfferController extends Controller
{
    use ApiResponse;

    public function __construct(
        private HiringService $hiringService,
        private CandidateOfferService $candidateOfferService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'sent', 'accepted', 'declined', 'withdrawn'])],
            'per_page' => ['nullable', 'integer', Rule::in([5, 10, 25, 50])],
        ]);

        $paginator = $this->hiringService->listOffers($request->user(), $validated);

        return $this->success([
            'offers' => collect($paginator->items())->map(fn (HiringOffer $o) => $this->hiringService->formatOffer($o))->values(),
            'pagination' => $this->paginationMeta($paginator),
        ]);
    }

    public function show(Request $request, HiringOffer $hiringOffer): JsonResponse
    {
        $offer = $this->hiringService->resolveOffer($request->user(), $hiringOffer);

        return $this->success(['offer' => $this->hiringService->formatOffer($offer)]);
    }

    public function pdf(Request $request, HiringOffer $hiringOffer): Response
    {
        $offer = $this->hiringService->resolveOffer($request->user(), $hiringOffer);
        $file = $this->candidateOfferService->pdfContents($offer);

        return response($file['binary'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$file['filename'].'"',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'candidate_id' => ['required', 'integer', 'exists:candidates,id'],
            'job_id' => ['nullable', 'integer', 'exists:job_postings,id'],
            'template_id' => ['required', 'integer', 'exists:hiring_templates,id'],
            'title' => ['required', 'string', 'max:255'],
            'offered_ctc' => ['nullable', 'numeric', 'min:0'],
            'joining_date' => ['nullable', 'date'],
        ]);

        $offer = $this->hiringService->storeOffer($request->user(), $validated);

        return $this->success(['offer' => $this->hiringService->formatOffer($offer)], 'Offer created.', 201);
    }

    public function send(Request $request, HiringOffer $hiringOffer): JsonResponse
    {
        $offer = $this->hiringService->sendOffer($request->user(), $hiringOffer);

        return $this->success(['offer' => $this->hiringService->formatOffer($offer)], 'Offer email with PDF sent to candidate.');
    }

    public function templates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', 'max:30'],
            'per_page' => ['nullable', 'integer', Rule::in([5, 10, 25, 50])],
        ]);

        $paginator = $this->hiringService->listTemplates($request->user(), $validated);

        return $this->success([
            'templates' => collect($paginator->items())->map(fn (HiringTemplate $t) => $this->formatTemplate($t))->values(),
            'pagination' => $this->paginationMeta($paginator),
        ]);
    }

    public function templatesMeta(): JsonResponse
    {
        return $this->success([
            'placeholders' => config('hiring.placeholders', []),
            'sample_templates' => config('hiring.sample_templates', []),
            'types' => config('hiring.template_types', []),
        ]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string'],
            'body_html' => ['nullable', 'string'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $template = $this->hiringService->storeTemplate($request->user(), $validated);

        return $this->success(['template' => $this->formatTemplate($template)], 'Template created.', 201);
    }

    public function updateTemplate(Request $request, HiringTemplate $hiringTemplate): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string'],
            'body_html' => ['nullable', 'string'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $template = $this->hiringService->updateTemplate($request->user(), $hiringTemplate, $validated);

        return $this->success(['template' => $this->formatTemplate($template)], 'Template updated.');
    }

    private function formatTemplate(HiringTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'type' => $template->type,
            'description' => $template->description,
            'body_html' => $template->body_html,
            'is_default' => $template->is_default,
        ];
    }

    private function paginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }
}
