<?php

namespace App\Services;

class EmployeeBulkImportFieldCatalog
{
    public const MAP_SKIP = '__skip__';

    public const MAP_EXTRA = '__extra__';

    /** @return array<int, array{key: string, label: string, group: string, required: bool, hint?: string}> */
    public static function fields(): array
    {
        return [
            ['key' => 'first_name', 'label' => 'First Name', 'group' => 'Basic', 'required' => true],
            ['key' => 'last_name', 'label' => 'Last Name', 'group' => 'Basic', 'required' => false],
            ['key' => 'email', 'label' => 'Work Email', 'group' => 'Basic', 'required' => true],
            ['key' => 'personal_email', 'label' => 'Personal Email', 'group' => 'Basic', 'required' => false],
            ['key' => 'phone', 'label' => 'Mobile Number', 'group' => 'Basic', 'required' => true, 'hint' => '10 digits'],
            ['key' => 'employee_code', 'label' => 'Employee Code', 'group' => 'Basic', 'required' => true],
            ['key' => 'designation', 'label' => 'Designation', 'group' => 'Basic', 'required' => false],
            ['key' => 'gender', 'label' => 'Gender', 'group' => 'Basic', 'required' => true, 'hint' => 'male, female, other'],
            ['key' => 'date_of_birth', 'label' => 'Date of Birth', 'group' => 'Basic', 'required' => true],
            ['key' => 'joining_date', 'label' => 'Joining Date', 'group' => 'Basic', 'required' => true],
            ['key' => 'employment_type', 'label' => 'Employment Type', 'group' => 'Basic', 'required' => false, 'hint' => 'full_time, part_time, contract, intern'],
            ['key' => 'is_paid_employee', 'label' => 'Paid Employee', 'group' => 'Basic', 'required' => false, 'hint' => 'yes/no — set no for unpaid interns'],
            ['key' => 'status', 'label' => 'Status', 'group' => 'Basic', 'required' => false, 'hint' => 'active or inactive'],
            ['key' => 'give_portal_access', 'label' => 'Portal Access', 'group' => 'Basic', 'required' => false, 'hint' => 'yes/no'],

            ['key' => 'department_name', 'label' => 'Department Name', 'group' => 'Organization', 'required' => false, 'hint' => 'Matched by name'],
            ['key' => 'role_name', 'label' => 'Role Name', 'group' => 'Organization', 'required' => false, 'hint' => 'Matched by name'],
            ['key' => 'shift_name', 'label' => 'Shift Name', 'group' => 'Organization', 'required' => false, 'hint' => 'Matched by name'],
            ['key' => 'manager_employee_code', 'label' => 'Manager Employee Code', 'group' => 'Organization', 'required' => false],
            ['key' => 'manager_email', 'label' => 'Manager Email', 'group' => 'Organization', 'required' => false],

            ['key' => 'address_line_1', 'label' => 'Address Line 1', 'group' => 'Address', 'required' => false],
            ['key' => 'address_line_2', 'label' => 'Address Line 2', 'group' => 'Address', 'required' => false],
            ['key' => 'city', 'label' => 'City', 'group' => 'Address', 'required' => false],
            ['key' => 'state', 'label' => 'State', 'group' => 'Address', 'required' => false],
            ['key' => 'country', 'label' => 'Country', 'group' => 'Address', 'required' => false],
            ['key' => 'postal_code', 'label' => 'Postal Code', 'group' => 'Address', 'required' => false],

            ['key' => 'pan_number', 'label' => 'PAN Number', 'group' => 'Compliance', 'required' => false],
            ['key' => 'aadhaar_number', 'label' => 'Aadhaar Number', 'group' => 'Compliance', 'required' => false],
            ['key' => 'uan', 'label' => 'UAN', 'group' => 'Compliance', 'required' => false],
            ['key' => 'pf_number', 'label' => 'PF Number', 'group' => 'Compliance', 'required' => false],
            ['key' => 'esi_number', 'label' => 'ESI Number', 'group' => 'Compliance', 'required' => false],

            ['key' => 'emergency_contact_name', 'label' => 'Emergency Contact Name', 'group' => 'Emergency', 'required' => false],
            ['key' => 'emergency_contact_phone', 'label' => 'Emergency Contact Phone', 'group' => 'Emergency', 'required' => false],
            ['key' => 'emergency_contact_relation', 'label' => 'Emergency Contact Relation', 'group' => 'Emergency', 'required' => false],

            ['key' => 'annual_ctc', 'label' => 'Annual CTC', 'group' => 'Salary', 'required' => false],
            ['key' => 'salary_effective_from', 'label' => 'Salary Effective From', 'group' => 'Salary', 'required' => false],
            ['key' => 'salary_payout_from', 'label' => 'Salary Payout From', 'group' => 'Salary', 'required' => false],
        ];
    }

    /** @return array<string, string> header => field key */
    public static function suggestMapping(array $headers): array
    {
        $aliases = self::aliasMap();
        $mapping = [];

        foreach ($headers as $header) {
            $header = trim((string) $header);

            if ($header === '') {
                continue;
            }

            $normalized = self::normalizeHeader($header);
            $mapping[$header] = $aliases[$normalized] ?? self::MAP_EXTRA;
        }

        return $mapping;
    }

    /** @return array<string, string> */
    private static function aliasMap(): array
    {
        $map = [];

        foreach (self::fields() as $field) {
            $map[self::normalizeHeader($field['key'])] = $field['key'];
            $map[self::normalizeHeader(str_replace('_', ' ', $field['key']))] = $field['key'];
            $map[self::normalizeHeader($field['label'])] = $field['key'];
        }

        $map[self::normalizeHeader('emp code')] = 'employee_code';
        $map[self::normalizeHeader('employee id')] = 'employee_code';
        $map[self::normalizeHeader('emp id')] = 'employee_code';
        $map[self::normalizeHeader('mobile')] = 'phone';
        $map[self::normalizeHeader('mobile number')] = 'phone';
        $map[self::normalizeHeader('contact number')] = 'phone';
        $map[self::normalizeHeader('work email')] = 'email';
        $map[self::normalizeHeader('official email')] = 'email';
        $map[self::normalizeHeader('dob')] = 'date_of_birth';
        $map[self::normalizeHeader('birth date')] = 'date_of_birth';
        $map[self::normalizeHeader('date of joining')] = 'joining_date';
        $map[self::normalizeHeader('doj')] = 'joining_date';
        $map[self::normalizeHeader('department')] = 'department_name';
        $map[self::normalizeHeader('dept')] = 'department_name';
        $map[self::normalizeHeader('role')] = 'role_name';
        $map[self::normalizeHeader('shift')] = 'shift_name';
        $map[self::normalizeHeader('manager code')] = 'manager_employee_code';
        $map[self::normalizeHeader('reporting manager code')] = 'manager_employee_code';
        $map[self::normalizeHeader('ctc')] = 'annual_ctc';
        $map[self::normalizeHeader('annual ctc')] = 'annual_ctc';
        $map[self::normalizeHeader('salary')] = 'annual_ctc';
        $map[self::normalizeHeader('pan')] = 'pan_number';
        $map[self::normalizeHeader('aadhaar')] = 'aadhaar_number';
        $map[self::normalizeHeader('aadhar')] = 'aadhaar_number';
        $map[self::normalizeHeader('paid employee')] = 'is_paid_employee';
        $map[self::normalizeHeader('is paid employee')] = 'is_paid_employee';
        $map[self::normalizeHeader('paid')] = 'is_paid_employee';
        $map[self::normalizeHeader('non paid')] = 'is_paid_employee';

        return $map;
    }

    public static function normalizeHeader(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
