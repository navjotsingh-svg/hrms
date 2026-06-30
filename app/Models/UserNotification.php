<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    public const TYPE_LEAVE_SUBMITTED = 'leave_submitted';

    public const TYPE_LEAVE_DECISION = 'leave_decision';

    public const TYPE_DOCUMENT_VERIFICATION = 'document_verification';

    public const TYPE_DOCUMENT_DECISION = 'document_decision';

    public const TYPE_REGULARIZATION_SUBMITTED = 'regularization_submitted';

    public const TYPE_REGULARIZATION_DECISION = 'regularization_decision';

    public const TYPE_MOMENT_NEW = 'moment_new';

    public const TYPE_PROFILE_PHOTO_SUBMITTED = 'profile_photo_submitted';

    public const TYPE_PROFILE_PHOTO_DECISION = 'profile_photo_decision';

    protected $fillable = [
        'company_id',
        'user_id',
        'type',
        'title',
        'body',
        'action_url',
        'related_type',
        'related_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
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
}
