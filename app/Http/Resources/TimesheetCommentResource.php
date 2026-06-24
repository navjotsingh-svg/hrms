<?php

namespace App\Http\Resources;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimesheetCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $linkedEmployeeId = (int) ($viewer?->employee?->id ?? 0);

        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'work_date' => $this->work_date?->format('Y-m-d'),
            'project_id' => $this->project_id,
            'parent_id' => $this->parent_id,
            'body' => $this->body,
            'author_name' => $this->user?->name ?? 'User',
            'author_role_label' => $this->authorRoleLabel(),
            'author_user_id' => $this->user_id,
            'is_mine' => $viewer && (int) $this->user_id === (int) $viewer->id,
            'is_employee_owner' => $linkedEmployeeId > 0 && (int) $this->employee_id === $linkedEmployeeId,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_label' => $this->created_at?->timezone(config('app.timezone'))->format('d M Y, h:i A'),
            'replies' => self::collection($this->whenLoaded('replies')),
        ];
    }

    private function authorRoleLabel(): string
    {
        $roleSlug = $this->user?->role?->slug;

        return match ($roleSlug) {
            Role::SLUG_DEPARTMENT_HEAD => 'Department Head',
            Role::SLUG_TEAM_LEAD => 'Team Lead',
            Role::SLUG_COMPANY_ADMIN => 'Admin',
            Role::SLUG_HR_MANAGER => 'HR',
            default => 'Employee',
        };
    }
}
