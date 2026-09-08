<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILURE = 'failure';

    protected $fillable = [
        'uuid',
        'company_id',
        'user_id',
        'employee_id',
        'user_name',
        'user_email',
        'role_slug',
        'module',
        'action',
        'status',
        'subject_type',
        'subject_id',
        'request_type',
        'message',
        'failure_reason',
        'action_note',
        'old_values',
        'new_values',
        'metadata',
        'ip_address',
        'user_agent',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
            'logged_at' => 'datetime',
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

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
