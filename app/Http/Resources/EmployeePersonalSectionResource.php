<?php

namespace App\Http\Resources;

use App\Models\EmployeePersonalSection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeePersonalSectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'section_type' => $this->section_type,
            'section_label' => $this->label(),
            'payload' => $this->payload,
            'status' => $this->status,
            'notes' => $this->notes,
            'can_resubmit' => $this->canBeResubmitted(),
            'is_locked' => $this->isLocked(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'full_name' => $this->employee->full_name,
                'employee_code' => $this->employee->employee_code,
            ]),
            'submitted_by' => $this->whenLoaded('submittedBy', fn () => $this->submittedBy ? [
                'id' => $this->submittedBy->id,
                'name' => $this->submittedBy->name,
                'role' => $this->submittedBy->role?->name,
            ] : null),
            'reviewed_by' => $this->whenLoaded('reviewedBy', fn () => $this->reviewedBy ? [
                'id' => $this->reviewedBy->id,
                'name' => $this->reviewedBy->name,
            ] : null),
            'summary' => $this->summary(),
        ];
    }

    private function summary(): string
    {
        return match ($this->section_type) {
            'family' => collect($this->payload['members'] ?? [])
                ->map(fn (array $member) => trim(($member['name'] ?? '').' ('.($member['relation'] ?? '').')'))
                ->filter()
                ->implode(', ') ?: '—',
            'address' => $this->formatAddressSummary($this->payload['permanent'] ?? []),
            'emergency_contact' => $this->formatEmergencySummary(),
            default => '—',
        };
    }

    private function formatAddressSummary(array $address): string
    {
        $parts = array_filter([
            $address['address_line_1'] ?? null,
            $address['city'] ?? null,
            $address['state'] ?? null,
            $address['postal_code'] ?? null,
        ]);

        return $parts ? implode(', ', $parts) : '—';
    }

    private function formatEmergencySummary(): string
    {
        $name = trim((string) ($this->payload['name'] ?? ''));
        $relation = trim((string) ($this->payload['relation'] ?? ''));

        if ($name !== '') {
            return $relation !== '' ? "{$name} ({$relation})" : $name;
        }

        $member = $this->employee?->familyMembers
            ?->firstWhere('id', $this->payload['family_member_id'] ?? null);

        if ($member) {
            return trim($member->name.' ('.$member->relation.')');
        }

        return '—';
    }
}
