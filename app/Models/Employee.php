<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'department_id',
        'role_id',
        'manager_id',
        'shift_id',
        'employee_code',
        'first_name',
        'last_name',
        'email',
        'personal_email',
        'phone',
        'designation',
        'joining_date',
        'portal_access_date',
        'gender',
        'date_of_birth',
        'employment_type',
        'status',
        'probation_applicable',
        'probation_period_months',
        'probation_end_date',
        'probation_status',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'country',
        'postal_code',
        'temp_address_line_1',
        'temp_address_line_2',
        'temp_city',
        'temp_state',
        'temp_country',
        'temp_postal_code',
        'pan_number',
        'aadhaar_number',
        'uan',
        'pf_number',
        'esi_number',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'emergency_contact_family_member_id',
    ];

    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
            'portal_access_date' => 'date',
            'date_of_birth' => 'date',
            'probation_applicable' => 'boolean',
            'probation_end_date' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class)->withTimestamps();
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function salary(): HasOne
    {
        return $this->hasOne(EmployeeSalary::class);
    }

    public function salaryRevisions(): HasMany
    {
        return $this->hasMany(EmployeeSalaryRevision::class)->latest('revised_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(EmployeePaymentMethod::class);
    }

    public function complianceFields(): HasMany
    {
        return $this->hasMany(EmployeeComplianceField::class);
    }

    public function personalSections(): HasMany
    {
        return $this->hasMany(EmployeePersonalSection::class);
    }

    public function familyMembers(): HasMany
    {
        return $this->hasMany(EmployeeFamilyMember::class)->orderBy('sort_order');
    }

    public function employeeAssets(): HasMany
    {
        return $this->hasMany(EmployeeAsset::class);
    }

    public function attendancePunches(): HasMany
    {
        return $this->hasMany(AttendancePunch::class)->orderBy('punched_at');
    }

    public function emergencyContactFamilyMember(): BelongsTo
    {
        return $this->belongsTo(EmployeeFamilyMember::class, 'emergency_contact_family_member_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getFullAddressAttribute(): string
    {
        return collect([
            $this->address_line_1,
            $this->address_line_2,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ])->filter()->implode(', ');
    }

    public function getTempFullAddressAttribute(): string
    {
        return collect([
            $this->temp_address_line_1,
            $this->temp_address_line_2,
            $this->temp_city,
            $this->temp_state,
            $this->temp_postal_code,
            $this->temp_country,
        ])->filter()->implode(', ');
    }
}
