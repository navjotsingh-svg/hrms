<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyMomentComment extends Model
{
    protected $fillable = [
        'company_moment_id',
        'user_id',
        'content',
    ];

    public function moment(): BelongsTo
    {
        return $this->belongsTo(CompanyMoment::class, 'company_moment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
