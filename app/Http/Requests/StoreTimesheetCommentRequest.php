<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTimesheetCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer'],
            'work_date' => ['required', 'date'],
            'project_id' => ['required_without:parent_id', 'nullable', 'integer'],
            'body' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'project_id.required_without' => 'Select which project submission this comment is for.',
        ];
    }
}
