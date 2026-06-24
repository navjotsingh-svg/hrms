<?php

namespace App\Services;

use App\Models\User;

class MenuAccessService
{
    public function canSee(User $user, string $key): bool
    {
        if ($user->isSuperAdmin()) {
            return $key === 'companies';
        }

        if (! $user->company_id) {
            return false;
        }

        $menu = config('hrms.sidebar_menu', []);
        $config = $menu[$key] ?? null;

        if (! is_array($config)) {
            return false;
        }

        if ($user->hasFullAccess()) {
            return true;
        }

        if (! empty($config['feature']) && ! config('hrms.'.$config['feature'])) {
            return false;
        }

        if (! empty($config['rule'])) {
            return $this->evaluateRule($user, $config['rule']);
        }

        return $this->hasAnyPermission($user, $config['permissions'] ?? []);
    }

    /** @param  array<int, string>  $keys */
    public function canSeeSection(User $user, array $keys): bool
    {
        foreach ($keys as $key) {
            if ($this->canSee($user, $key)) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<int, string>  $slugs */
    private function hasAnyPermission(User $user, array $slugs): bool
    {
        foreach ($slugs as $slug) {
            if ($user->hasPermission($slug)) {
                return true;
            }
        }

        return false;
    }

    private function evaluateRule(User $user, string $rule): bool
    {
        return match ($rule) {
            'attendance_calendar' => $user->canViewAttendance(),
            'attendance_team' => $user->canViewTeamAttendance(),
            'timesheets_access' => $user->canAccessTimesheets(),
            default => false,
        };
    }
}
