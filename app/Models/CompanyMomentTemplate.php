<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyMomentTemplate extends Model
{
    protected $fillable = [
        'company_id',
        'birthday_template',
        'work_anniversary_template',
        'new_joinee_template',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function defaults(): array
    {
        return config('moments.default_templates', []);
    }

    public function templateForType(string $type): string
    {
        return match ($type) {
            CompanyMoment::TYPE_BIRTHDAY => $this->birthday_template,
            CompanyMoment::TYPE_WORK_ANNIVERSARY => $this->work_anniversary_template,
            CompanyMoment::TYPE_NEW_JOINEE => $this->new_joinee_template,
            default => '',
        };
    }
}
