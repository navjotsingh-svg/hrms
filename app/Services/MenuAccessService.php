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

        if (! empty($config['feature']) && ! config('hrms.'.$config['feature'])) {
            return false;
        }

        $allowed = false;

        if (! empty($config['rule'])) {
            $allowed = $this->evaluateRule($user, $config['rule']);
        }

        if (! empty($config['permissions'])) {
            $allowed = $allowed || $this->hasAnyPermission($user, $config['permissions']);
        }

        if (! empty($config['rule']) || ! empty($config['permissions'])) {
            return $allowed;
        }

        return false;
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
            'company_member' => true,
            'company_admin' => $user->isCompanyAdmin(),
            'attendance_calendar' => $user->canViewAttendance(),
            'attendance_team' => $user->canViewTeamAttendance(),
            'timesheets_access' => $user->canAccessTimesheets(),
            default => false,
        };
    }
}
