<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExitCase extends Model
{
    public const STAGE_CLEARANCE = 'clearance';

    public const STAGE_ASSET_RETURN = 'asset_return';

    public const STAGE_SURVEY = 'survey';

    public const STAGE_FNF = 'fnf';

    public const STAGE_COMPLETED = 'completed';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'employee_id',
        'resignation_request_id',
        'last_working_date',
        'stage',
        'status',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_working_date' => 'date',
            'completed_at' => 'datetime',
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

    public function resignationRequest(): BelongsTo
    {
        return $this->belongsTo(ResignationRequest::class);
    }

    public function clearanceItems(): HasMany
    {
        return $this->hasMany(ExitClearanceItem::class)->orderBy('sort_order');
    }

    public function assetReturnItems(): HasMany
    {
        return $this->hasMany(ExitAssetReturnItem::class);
    }

    public function surveyResponse(): HasOne
    {
        return $this->hasOne(ExitSurveyResponse::class);
    }

    public function fullAndFinalSettlement(): HasOne
    {
        return $this->hasOne(FullAndFinalSettlement::class);
    }

    public function stageLabel(): string
    {
        return match ($this->stage) {
            self::STAGE_CLEARANCE => 'Clearance',
            self::STAGE_ASSET_RETURN => 'Asset Return',
            self::STAGE_SURVEY => 'Exit Survey',
            self::STAGE_FNF => 'Full & Final',
            self::STAGE_COMPLETED => 'Completed',
            default => ucfirst(str_replace('_', ' ', $this->stage)),
        };
    }
}
