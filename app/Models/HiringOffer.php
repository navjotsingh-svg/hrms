<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HiringOffer extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'company_id',
        'candidate_id',
        'job_id',
        'template_id',
        'title',
        'offered_ctc',
        'joining_date',
        'letter_html',
        'status',
        'sent_at',
        'responded_at',
        'created_by_user_id',
        'access_token',
        'token_expires_at',
        'pdf_path',
        'signed_pdf_path',
        'signature_name',
        'signature_image_path',
        'signed_at',
        'signature_ip',
        'signature_meta',
        'decline_reason',
        'otp_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'offered_ctc' => 'decimal:2',
            'joining_date' => 'date',
            'sent_at' => 'datetime',
            'responded_at' => 'datetime',
            'token_expires_at' => 'datetime',
            'signed_at' => 'datetime',
            'otp_verified_at' => 'datetime',
            'signature_meta' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class, 'job_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(HiringTemplate::class, 'template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
