<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareersPageSetting extends Model
{
    protected $primaryKey = 'company_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'company_id',
        'hero_title',
        'hero_subtitle',
        'about_html',
        'header_html',
        'footer_html',
        'banner_path',
        'logo_path',
        'is_published',
        'embed_snippet',
        'meta_title',
        'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
