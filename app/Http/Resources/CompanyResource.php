<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'slug' => $this->slug,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'logo' => $this->logo,
            'logo_url' => $this->logo_url,
            'industry' => $this->industry,
            'founded_year' => $this->founded_year,
            'employee_strength' => $this->employee_strength,
            'registration_number' => $this->registration_number,
            'gstin' => $this->gstin,
            'pan_number' => $this->pan_number,
            'contact_person_name' => $this->contact_person_name,
            'contact_person_email' => $this->contact_person_email,
            'contact_person_phone' => $this->contact_person_phone,
            'address_line_1' => $this->address_line_1,
            'address_line_2' => $this->address_line_2,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'postal_code' => $this->postal_code,
            'timezone' => $this->timezone,
            'status' => $this->status,
            'description' => $this->description,
            'full_address' => $this->full_address,
            'admin_user' => new UserResource($this->whenLoaded('adminUser')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
