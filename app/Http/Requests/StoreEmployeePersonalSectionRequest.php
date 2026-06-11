<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\EmployeePersonalSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeePersonalSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            'phone.digits' => 'Mobile number must be exactly 10 digits.',
        ];
    }

    public function rules(): array
    {
        $sectionType = $this->input('section_type');
        $employee = $this->resolveTargetEmployee();

        $rules = [
            'section_type' => ['required', Rule::in(EmployeePersonalSection::SECTION_TYPES)],
        ];

        return match ($sectionType) {
            'address' => array_merge($rules, $this->addressRules()),
            'emergency_contact' => array_merge($rules, $this->emergencyRules($employee)),
            default => $rules,
        };
    }

    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);

        return match ($validated['section_type']) {
            'address' => [
                'section_type' => 'address',
                'payload' => [
                    'same_as_permanent' => (bool) ($validated['same_as_permanent'] ?? false),
                    'permanent' => $this->normalizeAddressBlock($validated['permanent'] ?? []),
                    'temporary' => $this->normalizeAddressBlock($validated['temporary'] ?? []),
                ],
            ],
            'emergency_contact' => [
                'section_type' => 'emergency_contact',
                'payload' => [
                    'name' => trim($validated['name']),
                    'relation' => trim($validated['relation']),
                    'phone' => isset($validated['phone']) && trim($validated['phone']) !== ''
                        ? trim($validated['phone'])
                        : null,
                ],
            ],
            default => $validated,
        };
    }

    private function addressRules(): array
    {
        $blockRules = fn (string $prefix, bool $required = false) => [
            "{$prefix}.address_line_1" => [$required ? 'required' : 'nullable', 'string', 'max:255'],
            "{$prefix}.address_line_2" => ['nullable', 'string', 'max:255'],
            "{$prefix}.city" => ['nullable', 'string', 'max:100'],
            "{$prefix}.state" => ['nullable', 'string', 'max:100'],
            "{$prefix}.country" => ['nullable', 'string', 'max:100'],
            "{$prefix}.postal_code" => ['nullable', 'string', 'max:20'],
        ];

        return array_merge(
            ['same_as_permanent' => ['nullable', 'boolean']],
            ['permanent' => ['required', 'array']],
            $blockRules('permanent', true),
            ['temporary' => ['nullable', 'array']],
            $blockRules('temporary'),
        );
    }

    private function emergencyRules(?Employee $employee): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'relation' => ['required', 'string', 'max:50'],
            'phone' => ['nullable', 'digits:10'],
        ];
    }

    private function resolveTargetEmployee(): ?Employee
    {
        $routeEmployee = $this->route('employee');

        if ($routeEmployee instanceof Employee) {
            return $routeEmployee;
        }

        return $this->user()?->employee;
    }

    private function normalizeAddressBlock(array $block): array
    {
        return [
            'address_line_1' => isset($block['address_line_1']) ? trim($block['address_line_1']) : null,
            'address_line_2' => isset($block['address_line_2']) ? trim($block['address_line_2']) : null,
            'city' => isset($block['city']) ? trim($block['city']) : null,
            'state' => isset($block['state']) ? trim($block['state']) : null,
            'country' => isset($block['country']) ? trim($block['country']) : null,
            'postal_code' => isset($block['postal_code']) ? trim($block['postal_code']) : null,
        ];
    }
}
