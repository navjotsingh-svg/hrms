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
        return array_merge($this->employeeSalaryRules(), [
            'revision_notes' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
