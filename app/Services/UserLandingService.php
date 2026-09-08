<?php

namespace App\Services;

use App\Models\User;

class UserLandingService
{
    /** @var array<int, array{route: string, permissions: array<int, string>}> */
    private const LANDING_CANDIDATES = [
        ['route' => 'web.home.index', 'permissions' => ['home.view']],
        ['route' => 'web.attendance.index', 'permissions' => ['attendance.view', 'attendance.manage']],
        ['route' => 'web.employees.index', 'permissions' => ['employees.view', 'employees.manage']],
        ['route' => 'web.requests.index', 'permissions' => [
            'leave.apply', 'leave.approve', 'attendance.regularize', 'attendance.approve',
            'expenses.apply', 'expenses.approve',
        ]],
        ['route' => 'web.profile', 'permissions' => []],
    ];

    public function routeNameFor(User $user): string
    {
        if ($user->isSuperAdmin() || ! $user->company_id) {
            return 'web.dashboard';
        }

        foreach (self::LANDING_CANDIDATES as $candidate) {
            if ($this->matchesCandidate($user, $candidate['permissions'])) {
                return $candidate['route'];
            }
        }

        return 'web.profile';
    }

    /** @param  array<int, string>  $permissions */
    private function matchesCandidate(User $user, array $permissions): bool
    {
        if ($permissions === []) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
