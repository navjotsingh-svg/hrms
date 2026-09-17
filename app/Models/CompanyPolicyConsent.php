<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyPolicyConsent extends Model
{
    public const PUBLIC_UPLOAD_DIR = 'images/company-policy-signatures';

    protected $fillable = [
        'company_id',
        'company_policy_id',
        'employee_id',
        'user_id',
        'policy_version',
        'consent_email',
        'signature_name',
        'signature_image_path',
        'signed_at',
        'signature_ip',
        'signature_meta',
    ];

    protected function casts(): array
    {
        return [
            'policy_version' => 'integer',
            'signed_at' => 'datetime',
            'signature_meta' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(CompanyPolicy::class, 'company_policy_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function signatureImageUrl(): ?string
    {
        return $this->signature_image_path ? '/'.ltrim($this->signature_image_path, '/') : null;
    }
}
