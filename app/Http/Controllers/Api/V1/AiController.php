<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\AiBulkImportExplainRequest;
use App\Http\Requests\AiDocumentDraftRequest;
use App\Http\Requests\AiGenericPromptRequest;
use App\Http\Requests\AiHelpdeskSuggestRequest;
use App\Http\Requests\AiHiringGenerateRequest;
use App\Http\Requests\AiPolicyAskRequest;
use App\Http\Requests\AiRoleAdviseRequest;
use App\Http\Requests\AiAnalyticsSummarizeRequest;
use App\Models\BulkImport;
use App\Services\AiFeatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
    use ApiResponse;

    public function __construct(
        private AiFeatureService $aiFeatureService,
    ) {}

    public function helpdeskSuggest(AiHelpdeskSuggestRequest $request): JsonResponse
    {
        $this->assertAiAccess($request);

        return $this->success($this->aiFeatureService->suggestHelpdeskTicket(
            $request->user(),
            $request->validated('description'),
        ));
    }

    public function documentDraft(AiDocumentDraftRequest $request): JsonResponse
    {
        $this->assertAiAccess($request);

        return $this->success($this->aiFeatureService->draftDocument(
            $request->user(),
            $request->validated('category'),
            $request->validated('prompt'),
            $request->validated('employee_id'),
        ));
    }

    public function hiringGenerate(AiHiringGenerateRequest $request): JsonResponse
    {
        $this->assertAiAccess($request);

        return $this->success($this->aiFeatureService->generateJobDescription(
            $request->user(),
            $request->validated('title'),
            $request->validated('department'),
            $request->validated('requirements'),
        ));
    }

    public function performanceReviewSuggest(AiGenericPromptRequest $request): JsonResponse
    {
        $this->assertAiAccess($request);

        return $this->success($this->aiFeatureService->suggestReviewComments(
            $request->user(),
            $request->validated('prompt'),
            $request->validated('employee_name'),
        ));
    }

    public function oneOnOneSuggest(AiGenericPromptRequest $request): JsonResponse
    {
        $this->assertAiAccess($request);

        return $this->success($this->aiFeatureService->suggestOneOnOneAgenda(
            $request->user(),
            $request->validated('employee_name'),
            $request->validated('prompt'),
        ));
    }

    public function bulkImportExplain(AiBulkImportExplainRequest $request, BulkImport $bulkImport): JsonResponse
    {
        $this->assertAiAccess($request);

        return $this->success($this->aiFeatureService->explainBulkImportErrors(
            $request->user(),
            $bulkImport,
        ));
    }

    public function analyticsSummarize(AiAnalyticsSummarizeRequest $request): JsonResponse
    {
        $this->assertAiAccess($request);

        return $this->success($this->aiFeatureService->summarizeAnalytics(
            $request->user(),
            $request->validated('report_key'),
            $request->validated('filters', []),
        ));
    }

    public function roleAdvise(AiRoleAdviseRequest $request): JsonResponse
    {
        $this->assertAiAccess($request);

        return $this->success($this->aiFeatureService->adviseRolePermissions(
            $request->user(),
            $request->validated('role_name'),
            $request->validated('description'),
        ));
    }

    public function dataQualityScan(Request $request): JsonResponse
    {
        $this->assertAiAccess($request);

        return $this->success($this->aiFeatureService->scanDataQuality($request->user()));
    }

    public function policyAsk(AiPolicyAskRequest $request): JsonResponse
    {
        $this->assertAiAccess($request);

        return $this->success($this->aiFeatureService->askPolicy(
            $request->user(),
            $request->validated('question'),
        ));
    }

    private function assertAiAccess(Request $request): void
    {
        abort_unless(config('hrms.assistant.enabled', true), 404);
        abort_unless($request->user()?->company_id, 403);
    }
}
