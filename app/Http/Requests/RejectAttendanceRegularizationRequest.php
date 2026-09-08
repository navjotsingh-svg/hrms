<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectAttendanceRegularizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canApproveRegularization() ?? false;
    }

    public function rules(): array
    {
        return [
            'notes' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}
