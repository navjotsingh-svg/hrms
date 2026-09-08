<?php

namespace App\Services;

class HrmsPageThemeService
{
    /** @return array{module: string, theme: string, icon: string, label: string} */
    public function resolve(?string $routeName): array
    {
        $parts = explode('.', (string) $routeName);
        $segment = $parts[1] ?? 'home';

        $map = [
            'home' => ['module' => 'home', 'theme' => 'brand', 'icon' => 'home', 'label' => 'Home'],
            'dashboard' => ['module' => 'home', 'theme' => 'brand', 'icon' => 'home', 'label' => 'Dashboard'],
            'employees' => ['module' => 'people', 'theme' => 'blue', 'icon' => 'people', 'label' => 'People'],
            'people' => ['module' => 'people', 'theme' => 'blue', 'icon' => 'people', 'label' => 'People'],
            'org-chart' => ['module' => 'people', 'theme' => 'blue', 'icon' => 'org', 'label' => 'Org Chart'],
            'attendance' => ['module' => 'attendance', 'theme' => 'orange', 'icon' => 'clock', 'label' => 'Attendance'],
            'attendance-regularize' => ['module' => 'attendance', 'theme' => 'orange', 'icon' => 'clock', 'label' => 'Regularization'],
            'leave' => ['module' => 'leave', 'theme' => 'green', 'icon' => 'calendar', 'label' => 'Leave'],
            'leave-types' => ['module' => 'leave', 'theme' => 'green', 'icon' => 'calendar', 'label' => 'Leave Types'],
            'requests' => ['module' => 'requests', 'theme' => 'violet', 'icon' => 'inbox', 'label' => 'Requests'],
            'wfh' => ['module' => 'wfh', 'theme' => 'cyan', 'icon' => 'house', 'label' => 'Work From Home'],
            'offboarding' => ['module' => 'offboarding', 'theme' => 'slate', 'icon' => 'exit', 'label' => 'Offboarding'],
            'assets' => ['module' => 'assets', 'theme' => 'amber', 'icon' => 'box', 'label' => 'Assets'],
            'asset-requests' => ['module' => 'assets', 'theme' => 'amber', 'icon' => 'box', 'label' => 'Asset Requests'],
            'performance' => ['module' => 'performance', 'theme' => 'purple', 'icon' => 'chart', 'label' => 'Performance'],
            'payroll' => ['module' => 'payroll', 'theme' => 'indigo', 'icon' => 'wallet', 'label' => 'Payroll'],
            'hiring' => ['module' => 'hiring', 'theme' => 'teal', 'icon' => 'briefcase', 'label' => 'Hiring'],
            'documents' => ['module' => 'documents', 'theme' => 'sky', 'icon' => 'file', 'label' => 'Documents'],
            'documents-letters' => ['module' => 'documents', 'theme' => 'sky', 'icon' => 'file', 'label' => 'Letters'],
            'projects' => ['module' => 'projects', 'theme' => 'rose', 'icon' => 'kanban', 'label' => 'Projects'],
            'timesheets' => ['module' => 'projects', 'theme' => 'rose', 'icon' => 'kanban', 'label' => 'Timesheets'],
            'expenses' => ['module' => 'expenses', 'theme' => 'emerald', 'icon' => 'receipt', 'label' => 'Expenses'],
            'analytics' => ['module' => 'analytics', 'theme' => 'brand', 'icon' => 'analytics', 'label' => 'Analytics'],
            'reports' => ['module' => 'analytics', 'theme' => 'brand', 'icon' => 'analytics', 'label' => 'Reports'],
            'companies' => ['module' => 'companies', 'theme' => 'brand', 'icon' => 'building', 'label' => 'Companies'],
            'departments' => ['module' => 'company', 'theme' => 'slate', 'icon' => 'settings', 'label' => 'Departments'],
            'roles' => ['module' => 'company', 'theme' => 'slate', 'icon' => 'settings', 'label' => 'Roles'],
            'shifts' => ['module' => 'company', 'theme' => 'slate', 'icon' => 'settings', 'label' => 'Shifts'],
            'holidays' => ['module' => 'company', 'theme' => 'slate', 'icon' => 'settings', 'label' => 'Holidays'],
            'weekly-off' => ['module' => 'company', 'theme' => 'slate', 'icon' => 'settings', 'label' => 'Weekly Off'],
            'activity-logs' => ['module' => 'company', 'theme' => 'slate', 'icon' => 'settings', 'label' => 'Activity Logs'],
            'helpdesk' => ['module' => 'helpdesk', 'theme' => 'blue', 'icon' => 'support', 'label' => 'Helpdesk'],
            'profile' => ['module' => 'profile', 'theme' => 'brand', 'icon' => 'user', 'label' => 'Profile'],
            'assistant' => ['module' => 'assistant', 'theme' => 'brand', 'icon' => 'support', 'label' => 'Assistant'],
            'portal-start' => ['module' => 'portal', 'theme' => 'brand', 'icon' => 'home', 'label' => 'Portal'],
            'employee-experience' => ['module' => 'experience', 'theme' => 'rose', 'icon' => 'people', 'label' => 'Employee Experience'],
        ];

        return $map[$segment] ?? [
            'module' => 'default',
            'theme' => 'brand',
            'icon' => 'grid',
            'label' => 'HRMS',
        ];
    }
}
