<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyMoment extends Model
{
    public const TYPE_POST = 'post';

    public const TYPE_BIRTHDAY = 'birthday';

    public const TYPE_WORK_ANNIVERSARY = 'work_anniversary';

    public const TYPE_NEW_JOINEE = 'new_joinee';

    protected $fillable = [
        'company_id',
        'type',
        'author_user_id',
        'content',
        'metadata',
        'occasion_date',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occasion_date' => 'date',
            'published_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(CompanyMomentReaction::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(CompanyMomentComment::class)->latest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CompanyMomentAttachment::class);
    }

    public function isSystemMoment(): bool
    {
        return $this->type !== self::TYPE_POST;
    }
}
