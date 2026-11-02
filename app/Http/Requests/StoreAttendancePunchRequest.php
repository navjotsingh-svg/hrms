<?php

namespace App\Http\Requests;

use App\Services\AttendanceSettingsService;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttendancePunchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (int) ($this->user()?->company_id ?? 0);
        $requiresPhoto = app(AttendanceSettingsService::class)->requiresPunchPhoto($companyId);
        $requiresLiveness = $requiresPhoto && (bool) config('hrms.attendance.require_liveness', true);
        $requiresSelfieUpload = $requiresPhoto && ! $requiresLiveness;

        return [
            'selfie' => [
                $requiresSelfieUpload ? 'required' : 'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
            'liveness_session_id' => [
                $requiresLiveness ? 'required' : 'nullable',
                'uuid',
            ],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'location_name' => ['nullable', 'string', 'max:500'],
            'face_match_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'selfie_face_descriptor' => [
                'nullable',
                'array',
                'min:64',
                'max:2048',
            ],
            'selfie_face_descriptor.*' => ['numeric'],
            'mac_address' => ['nullable', 'string', 'max:17'],
        ];
    }
}
