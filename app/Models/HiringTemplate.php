<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HiringTemplate extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'type',
        'description',
        'body_html',
        'file_path',
        'is_default',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(HiringOffer::class, 'template_id');
    }
}
