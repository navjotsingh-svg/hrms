<?php

namespace App\Models;

use App\Support\CareersPageDefaults;
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
        'hero_cta_text',
        'hero_cta_url',
        'about_html',
        'header_html',
        'footer_html',
        'banner_path',
        'logo_path',
        'theme_primary',
        'theme_accent',
        'sections',
        'is_published',
        'embed_snippet',
        'meta_title',
        'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'sections' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function resolvedSections(): array
    {
        return CareersPageDefaults::merge($this->sections);
    }
}
