<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceFaceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'face_match_threshold' => ['nullable', 'integer', 'min:1', 'max:100'],
            'require_face_match' => ['nullable', 'boolean'],
        ];
    }
}
