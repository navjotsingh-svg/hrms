<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public const SLUG_SUPER_ADMIN = 'super_admin';

    public const SLUG_COMPANY_ADMIN = 'company_admin';

    public const SLUG_HR_MANAGER = 'hr_manager';

    public const SLUG_DEPARTMENT_HEAD = 'department_head';

    public const SLUG_TEAM_LEAD = 'team_lead';

    public const SLUG_EMPLOYEE = 'employee';

    protected $fillable = [
        'company_id',
        'slug',
        'name',
        'description',
        'scope',
        'is_system',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->relationLoaded('permissions')) {
            return $this->permissions->contains('slug', $slug);
        }

        return $this->permissions()->where('slug', $slug)->exists();
    }

    public static function idFor(string $slug): ?int
    {
        return static::query()->where('slug', $slug)->value('id');
    }
}
