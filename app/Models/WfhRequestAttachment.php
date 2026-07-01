<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WfhRequestAttachment extends Model
{
    public const PUBLIC_UPLOAD_DIR = 'images/wfh-attachments';

    protected $fillable = [
        'wfh_request_id',
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

    public function wfhRequest(): BelongsTo
    {
        return $this->belongsTo(WfhRequest::class);
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
