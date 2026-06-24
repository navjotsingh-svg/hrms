<?php

namespace App\Http\Requests;

use App\Services\ProjectService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageProjects() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('description') === '') {
            $this->merge(['description' => null]);
        }

        if ($this->input('end_date') === '') {
            $this->merge(['end_date' => null]);
        }

        if ($this->user()?->canManageProjects() && $this->has('employee_ids')) {
            $this->merge([
                'employee_ids' => app(ProjectService::class)->resolveEmployeeIds(
                    $this->user(),
                    $this->input('employee_ids', []),
                ),
            ]);
        }
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', Rule::in(['active', 'closed'])],
            'employee_ids' => ['sometimes', 'required', 'array', 'min:1'],
            'employee_ids.*' => [
                'integer',
                Rule::exists('employees', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
        ];
    }
}
