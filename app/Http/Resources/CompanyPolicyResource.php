<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyPolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canManage = $request->user()?->canManageDocuments() ?? false;
        $consent = $this->relationLoaded('myConsent') ? $this->getRelation('myConsent') : null;
        $hasCurrentConsent = $consent
            && (int) $consent->policy_version === (int) $this->version;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'category' => $this->category,
            'category_label' => $this->categoryLabel(),
            'description' => $this->description,
            'body_html' => $this->body_html,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'requires_consent' => (bool) $this->requires_consent,
            'version' => $this->version,
            'uploaded_by' => $this->uploadedBy?->name,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'updated_at_label' => $this->updated_at?->format('d M Y'),
            'can_manage' => $canManage,
            'has_consented' => $hasCurrentConsent,
            'needs_consent' => (bool) $this->requires_consent && ! $hasCurrentConsent && $this->isPublished() && ! $canManage,
            'my_consent' => $consent ? [
                'consent_email' => $consent->consent_email,
                'signature_name' => $consent->signature_name,
                'signature_image_url' => $consent->signatureImageUrl(),
                'signed_at' => $consent->signed_at?->toIso8601String(),
                'signed_at_label' => $consent->signed_at?->format('d M Y, h:i A'),
                'policy_version' => $consent->policy_version,
            ] : null,
        ];
    }
}
