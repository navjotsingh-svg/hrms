<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentLetterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $categories = config('document_letters.categories', []);
        $statuses = config('document_letters.statuses', []);

        return [
            'id' => $this->id,
            'document_number' => $this->document_number,
            'title' => $this->title,
            'category' => $this->category,
            'category_label' => $categories[$this->category] ?? ucfirst(str_replace('_', ' ', $this->category)),
            'rendered_html' => $this->rendered_html,
            'status' => $this->status,
            'status_label' => $statuses[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status)),
            'requires_signature' => (bool) $this->requires_signature,
            'issued_at_label' => $this->issued_at?->format('d M Y, h:i A'),
            'signed_at_label' => $this->signed_at?->format('d M Y, h:i A'),
            'signature_name' => $this->signature_name,
            'signature_image_url' => $this->signatureImageUrl(),
            'decline_reason' => $this->decline_reason,
            'employee' => $this->when($this->relationLoaded('employee'), fn () => [
                'id' => $this->employee->id,
                'full_name' => $this->employee->full_name,
                'employee_code' => $this->employee->employee_code,
            ]),
            'template' => $this->when($this->relationLoaded('template') && $this->template, fn () => [
                'id' => $this->template->id,
                'name' => $this->template->name,
            ]),
            'issued_by' => $this->when($this->relationLoaded('issuedBy') && $this->issuedBy, fn () => [
                'id' => $this->issuedBy->id,
                'name' => $this->issuedBy->name,
            ]),
            'signed_by' => $this->when($this->relationLoaded('signedBy') && $this->signedBy, fn () => [
                'id' => $this->signedBy->id,
                'name' => $this->signedBy->name,
            ]),
            'can_manage' => $request->user()?->canManageDocuments() ?? false,
            'can_sign' => $request->user()?->canSignDocumentLetter($this->resource) ?? false,
            'can_view' => $request->user()?->canViewDocumentLetter($this->resource) ?? false,
        ];
    }
}
