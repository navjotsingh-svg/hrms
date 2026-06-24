<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Models\HiringOffer;
use App\Models\HiringTemplate;
use App\Services\HiringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HiringOfferController extends Controller
{
    use ApiResponse;

    public function __construct(private HiringService $hiringService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'sent', 'accepted', 'declined', 'withdrawn'])],
            'per_page' => ['nullable', 'integer', Rule::in([5, 10, 25, 50])],
        ]);

        $paginator = $this->hiringService->listOffers($request->user(), $validated);

        return $this->success([
            'offers' => collect($paginator->items())->map(fn (HiringOffer $o) => $this->formatOffer($o))->values(),
            'pagination' => $this->paginationMeta($paginator),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'candidate_id' => ['required', 'integer', 'exists:candidates,id'],
            'job_id' => ['nullable', 'integer', 'exists:job_postings,id'],
            'template_id' => ['nullable', 'integer', 'exists:hiring_templates,id'],
            'title' => ['required', 'string', 'max:255'],
            'offered_ctc' => ['nullable', 'numeric', 'min:0'],
            'joining_date' => ['nullable', 'date'],
            'letter_html' => ['nullable', 'string'],
        ]);

        $offer = $this->hiringService->storeOffer($request->user(), $validated);

        return $this->success(['offer' => $this->formatOffer($offer)], 'Offer created.', 201);
    }

    public function send(Request $request, HiringOffer $hiringOffer): JsonResponse
    {
        $offer = $this->hiringService->sendOffer($request->user(), $hiringOffer);

        return $this->success(['offer' => $this->formatOffer($offer)], 'Offer sent.');
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

    private function formatOffer(HiringOffer $offer): array
    {
        $offer->loadMissing(['candidate', 'job', 'template']);

        return [
            'id' => $offer->id,
            'title' => $offer->title,
            'offered_ctc' => $offer->offered_ctc,
            'joining_date' => $offer->joining_date?->format('Y-m-d'),
            'letter_html' => $offer->letter_html,
            'status' => $offer->status,
            'sent_at' => $offer->sent_at?->toIso8601String(),
            'candidate' => $offer->candidate ? [
                'id' => $offer->candidate->id,
                'full_name' => trim($offer->candidate->first_name.' '.$offer->candidate->last_name),
            ] : null,
            'job' => $offer->job ? ['id' => $offer->job->id, 'title' => $offer->job->title] : null,
            'template' => $offer->template ? ['id' => $offer->template->id, 'name' => $offer->template->name] : null,
        ];
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
