<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExitAssetReturnItem extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_WAIVED = 'waived';

    protected $fillable = [
        'exit_case_id',
        'asset_type_id',
        'asset_name',
        'status',
        'condition_notes',
        'received_by_user_id',
        'returned_at',
    ];

    protected function casts(): array
    {
        return [
            'returned_at' => 'datetime',
        ];
    }

    public function exitCase(): BelongsTo
    {
        return $this->belongsTo(ExitCase::class);
    }

    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
