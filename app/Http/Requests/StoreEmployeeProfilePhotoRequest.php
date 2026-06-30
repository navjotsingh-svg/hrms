<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeProfilePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => 'Please select a profile photo to upload.',
            'photo.image' => 'The profile photo must be an image file.',
            'photo.max' => 'The profile photo may not be larger than 5 MB.',
        ];
    }
}
