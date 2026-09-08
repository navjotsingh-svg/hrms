<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompensationBand extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'grade',
        'min_salary',
        'mid_salary',
        'max_salary',
        'currency',
        'description',
        'is_active',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'min_salary' => 'decimal:2',
            'mid_salary' => 'decimal:2',
            'max_salary' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(CompensationRecommendation::class, 'band_id');
    }
}
