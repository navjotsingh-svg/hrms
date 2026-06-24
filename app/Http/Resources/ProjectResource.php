<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'description' => $this->description,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'status' => $this->status,
            'created_by_user_id' => $this->created_by_user_id,
            'created_by' => $this->whenLoaded('createdBy', function () {
                $employee = $this->createdBy?->employee;

                return [
                    'id' => $this->createdBy?->id,
                    'name' => $employee?->full_name ?? $this->createdBy?->name,
                ];
            }),
            'employees' => $this->whenLoaded('employees', fn () => $this->employees->map(fn ($employee) => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employee_code' => $employee->employee_code,
            ])),
            'employee_ids' => $this->whenLoaded('employees', fn () => $this->employees->pluck('id')->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
