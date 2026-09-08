<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseAttachment extends Model
{
    public const PUBLIC_UPLOAD_DIR = 'images/expense-receipts';

    protected $fillable = [
        'expense_id',
        'original_name',
        'file_path',
        'mime_type',
        'file_size',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function fileUrl(): string
    {
        return asset($this->file_path);
    }

    public function deleteFile(): void
    {
        $absolutePath = public_path($this->file_path);

        if ($this->file_path && is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }
}
