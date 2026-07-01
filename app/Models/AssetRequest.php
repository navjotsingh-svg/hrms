<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_PARTIALLY_REVIEWED = 'partially_reviewed';

    protected $fillable = [
        'company_id',
        'employee_id',
        'applied_by_user_id',
        'reason',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_notes',
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

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AssetRequestItem::class);
    }

    public function assetNamesLabel(): string
    {
        $this->loadMissing('items.assetType');

        $names = $this->items
            ->map(fn (AssetRequestItem $item) => $item->assetType?->name)
            ->filter()
            ->values();

        return $names->isNotEmpty() ? $names->join(', ') : '—';
    }

    public function hasPendingItems(): bool
    {
        $this->loadMissing('items');

        return $this->items->contains(fn (AssetRequestItem $item) => $item->isPending());
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PARTIALLY_REVIEWED => 'Partially Reviewed',
            default => ucfirst($this->status),
        };
    }
}
