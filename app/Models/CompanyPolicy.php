<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyPolicy extends Model
{
    public const PUBLIC_UPLOAD_DIR = 'images/company-policies';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_DRAFT = 'draft';

    protected $fillable = [
        'company_id',
        'title',
        'category',
        'description',
        'body_html',
        'original_name',
        'file_path',
        'mime_type',
        'file_size',
        'status',
        'requires_consent',
        'version',
        'uploaded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'version' => 'integer',
            'requires_consent' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(CompanyPolicyConsent::class);
    }

    public function categoryLabel(): string
    {
        $categories = config('company_policies.categories', []);

        return $categories[$this->category] ?? ucfirst(str_replace('_', ' ', (string) $this->category));
    }

    public function statusLabel(): string
    {
        $statuses = config('company_policies.statuses', []);

        return $statuses[$this->status] ?? ucfirst((string) $this->status);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function fileUrl(): ?string
    {
        return $this->file_path ? '/'.ltrim($this->file_path, '/') : null;
    }

    public function absoluteFilePath(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return public_path(ltrim($this->file_path, '/'));
    }

    public function deleteFile(): void
    {
        $path = $this->absoluteFilePath();

        if ($path && is_file($path)) {
            @unlink($path);
        }
    }
}
