<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendancePunchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'selfie' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'location_name' => ['nullable', 'string', 'max:500'],
            'face_match_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'selfie_face_descriptor' => ['nullable', 'array', 'min:64', 'max:2048'],
            'selfie_face_descriptor.*' => ['numeric'],
            'mac_address' => ['nullable', 'string', 'max:17'],
        ];
    }
}
