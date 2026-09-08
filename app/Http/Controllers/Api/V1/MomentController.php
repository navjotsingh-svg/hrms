<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Models\CompanyMomentReaction;
use App\Services\MomentService;
use App\Services\MomentTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MomentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private MomentService $momentService,
        private MomentTemplateService $templateService,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        return $this->success($this->momentService->summaryForUser($request->user()));
    }

    public function markSeen(Request $request): JsonResponse
    {
        return $this->success($this->momentService->markFeedSeen($request->user()));
    }

    public function updateTemplates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'birthday_template' => ['required', 'string', 'max:2000'],
            'work_anniversary_template' => ['required', 'string', 'max:2000'],
            'new_joinee_template' => ['required', 'string', 'max:2000'],
        ]);

        $template = $this->templateService->updateForCompany($request->user(), $validated);

        return $this->success([
            'templates' => $this->templateService->toArray($template),
        ], 'Moment templates saved.');
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', Rule::in([
                'post',
                'birthday',
                'work_anniversary',
                'new_joinee',
            ])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return $this->success($this->momentService->feedForUser(
            $request->user(),
            array_filter([
                'type' => $validated['type'] ?? null,
            ]),
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 15),
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', Rule::in([
                'post',
                'birthday',
                'work_anniversary',
                'new_joinee',
            ])],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'content' => ['nullable', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,gif,webp,pdf'],
        ]);

        $type = $validated['type'] ?? 'post';

        $moment = $this->momentService->createPost(
            $request->user(),
            $type,
            trim((string) ($validated['content'] ?? '')),
            $request->file('attachments', []) ?? [],
            isset($validated['employee_id']) ? (int) $validated['employee_id'] : null,
        );

        $label = match ($type) {
            'birthday' => 'Birthday moment published.',
            'work_anniversary' => 'Anniversary moment published.',
            'new_joinee' => 'Welcome moment published.',
            default => 'Post published.',
        };

        return $this->success(['moment' => $moment], $label, 201);
    }

    public function react(Request $request, int $moment): JsonResponse
    {
        $validated = $request->validate([
            'reaction' => ['nullable', Rule::in(CompanyMomentReaction::REACTIONS)],
        ]);

        $updated = $this->momentService->toggleReaction(
            $request->user(),
            $moment,
            $validated['reaction'] ?? null,
        );

        return $this->success(['moment' => $updated]);
    }

    public function comments(Request $request, int $moment): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return $this->success($this->momentService->commentsForMoment(
            $request->user(),
            $moment,
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 20),
        ));
    }

    public function storeComment(Request $request, int $moment): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
        ]);

        $comment = $this->momentService->addComment(
            $request->user(),
            $moment,
            $validated['content'],
        );

        return $this->success(['comment' => $comment], 'Comment added.', 201);
    }
}
