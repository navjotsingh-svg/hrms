<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BulkImportRow extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'bulk_import_id',
        'row_number',
        'status',
        'error_message',
        'employee_id',
        'raw_data',
    ];

    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
        ];
    }

    public function bulkImport(): BelongsTo
    {
        return $this->belongsTo(BulkImport::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function extras(): HasMany
    {
        return $this->hasMany(BulkImportRowExtra::class);
    }
}
