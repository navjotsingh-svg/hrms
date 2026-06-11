<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePunch extends Model
{
    public const PUBLIC_UPLOAD_DIR = 'images/attendance/selfies';

    public const TYPE_IN = 'in';

    public const TYPE_OUT = 'out';

    public const SOURCE_LIVE = 'live';

    public const SOURCE_REGULARIZATION = 'regularization';

    protected $fillable = [
        'company_id',
        'employee_id',
        'punch_type',
        'punched_at',
        'latitude',
        'longitude',
        'location_name',
        'selfie_path',
        'source',
        'regularization_request_id',
    ];

    public function regularizationRequest(): BelongsTo
    {
        return $this->belongsTo(AttendanceRegularizationRequest::class, 'regularization_request_id');
    }

    public function isRegularized(): bool
    {
        return $this->source === self::SOURCE_REGULARIZATION;
    }

    protected function casts(): array
    {
        return [
            'punched_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
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

    public function selfieUrl(): string
    {
        return '/'.ltrim($this->selfie_path, '/');
    }

    public function locationLabel(): string
    {
        if ($this->location_name) {
            return $this->location_name;
        }

        return sprintf('%.5f, %.5f', $this->latitude, $this->longitude);
    }
}
