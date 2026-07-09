<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExitCaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stage' => $this->stage,
            'stage_label' => $this->resource->stageLabel(),
            'status' => $this->status,
            'status_label' => ucfirst(str_replace('_', ' ', $this->status)),
            'last_working_date' => $this->last_working_date?->format('d M Y'),
            'completed_at_label' => $this->completed_at?->labelStack(),
            'created_at_label' => $this->created_at?->labelStack(),
            'employee' => $this->when($this->relationLoaded('employee'), fn () => [
                'id' => $this->employee->id,
                'full_name' => $this->employee->full_name,
                'employee_code' => $this->employee->employee_code,
            ]),
            'resignation_request' => $this->when($this->relationLoaded('resignationRequest'), fn () => new ResignationRequestResource($this->resignationRequest)),
            'clearance_items' => ExitClearanceItemResource::collection($this->whenLoaded('clearanceItems')),
            'asset_return_items' => ExitAssetReturnItemResource::collection($this->whenLoaded('assetReturnItems')),
            'survey_response' => $this->when($this->relationLoaded('surveyResponse') && $this->surveyResponse, fn () => [
                'submitted_at_label' => $this->surveyResponse->submitted_at?->labelStack(),
                'responses' => $this->surveyResponse->responses,
                'is_submitted' => (bool) $this->surveyResponse->submitted_at,
            ]),
            'full_and_final_settlement' => $this->when($this->relationLoaded('fullAndFinalSettlement') && $this->fullAndFinalSettlement, fn () => [
                'leave_encashment' => (float) $this->fullAndFinalSettlement->leave_encashment,
                'pending_dues' => (float) $this->fullAndFinalSettlement->pending_dues,
                'deductions' => (float) $this->fullAndFinalSettlement->deductions,
                'net_payable' => (float) $this->fullAndFinalSettlement->net_payable,
                'settlement_notes' => $this->fullAndFinalSettlement->settlement_notes,
                'status' => $this->fullAndFinalSettlement->status,
                'status_label' => ucfirst($this->fullAndFinalSettlement->status),
                'processed_at_label' => $this->fullAndFinalSettlement->processed_at?->labelStack(),
            ]),
            'survey_questions' => $this->when(
                ($request->user()?->isExitCaseOwner($this->resource) ?? false)
                    && ! ($this->relationLoaded('surveyResponse') ? $this->surveyResponse?->submitted_at : false),
                fn () => app(\App\Services\ExitCaseService::class)->surveyQuestionsForCompany((int) $this->company_id),
            ),
            'can_manage' => $request->user()?->canManageOffboarding() ?? false,
            'can_review_clearance' => $request->user()?->canReviewClearance() ?? false,
            'can_manage_fnf' => $request->user()?->canManageFnfSettlement() ?? false,
            'is_owner' => $request->user()?->isExitCaseOwner($this->resource) ?? false,
        ];
    }
}
