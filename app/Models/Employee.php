<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class Employee extends Model
{
    public const WEEKLY_OFF_MODE_COMPANY = 'company_default';

    public const WEEKLY_OFF_MODE_CUSTOM = 'custom';

    public const TAX_REGIME_OLD = 'old';

    public const TAX_REGIME_NEW = 'new';

    protected $fillable = [
        'company_id',
        'user_id',
        'department_id',
        'role_id',
        'manager_id',
        'shift_id',
        'weekly_off_mode',
        'employee_code',
        'first_name',
        'last_name',
        'email',
        'personal_email',
        'phone',
        'designation',
        'profile_photo_path',
        'profile_face_descriptor',
        'joining_date',
        'last_working_date',
        'exit_type',
        'portal_access_date',
        'gender',
        'date_of_birth',
        'employment_type',
        'is_paid_employee',
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
        'tax_regime',
        'aadhaar_number',
        'uan',
        'pf_number',
        'esi_number',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'emergency_contact_family_member_id',
        'emergency_contacts',
    ];

    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
            'last_working_date' => 'date',
            'portal_access_date' => 'date',
            'date_of_birth' => 'date',
            'probation_applicable' => 'boolean',
            'is_paid_employee' => 'boolean',
            'probation_end_date' => 'date',
            'profile_face_descriptor' => 'array',
            'emergency_contacts' => 'array',
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

    public function exitCases(): HasMany
    {
        return $this->hasMany(ExitCase::class);
    }

    public function profilePhotoSubmission(): HasOne
    {
        return $this->hasOne(EmployeeProfilePhoto::class);
    }

    public function profilePhotoUrl(): ?string
    {
        if (! $this->profile_photo_path) {
            return null;
        }

        return '/'.ltrim($this->profile_photo_path, '/');
    }

    public function initials(): string
    {
        $first = trim((string) $this->first_name);
        $last = trim((string) $this->last_name);
        $fromNames = strtoupper(substr($first, 0, 1).substr($last, 0, 1));

        if ($fromNames !== '') {
            return $fromNames;
        }

        $parts = preg_split('/\s+/', trim($this->full_name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1).substr($parts[array_key_last($parts)], 0, 1));
        }

        if (count($parts) === 1) {
            return strtoupper(substr($parts[0], 0, 2)) ?: 'EM';
        }

        return 'EM';
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_employee')->withTimestamps();
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function weeklyOffDays(): HasMany
    {
        return $this->hasMany(EmployeeWeeklyOffDay::class)->orderBy('weekday');
    }

    public function leaveTypes(): BelongsToMany
    {
        return $this->belongsToMany(LeaveType::class, 'employee_leave_types')
            ->withTimestamps()
            ->orderBy('leave_types.sort_order')
            ->orderBy('leave_types.name');
    }

    public function pipPlans(): HasMany
    {
        return $this->hasMany(PipPlan::class);
    }

    public function isOnProbation(): bool
    {
        if (! $this->probation_applicable) {
            return false;
        }

        return in_array($this->probation_status, ['on_probation', 'extended'], true);
    }

    public function hasActivePip(): bool
    {
        return $this->pipPlans()
            ->where('status', PipPlan::STATUS_ACTIVE)
            ->exists();
    }

    public function restrictsPaidLeave(): bool
    {
        return $this->isOnProbation() || $this->hasActivePip();
    }

    public function isPaidEmployee(): bool
    {
        return (bool) ($this->is_paid_employee ?? true);
    }

    public function taxRegimeLabel(): string
    {
        return ($this->tax_regime ?? self::TAX_REGIME_NEW) === self::TAX_REGIME_OLD
            ? 'Old Regime'
            : 'New Regime';
    }

    public function paidLeaveRestrictionLabel(): ?string
    {
        if (! $this->restrictsPaidLeave()) {
            return null;
        }

        $parts = [];

        if ($this->isOnProbation()) {
            $parts[] = 'probation';
        }

        if ($this->hasActivePip()) {
            $parts[] = 'an active performance improvement plan (PIP)';
        }

        return 'Paid leave is not available while you are on '.implode(' or ', $parts).'. Only unpaid leave (such as Loss of Pay) can be applied.';
    }

    public function usesCompanyWeeklyOff(): bool
    {
        return ($this->weekly_off_mode ?? self::WEEKLY_OFF_MODE_COMPANY) === self::WEEKLY_OFF_MODE_COMPANY;
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

    /**
     * Sort employees alphabetically by display name.
     */
    public function scopeOrderedByName($query)
    {
        return $query
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->orderBy('employee_code');
    }

    /**
     * Employees who had at least one employed day in the date range,
     * including people who later became inactive / offboarded.
     */
    public function scopeEmployedDuring(Builder $query, mixed $start, mixed $end): Builder
    {
        $periodStart = Carbon::parse($start)->toDateString();
        $periodEnd = Carbon::parse($end)->toDateString();

        return $query
            ->where(function (Builder $builder) use ($periodEnd) {
                $builder
                    ->whereNull('joining_date')
                    ->orWhereDate('joining_date', '<=', $periodEnd);
            })
            ->where(function (Builder $builder) use ($periodStart) {
                $builder
                    ->where(function (Builder $inner) {
                        $inner->whereNull('last_working_date')->where('status', 'active');
                    })
                    ->orWhere(function (Builder $inner) use ($periodStart) {
                        $inner->whereNotNull('last_working_date')
                            ->whereDate('last_working_date', '>=', $periodStart);
                    })
                    ->orWhere(function (Builder $inner) use ($periodStart) {
                        $inner->whereNull('last_working_date')
                            ->where('status', 'inactive')
                            ->whereDate('updated_at', '>=', $periodStart);
                    });
            });
    }

    public function scopeEmployedOn(Builder $query, mixed $date): Builder
    {
        return $query->employedDuring($date, $date);
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
