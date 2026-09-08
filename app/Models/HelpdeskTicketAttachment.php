<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskTicketAttachment extends Model
{
    public const PUBLIC_UPLOAD_DIR = 'images/helpdesk-attachments';

    protected $fillable = [
        'helpdesk_ticket_id',
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

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(HelpdeskTicket::class, 'helpdesk_ticket_id');
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
