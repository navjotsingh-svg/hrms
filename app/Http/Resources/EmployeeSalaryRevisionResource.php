<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeSalaryRevisionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'annual_ctc' => (float) $this->annual_ctc,
            'basic_salary' => (float) $this->basic_salary,
            'hra_percent' => (float) $this->hra_percent,
            'special_allowance_percent' => (float) $this->special_allowance_percent,
            'hra' => (float) $this->hra,
            'special_allowance' => (float) $this->special_allowance,
            'conveyance_allowance' => (float) $this->conveyance_allowance,
            'medical_allowance' => (float) $this->medical_allowance,
            'other_allowance' => (float) $this->other_allowance,
            'monthly_gross' => $this->monthly_gross,
            'pf_applicable' => $this->pf_applicable,
            'esi_applicable' => $this->esi_applicable,
            'professional_tax_applicable' => $this->professional_tax_applicable,
            'salary_effective_from' => $this->salary_effective_from?->format('Y-m-d'),
            'salary_payout_from' => $this->salary_payout_from?->format('Y-m-d'),
            'revision_notes' => $this->revision_notes,
            'revision_type' => $this->revision_type,
            'revised_at' => $this->revised_at?->toIso8601String(),
            'revised_by' => $this->whenLoaded('revisedBy', fn () => $this->revisedBy ? [
                'id' => $this->revisedBy->id,
                'name' => $this->revisedBy->name,
            ] : null),
        ];
    }
}
