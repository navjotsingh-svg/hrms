<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentLetter extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_SIGNATURE = 'pending_signature';

    public const STATUS_SIGNED = 'signed';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'employee_id',
        'template_id',
        'document_number',
        'title',
        'category',
        'rendered_html',
        'status',
        'requires_signature',
        'issued_by_user_id',
        'issued_at',
        'signature_name',
        'signature_image_path',
        'signed_at',
        'signed_by_user_id',
        'signature_ip',
        'signature_meta',
        'decline_reason',
    ];

    protected function casts(): array
    {
        return [
            'requires_signature' => 'boolean',
            'issued_at' => 'datetime',
            'signed_at' => 'datetime',
            'signature_meta' => 'array',
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentLetterTemplate::class, 'template_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by_user_id');
    }

    public function signatureImageUrl(): ?string
    {
        return $this->signature_image_path ? '/'.ltrim($this->signature_image_path, '/') : null;
    }

    public function isPendingSignature(): bool
    {
        return $this->status === self::STATUS_PENDING_SIGNATURE;
    }
}
