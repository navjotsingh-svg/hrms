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

    public const TYPE_WFH_SUBMITTED = 'wfh_submitted';

    public const TYPE_WFH_DECISION = 'wfh_decision';

    public const TYPE_ASSET_REQUEST_SUBMITTED = 'asset_request_submitted';

    public const TYPE_ASSET_REQUEST_DECISION = 'asset_request_decision';

    public const TYPE_RESIGNATION_SUBMITTED = 'resignation_submitted';

    public const TYPE_RESIGNATION_DECISION = 'resignation_decision';

    public const TYPE_OFFBOARDING_COMPLETED = 'offboarding_completed';

    public const TYPE_MOMENT_NEW = 'moment_new';

    public const TYPE_HELPDESK_SUBMITTED = 'helpdesk_submitted';

    public const TYPE_HELPDESK_UPDATE = 'helpdesk_update';

    public const TYPE_DOCUMENT_SIGNATURE_REQUIRED = 'document_signature_required';

    public const TYPE_PROFILE_PHOTO_SUBMITTED = 'profile_photo_submitted';

    public const TYPE_PROFILE_PHOTO_DECISION = 'profile_photo_decision';

    public const TYPE_ONE_ON_ONE_SCHEDULED = 'one_on_one_scheduled';

    public const TYPE_TIMESHEET_SUBMITTED = 'timesheet_submitted';

    public const TYPE_TIMESHEET_COMMENT = 'timesheet_comment';

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
