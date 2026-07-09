<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BulkImport extends Model
{
    public const STATUS_MAPPING = 'mapping';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'company_id',
        'user_id',
        'entity_type',
        'original_filename',
        'stored_path',
        'status',
        'headers',
        'column_mapping',
        'preview_rows',
        'row_count',
        'imported_count',
        'failed_count',
        'summary_message',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'column_mapping' => 'array',
            'preview_rows' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(BulkImportRow::class);
    }
}
