<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentLetterTemplate extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'category',
        'subject',
        'description',
        'body_html',
        'requires_signature',
        'is_default',
        'status',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'requires_signature' => 'boolean',
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

    public function letters(): HasMany
    {
        return $this->hasMany(DocumentLetter::class, 'template_id');
    }
}
