<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDocument extends Model
{
    public const PUBLIC_UPLOAD_DIR = 'images/employee-documents';

    protected $fillable = [
        'company_id',
        'employee_id',
        'document_type_id',
        'uploaded_by_user_id',
        'original_name',
        'file_path',
        'mime_type',
        'file_size',
        'status',
        'notes',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function canBeReuploaded(): bool
    {
        return $this->status === 'rejected';
    }

    public function isLocked(): bool
    {
        return in_array($this->status, ['pending', 'approved'], true);
    }

    public function fileUrl(): string
    {
        return '/'.ltrim($this->file_path, '/');
    }

    public function absoluteFilePath(): ?string
    {
        if (! $this->file_path || ! str_starts_with($this->file_path, 'images/')) {
            return null;
        }

        $path = public_path($this->file_path);

        return is_file($path) ? $path : null;
    }

    public function deleteFile(): void
    {
        if (! $this->file_path) {
            return;
        }

        $path = public_path($this->file_path);

        if (is_file($path)) {
            @unlink($path);
        }
    }
}
