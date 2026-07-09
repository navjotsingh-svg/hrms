<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkImportRowExtra extends Model
{
    protected $fillable = [
        'bulk_import_row_id',
        'column_name',
        'column_value',
    ];

    public function row(): BelongsTo
    {
        return $this->belongsTo(BulkImportRow::class, 'bulk_import_row_id');
    }
}
