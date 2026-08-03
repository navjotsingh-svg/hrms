<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReviewSelectedAttendanceRegularizationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canApproveRegularization() ?? false;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.request_id' => ['required', 'integer', 'exists:attendance_regularization_requests,id'],
            'items.*.action' => ['required', Rule::in(['approve', 'reject'])],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($this->input('items', []) as $index => $item) {
                if (($item['action'] ?? null) !== 'reject') {
                    continue;
                }

                $notes = trim((string) ($item['notes'] ?? ''));

                if (strlen($notes) < 3) {
                    $validator->errors()->add(
                        "items.{$index}.notes",
                        'Rejection remarks are required (minimum 3 characters).',
                    );
                }
            }
        });
    }
}
