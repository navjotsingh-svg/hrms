<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Company extends Model
{
    protected $fillable = [
        'name',
        'legal_name',
        'slug',
        'email',
        'phone',
        'website',
        'logo',
        'industry',
        'founded_year',
        'employee_strength',
        'registration_number',
        'gstin',
        'pan_number',
        'contact_person_name',
        'contact_person_email',
        'contact_person_phone',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'country',
        'postal_code',
        'timezone',
        'attendance_portal_start_date',
        'attendance_allowed_ips',
        'attendance_face_match_threshold',
        'attendance_require_face_match',
        'pf_applicable',
        'esi_applicable',
        'professional_tax_applicable',
        'basic_salary_percent',
        'hra_percent',
        'special_allowance_percent',
        'conveyance_allowance',
        'medical_allowance',
        'other_allowance',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'attendance_portal_start_date' => 'date',
            'attendance_face_match_threshold' => 'integer',
            'attendance_require_face_match' => 'boolean',
            'pf_applicable' => 'boolean',
            'esi_applicable' => 'boolean',
            'professional_tax_applicable' => 'boolean',
            'basic_salary_percent' => 'decimal:2',
            'hra_percent' => 'decimal:2',
            'special_allowance_percent' => 'decimal:2',
            'conveyance_allowance' => 'decimal:2',
            'medical_allowance' => 'decimal:2',
            'other_allowance' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Company $company) {
            if (empty($company->slug)) {
                $company->slug = static::generateUniqueSlug($company->name);
            }
        });

        static::updating(function (Company $company) {
            if ($company->isDirty('name') && ! $company->isDirty('slug')) {
                $company->slug = static::generateUniqueSlug($company->name, $company->id);
            }
        });
    }

    public static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $count = 1;

        while (static::query()
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $originalSlug.'-'.$count;
            $count++;
        }

        return $slug;
    }

    public function adminUser(): HasOne
    {
        return $this->hasOne(User::class)->whereHas('role', function ($query) {
            $query->where('slug', Role::SLUG_COMPANY_ADMIN);
        });
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function documentTypes(): HasMany
    {
        return $this->hasMany(DocumentType::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? asset($this->logo) : null;
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

    public function payslipCompanyName(): string
    {
        return filled($this->legal_name)
            ? trim($this->legal_name)
            : trim((string) $this->name);
    }
}
