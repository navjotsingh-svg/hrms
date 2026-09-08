<?php

namespace App\Services;

use App\Models\BulkImport;
use App\Models\BulkImportRow;
use App\Models\BulkImportRowExtra;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EmployeeBulkImportService
{
    public function __construct(
        private BulkImportParserService $parserService,
        private EmployeeService $employeeService,
    ) {}

    public function upload(User $user, UploadedFile $file): BulkImport
    {
        $this->assertManage($user);

        $storedPath = $file->store('bulk-imports/'.(int) $user->company_id, 'local');
        $absolutePath = Storage::disk('local')->path($storedPath);
        $parsed = $this->parserService->parse($absolutePath);

        return BulkImport::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'entity_type' => 'employee',
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'status' => BulkImport::STATUS_MAPPING,
            'headers' => $parsed['headers'],
            'column_mapping' => EmployeeBulkImportFieldCatalog::suggestMapping($parsed['headers']),
            'preview_rows' => $parsed['preview'],
            'row_count' => count($parsed['rows']),
        ]);
    }

    public function resolveForUser(User $user, BulkImport $bulkImport): BulkImport
    {
        if ((int) $bulkImport->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Import not found.');
        }

        if ($bulkImport->entity_type !== 'employee') {
            throw new NotFoundHttpException('Import not found.');
        }

        return $bulkImport;
    }

    /** @return array<string, mixed> */
    public function mappingPayload(BulkImport $bulkImport): array
    {
        return [
            'import' => $this->formatImport($bulkImport),
            'fields' => EmployeeBulkImportFieldCatalog::fields(),
            'mapping_options' => [
                ['value' => EmployeeBulkImportFieldCatalog::MAP_EXTRA, 'label' => 'Store as extra column'],
                ['value' => EmployeeBulkImportFieldCatalog::MAP_SKIP, 'label' => 'Skip this column'],
            ],
            'suggested_mapping' => $bulkImport->column_mapping ?? [],
            'headers' => $bulkImport->headers ?? [],
            'preview_rows' => $bulkImport->preview_rows ?? [],
        ];
    }

    /** @param  array<string, string>  $mapping */
    public function confirm(User $user, BulkImport $bulkImport, array $mapping): BulkImport
    {
        $this->assertManage($user);
        $bulkImport = $this->resolveForUser($user, $bulkImport);

        if ($bulkImport->status !== BulkImport::STATUS_MAPPING) {
            throw ValidationException::withMessages([
                'import' => ['This import has already been processed.'],
            ]);
        }

        $validatedMapping = $this->validateMapping($mapping, $bulkImport->headers ?? []);
        $bulkImport->update([
            'column_mapping' => $validatedMapping,
            'status' => BulkImport::STATUS_PROCESSING,
        ]);

        $parsed = $this->parserService->parse(Storage::disk('local')->path($bulkImport->stored_path));
        $defaults = $this->companyDefaults((int) $user->company_id);
        $imported = 0;
        $failed = 0;

        DB::transaction(function () use ($bulkImport, $parsed, $validatedMapping, $defaults, $user, &$imported, &$failed) {
            foreach ($parsed['rows'] as $index => $rawRow) {
                $rowNumber = $index + 2;
                $importRow = BulkImportRow::create([
                    'bulk_import_id' => $bulkImport->id,
                    'row_number' => $rowNumber,
                    'status' => BulkImportRow::STATUS_PENDING,
                    'raw_data' => $rawRow,
                ]);

                try {
                    [$payload, $extras] = $this->buildPayload($rawRow, $validatedMapping, $defaults, (int) $user->company_id);
                    $this->validatePayload($payload, (int) $user->company_id);
                    $result = $this->employeeService->create((int) $user->company_id, $payload);
                    $employee = $result['employee'];

                    foreach ($extras as $columnName => $columnValue) {
                        BulkImportRowExtra::create([
                            'bulk_import_row_id' => $importRow->id,
                            'column_name' => $columnName,
                            'column_value' => $columnValue,
                        ]);
                    }

                    $importRow->update([
                        'status' => BulkImportRow::STATUS_SUCCESS,
                        'employee_id' => $employee->id,
                    ]);
                    $imported++;
                } catch (\Throwable $exception) {
                    $message = $exception instanceof ValidationException
                        ? collect($exception->errors())->flatten()->first()
                        : $exception->getMessage();

                    $importRow->update([
                        'status' => BulkImportRow::STATUS_FAILED,
                        'error_message' => $message,
                    ]);
                    $failed++;
                }
            }
        });

        $bulkImport->update([
            'status' => BulkImport::STATUS_COMPLETED,
            'imported_count' => $imported,
            'failed_count' => $failed,
            'summary_message' => "{$imported} employee(s) imported, {$failed} failed.",
        ]);

        return $bulkImport->fresh(['rows.extras']);
    }

    /** @return array<string, mixed> */
    public function resultPayload(BulkImport $bulkImport): array
    {
        $failedRows = $bulkImport->rows()
            ->where('status', BulkImportRow::STATUS_FAILED)
            ->orderBy('row_number')
            ->limit(50)
            ->get(['id', 'row_number', 'error_message']);

        return [
            'import' => $this->formatImport($bulkImport),
            'failed_rows' => $failedRows,
        ];
    }

    /** @param  array<string, string>  $mapping */
    private function validateMapping(array $mapping, array $headers): array
    {
        $allowedKeys = collect(EmployeeBulkImportFieldCatalog::fields())->pluck('key')->all();
        $allowedKeys[] = EmployeeBulkImportFieldCatalog::MAP_EXTRA;
        $allowedKeys[] = EmployeeBulkImportFieldCatalog::MAP_SKIP;

        $validated = [];

        foreach ($headers as $header) {
            $target = $mapping[$header] ?? EmployeeBulkImportFieldCatalog::MAP_EXTRA;

            if (! in_array($target, $allowedKeys, true)) {
                throw ValidationException::withMessages([
                    'mapping' => ["Invalid mapping target for column \"{$header}\"."],
                ]);
            }

            $validated[$header] = $target;
        }

        $mappedFields = array_values(array_filter(
            $validated,
            fn (string $target) => ! in_array($target, [EmployeeBulkImportFieldCatalog::MAP_EXTRA, EmployeeBulkImportFieldCatalog::MAP_SKIP], true),
        ));

        if (count($mappedFields) !== count(array_unique($mappedFields))) {
            throw ValidationException::withMessages([
                'mapping' => ['Each system field can only be mapped once.'],
            ]);
        }

        $requiredFields = collect(EmployeeBulkImportFieldCatalog::fields())
            ->where('required', true)
            ->pluck('key')
            ->all();

        $missingRequired = array_values(array_diff($requiredFields, $mappedFields));

        if ($missingRequired !== []) {
            $labels = collect(EmployeeBulkImportFieldCatalog::fields())
                ->whereIn('key', $missingRequired)
                ->pluck('label')
                ->implode(', ');

            throw ValidationException::withMessages([
                'mapping' => ["Please map required fields: {$labels}."],
            ]);
        }

        return $validated;
    }

    /** @return array{0: array<string, mixed>, 1: array<string, string|null>} */
    private function buildPayload(array $rawRow, array $mapping, array $defaults, int $companyId): array
    {
        $payload = $defaults;
        $extras = [];

        foreach ($mapping as $header => $target) {
            $value = $rawRow[$header] ?? null;

            if ($target === EmployeeBulkImportFieldCatalog::MAP_SKIP) {
                continue;
            }

            if ($target === EmployeeBulkImportFieldCatalog::MAP_EXTRA) {
                if ($value !== null && trim($value) !== '') {
                    $extras[$header] = $value;
                }

                continue;
            }

            if ($value === null || trim($value) === '') {
                continue;
            }

            $payload[$target] = $this->normalizeFieldValue($target, $value);
        }

        $payload = $this->resolveLookups($payload, $companyId);

        $isPaidEmployee = (bool) ($payload['is_paid_employee'] ?? true);

        if (! $isPaidEmployee) {
            unset($payload['annual_ctc'], $payload['salary_effective_from'], $payload['salary_payout_from']);
        } elseif (! empty($payload['annual_ctc']) && empty($payload['salary_effective_from'])) {
            $payload['salary_effective_from'] = $payload['joining_date'] ?? now()->toDateString();
        }

        return [$payload, $extras];
    }

    /** @return array<string, mixed> */
    private function companyDefaults(int $companyId): array
    {
        $roleId = Role::query()
            ->where('scope', 'company')
            ->where('slug', Role::SLUG_EMPLOYEE)
            ->where(function ($query) use ($companyId) {
                $query->whereNull('company_id')->orWhere('company_id', $companyId);
            })
            ->value('id');

        $shiftId = Shift::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->orderBy('name')
            ->value('id');

        $leaveTypeIds = LeaveType::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->orderBy('name')
            ->pluck('id')
            ->all();

        return [
            'role_id' => $roleId,
            'shift_id' => $shiftId,
            'leave_type_ids' => $leaveTypeIds,
            'weekly_off_mode' => Employee::WEEKLY_OFF_MODE_COMPANY,
            'status' => 'active',
            'employment_type' => 'full_time',
            'is_paid_employee' => true,
            'gender' => 'other',
            'give_portal_access' => false,
        ];
    }

    /** @param  array<string, mixed>  $payload */
    private function resolveLookups(array $payload, int $companyId): array
    {
        if (! empty($payload['department_name']) && empty($payload['department_id'])) {
            $payload['department_id'] = Department::query()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(name) = ?', [strtolower(trim((string) $payload['department_name']))])
                ->value('id');
            unset($payload['department_name']);
        }

        if (! empty($payload['role_name']) && empty($payload['role_id'])) {
            $payload['role_id'] = Role::query()
                ->where('scope', 'company')
                ->where(function ($query) use ($companyId) {
                    $query->whereNull('company_id')->orWhere('company_id', $companyId);
                })
                ->whereRaw('LOWER(name) = ?', [strtolower(trim((string) $payload['role_name']))])
                ->value('id');
            unset($payload['role_name']);
        }

        if (! empty($payload['shift_name']) && empty($payload['shift_id'])) {
            $payload['shift_id'] = Shift::query()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(name) = ?', [strtolower(trim((string) $payload['shift_name']))])
                ->value('id');
            unset($payload['shift_name']);
        }

        if (! empty($payload['manager_employee_code']) && empty($payload['manager_id'])) {
            $payload['manager_id'] = Employee::query()
                ->where('company_id', $companyId)
                ->where('employee_code', trim((string) $payload['manager_employee_code']))
                ->value('id');
            unset($payload['manager_employee_code']);
        }

        if (! empty($payload['manager_email']) && empty($payload['manager_id'])) {
            $payload['manager_id'] = Employee::query()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(email) = ?', [strtolower(trim((string) $payload['manager_email']))])
                ->value('id');
            unset($payload['manager_email']);
        }

        return $payload;
    }

    private function normalizeFieldValue(string $field, string $value): mixed
    {
        $value = trim($value);

        return match ($field) {
            'phone' => preg_replace('/\D+/', '', $value) ?? $value,
            'gender' => match (strtolower($value)) {
                'm', 'male', 'man' => 'male',
                'f', 'female', 'woman' => 'female',
                default => 'other',
            },
            'employment_type' => match (strtolower(str_replace([' ', '-'], '_', $value))) {
                'parttime', 'part_time' => 'part_time',
                'contract', 'contractor' => 'contract',
                'intern', 'internship' => 'intern',
                default => 'full_time',
            },
            'status' => strtolower($value) === 'inactive' ? 'inactive' : 'active',
            'give_portal_access' => in_array(strtolower($value), ['1', 'yes', 'true', 'y'], true),
            'is_paid_employee' => ! in_array(strtolower($value), ['0', 'no', 'false', 'n', 'unpaid', 'non-paid', 'nonpaid', 'non paid'], true),
            'joining_date', 'date_of_birth', 'salary_effective_from', 'salary_payout_from' => $this->parseDate($value),
            'annual_ctc' => (float) str_replace([',', '₹', ' '], '', $value),
            default => $value,
        };
    }

    private function parseDate(string $value): string
    {
        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'm/d/Y', 'd-M-Y', 'd M Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->toDateString();
            } catch (\Throwable) {
            }
        }

        return Carbon::parse($value)->toDateString();
    }

    /** @param  array<string, mixed>  $payload */
    private function validatePayload(array $payload, int $companyId): void
    {
        $validator = Validator::make($payload, [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('employees', 'email')->where(fn ($query) => $query->where('company_id', $companyId)),
                Rule::unique('users', 'email'),
            ],
            'phone' => [
                'required', 'digits:10',
                Rule::unique('employees', 'phone')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'employee_code' => [
                'required', 'string', 'max:50',
                Rule::unique('employees', 'employee_code')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'role_id' => ['required', 'integer'],
            'shift_id' => ['required', 'integer'],
            'leave_type_ids' => ['required', 'array', 'min:1'],
            'gender' => ['required', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['required', 'date'],
            'joining_date' => ['required', 'date'],
            'department_id' => ['nullable', 'integer'],
            'manager_id' => ['nullable', 'integer'],
            'is_paid_employee' => ['sometimes', 'boolean'],
            'annual_ctc' => ['nullable', 'numeric', 'min:0'],
            'salary_effective_from' => ['nullable', 'date'],
        ]);

        $validator->validate();
    }

    /** @return array<string, mixed> */
    private function formatImport(BulkImport $bulkImport): array
    {
        return [
            'id' => $bulkImport->id,
            'original_filename' => $bulkImport->original_filename,
            'status' => $bulkImport->status,
            'row_count' => $bulkImport->row_count,
            'imported_count' => $bulkImport->imported_count,
            'failed_count' => $bulkImport->failed_count,
            'summary_message' => $bulkImport->summary_message,
            'created_at' => $bulkImport->created_at?->toIso8601String(),
        ];
    }

    private function assertManage(User $user): void
    {
        if (! $user->canManageEmployees()) {
            throw new AccessDeniedHttpException('You do not have permission to import employees.');
        }
    }
}
