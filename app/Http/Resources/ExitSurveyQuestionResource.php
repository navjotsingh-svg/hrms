<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExitSurveyQuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $types = config('offboarding.survey_question_types', []);

        return [
            'id' => $this->id,
            'question' => $this->question,
            'type' => $this->type,
            'type_label' => $types[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type)),
            'options' => $this->options ?? [],
            'is_required' => (bool) $this->is_required,
            'sort_order' => (int) $this->sort_order,
            'status' => $this->status,
            'updated_at_label' => $this->updated_at?->format('d M Y, h:i A'),
        ];
    }
}
