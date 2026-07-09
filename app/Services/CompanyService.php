<?php

namespace App\Services;

use App\Http\Requests\Concerns\ValidatesCompanyFields;
use App\Mail\CompanyWelcomeMail;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CompanyService
{
    use ValidatesCompanyFields;

    public function __construct(
        private ImageCompressor $imageCompressor,
        private CompanyAdminEmployeeService $companyAdminEmployeeService,
    ) {}

    public function create(array $data, ?UploadedFile $logo = null): array
    {
        if ($logo) {
            $data['logo'] = $this->uploadLogo($logo);
        }

        $plainPassword = Str::password(12, symbols: false);

        $company = DB::transaction(function () use ($data, $plainPassword) {
            $company = Company::create($data);

            $adminUser = User::create([
                'company_id' => $company->id,
                'role_id' => Role::idFor(Role::SLUG_COMPANY_ADMIN),
                'name' => $company->contact_person_name ?: $company->name,
                'email' => $company->email,
                'password' => $plainPassword,
                'email_verified_at' => now(),
            ]);

            $this->companyAdminEmployeeService->ensureForAdmin($adminUser);

            return $company;
        });

        $message = 'Company created successfully.';

        try {
            Mail::to($company->email)->send(new CompanyWelcomeMail($company, $plainPassword));
            $message = 'Company created and welcome email sent with login credentials.';
        } catch (\Throwable $exception) {
            report($exception);
            $message = 'Company created but welcome email could not be sent. Please share credentials manually.';
        }

        return [
            'company' => $company->load('adminUser'),
            'message' => $message,
        ];
    }

    public function update(Company $company, array $data, ?UploadedFile $logo = null): Company
    {
        if ($logo) {
            $this->deleteLogo($company->logo);
            $data['logo'] = $this->uploadLogo($logo);
        }

        DB::transaction(function () use ($company, $data) {
            $company->update($data);

            $company->adminUser()?->update([
                'name' => $company->contact_person_name ?: $company->name,
                'email' => $company->email,
            ]);
        });

        return $company->fresh()->load('adminUser');
    }

    public function delete(Company $company): void
    {
        DB::transaction(function () use ($company) {
            $this->deleteLogo($company->logo);
            $company->adminUser()?->delete();
            $company->delete();
        });
    }

    public function validateField(string $field, string $value, ?int $companyId = null): array
    {
        if ($value === '') {
            return ['valid' => true, 'message' => null];
        }

        if (in_array($field, ['gstin', 'pan_number'], true)) {
            $value = strtoupper($value);
        }

        $userId = $companyId ? Company::find($companyId)?->adminUser?->id : null;
        $rules = $this->companyFieldRules($companyId, $userId);

        $validator = Validator::make(
            [$field => $value],
            [$field => $rules[$field] ?? []],
            $this->companyMessages()
        );

        if ($validator->fails()) {
            return [
                'valid' => false,
                'message' => $validator->errors()->first($field),
            ];
        }

        return ['valid' => true, 'message' => null];
    }

    public function validateCheckFieldRequest(array $input): array
    {
        return Validator::make($input, [
            'field' => ['required', Rule::in(['email', 'phone', 'registration_number', 'gstin', 'pan_number', 'contact_person_phone'])],
            'value' => ['nullable', 'string'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
        ])->validate();
    }

    private function uploadLogo(UploadedFile $file): string
    {
        return $this->imageCompressor->compressAndSave(
            $file,
            public_path('images/companies/logos')
        );
    }

    private function deleteLogo(?string $path): void
    {
        if ($path && file_exists(public_path($path))) {
            unlink(public_path($path));
        }
    }
}
