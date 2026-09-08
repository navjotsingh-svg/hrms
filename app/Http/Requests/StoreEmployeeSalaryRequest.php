<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesEmployeeSalaryFields;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeSalaryRequest extends FormRequest
{
    use ValidatesEmployeeSalaryFields;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $action = $this->input('salary_action', 'add');

        return [
            'salary_action' => ['nullable', 'string', 'in:add,revise,increment'],
            'annual_ctc' => ['required', 'numeric', 'min:1'],
            'salary_effective_from' => [
                $action === 'add' ? 'required' : 'nullable',
                'date',
            ],
            'salary_payout_from' => ['nullable', 'date', 'after_or_equal:salary_effective_from'],
            'revision_notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
