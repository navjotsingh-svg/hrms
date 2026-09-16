<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyPolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canManage = $request->user()?->canManageDocuments() ?? false;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'category' => $this->category,
            'category_label' => $this->categoryLabel(),
            'description' => $this->description,
            'original_name' => $this->original_name,
            'file_url' => $this->fileUrl(),
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'file_size_label' => $this->formatFileSize((int) $this->file_size),
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'version' => $this->version,
            'uploaded_by' => $this->uploadedBy?->name,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'updated_at_label' => $this->updated_at?->format('d M Y'),
            'can_manage' => $canManage,
        ];
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return round($value, $power === 0 ? 0 : 1).' '.$units[$power];
    }
}
