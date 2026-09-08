<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePaymentMethodProof extends Model
{
    public const PUBLIC_UPLOAD_DIR = 'images/bank-payment-proofs';

    protected $fillable = [
        'company_id',
        'employee_id',
        'employee_payment_method_id',
        'uploaded_by_user_id',
        'original_name',
        'file_path',
        'mime_type',
        'file_size',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(EmployeePaymentMethod::class, 'employee_payment_method_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function fileUrl(): string
    {
        return '/'.ltrim($this->file_path, '/');
    }

    public function absoluteFilePath(): ?string
    {
        if (! $this->file_path || ! str_starts_with($this->file_path, 'images/')) {
            return null;
        }

        $path = public_path($this->file_path);

        return is_file($path) ? $path : null;
    }

    public function deleteFile(): void
    {
        if (! $this->file_path) {
            return;
        }

        $path = public_path($this->file_path);

        if (is_file($path)) {
            @unlink($path);
        }
    }
}
