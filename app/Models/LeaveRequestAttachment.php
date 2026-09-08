<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequestAttachment extends Model
{
    public const PUBLIC_UPLOAD_DIR = 'images/leave-attachments';

    protected $fillable = [
        'leave_request_id',
        'original_name',
        'file_path',
        'mime_type',
        'file_size',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function fileUrl(): string
    {
        return '/'.ltrim($this->file_path, '/');
    }

    public function deleteFile(): void
    {
        $path = public_path($this->file_path);

        if (is_file($path)) {
            unlink($path);
        }
    }
}
