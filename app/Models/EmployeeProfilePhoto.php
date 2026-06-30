<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeProfilePhoto extends Model
{
    public const PUBLIC_UPLOAD_DIR = 'images/employee-profile-photos';

    protected $fillable = [
        'company_id',
        'employee_id',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'status',
        'notes',
        'submitted_by_user_id',
        'reviewed_by_user_id',
        'submitted_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
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

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
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
