<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentLetterTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $categories = config('document_letters.categories', []);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'category_label' => $categories[$this->category] ?? ucfirst(str_replace('_', ' ', $this->category)),
            'subject' => $this->subject,
            'description' => $this->description,
            'body_html' => $this->body_html,
            'requires_signature' => (bool) $this->requires_signature,
            'is_default' => (bool) $this->is_default,
            'status' => $this->status,
            'created_at_label' => $this->created_at?->labelStack(),
            'updated_at_label' => $this->updated_at?->labelStack(),
        ];
    }
}
