<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyMomentAttachment extends Model
{
    public const PUBLIC_UPLOAD_DIR = 'images/moment-attachments';

    protected $fillable = [
        'company_moment_id',
        'original_name',
        'file_path',
        'mime_type',
        'file_size',
    ];

    public function moment(): BelongsTo
    {
        return $this->belongsTo(CompanyMoment::class, 'company_moment_id');
    }

    public function fileUrl(): string
    {
        return asset($this->file_path);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function deleteFile(): void
    {
        $absolutePath = public_path($this->file_path);

        if ($this->file_path && is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }
}
