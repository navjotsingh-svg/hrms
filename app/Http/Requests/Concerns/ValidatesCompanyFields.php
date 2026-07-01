<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

trait ValidatesCompanyFields
{
    protected function companyFieldRules(?int $companyId = null, ?int $userId = null): array
    {
        $unique = fn (string $column) => Rule::unique('companies', $column)->ignore($companyId);

        return [
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', $unique('email'), Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'digits:10', 'regex:/^[6-9]\d{9}$/', $unique('phone')],
            'website' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'],
            'industry' => ['nullable', 'string', 'max:255'],
            'founded_year' => ['nullable', 'digits:4', 'integer', 'min:1800', 'max:'.date('Y')],
            'employee_strength' => ['nullable', Rule::in(['1-10', '11-50', '51-200', '201-500', '501-1000', '1000+'])],
            'registration_number' => ['nullable', 'string', 'max:100', $unique('registration_number')],
            'gstin' => ['nullable', 'string', 'size:15', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $unique('gstin')],
            'pan_number' => ['nullable', 'string', 'size:10', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $unique('pan_number')],
            'contact_person_name' => ['nullable', 'string', 'max:255'],
            'contact_person_email' => ['nullable', 'email', 'max:255'],
            'contact_person_phone' => ['nullable', 'digits:10', 'regex:/^[6-9]\d{9}$/'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'digits:6'],
            'timezone' => ['nullable', 'timezone:all'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'description' => ['nullable', 'string', 'max:10000'],
        ];
    }

    protected function prepareCompanyFields(): void
    {
        $uppercaseFields = ['gstin', 'pan_number'];

        foreach ($uppercaseFields as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $this->merge([$field => strtoupper(trim($this->input($field)))]);
            }
        }

        $nullableFields = [
            'phone', 'registration_number', 'gstin', 'pan_number',
            'contact_person_phone', 'founded_year', 'legal_name',
        ];

        foreach ($nullableFields as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }

        if ($this->has('description')) {
            $description = $this->sanitizeDescription($this->input('description'));
            $this->merge(['description' => $description]);
        }
    }

    protected function sanitizeDescription(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $normalized = strtolower(preg_replace('/\s+/', '', $html));

        if (in_array($normalized, ['<p><br></p>', '<p></p>'], true)) {
            return null;
        }

        $allowed = '<p><br><strong><b><em><i><u><s><strike><ul><ol><li><h1><h2><h3><blockquote><a>';

        return trim(strip_tags($html, $allowed)) === ''
            ? null
            : strip_tags($html, $allowed);
    }

    public function companyMessages(): array
    {
        return [
            'phone.digits' => 'Phone number must be exactly 10 digits.',
            'phone.regex' => 'Phone number must be a valid 10-digit Indian mobile number.',
            'contact_person_phone.digits' => 'Contact phone must be exactly 10 digits.',
            'contact_person_phone.regex' => 'Contact phone must be a valid 10-digit Indian mobile number.',
            'founded_year.digits' => 'Founded year must be exactly 4 digits.',
            'gstin.size' => 'GSTIN must be exactly 15 characters.',
            'gstin.regex' => 'GSTIN format is invalid.',
            'pan_number.size' => 'PAN number must be exactly 10 characters.',
            'pan_number.regex' => 'PAN number format is invalid.',
            'postal_code.digits' => 'Postal code must be exactly 6 digits.',
            'email.unique' => 'This email is already registered.',
            'phone.unique' => 'This mobile number is already registered.',
            'registration_number.unique' => 'This registration number is already registered.',
            'gstin.unique' => 'This GSTIN is already registered.',
            'pan_number.unique' => 'This PAN number is already registered.',
        ];
    }
}
