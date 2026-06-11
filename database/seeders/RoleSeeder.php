<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['slug' => 'companies.manage', 'name' => 'Manage Companies', 'module' => 'companies', 'description' => 'Create, update, and delete tenant companies'],
            ['slug' => 'companies.view', 'name' => 'View Companies', 'module' => 'companies', 'description' => 'View company records across the platform'],
            ['slug' => 'employees.manage', 'name' => 'Manage Employees', 'module' => 'employees', 'description' => 'Create and update employee records'],
            ['slug' => 'employees.view', 'name' => 'View Employees', 'module' => 'employees', 'description' => 'View employee directory and profiles'],
            ['slug' => 'departments.manage', 'name' => 'Manage Departments', 'module' => 'departments', 'description' => 'Create and organize departments'],
            ['slug' => 'departments.view', 'name' => 'View Departments', 'module' => 'departments', 'description' => 'View department structure'],
            ['slug' => 'documents.manage', 'name' => 'Manage Documents', 'module' => 'documents', 'description' => 'Create and organize document types'],
            ['slug' => 'documents.view', 'name' => 'View Documents', 'module' => 'documents', 'description' => 'View document type master'],
            ['slug' => 'assets.manage', 'name' => 'Manage Assets', 'module' => 'assets', 'description' => 'Create and organize asset types'],
            ['slug' => 'assets.view', 'name' => 'View Assets', 'module' => 'assets', 'description' => 'View asset type master'],
            ['slug' => 'shifts.manage', 'name' => 'Manage Shifts', 'module' => 'shifts', 'description' => 'Create and organize work shifts'],
            ['slug' => 'shifts.view', 'name' => 'View Shifts', 'module' => 'shifts', 'description' => 'View shift schedules'],
            ['slug' => 'attendance.manage', 'name' => 'Manage Attendance', 'module' => 'attendance', 'description' => 'Manage attendance policies and corrections'],
            ['slug' => 'attendance.view', 'name' => 'View Attendance', 'module' => 'attendance', 'description' => 'View attendance records'],
            ['slug' => 'attendance.regularize', 'name' => 'Regularize Attendance', 'module' => 'attendance', 'description' => 'Submit attendance regularization requests'],
            ['slug' => 'attendance.approve', 'name' => 'Approve Attendance', 'module' => 'attendance', 'description' => 'Approve or reject attendance regularization requests'],
            ['slug' => 'leave.manage', 'name' => 'Manage Leave', 'module' => 'leave', 'description' => 'Configure leave types and policies'],
            ['slug' => 'leave.approve', 'name' => 'Approve Leave', 'module' => 'leave', 'description' => 'Approve or reject leave requests'],
            ['slug' => 'leave.apply', 'name' => 'Apply Leave', 'module' => 'leave', 'description' => 'Submit personal leave requests'],
            ['slug' => 'payroll.manage', 'name' => 'Manage Payroll', 'module' => 'payroll', 'description' => 'Run payroll and salary structures'],
            ['slug' => 'payroll.view', 'name' => 'View Payroll', 'module' => 'payroll', 'description' => 'View payroll and payslips'],
            ['slug' => 'reports.export', 'name' => 'Export Reports', 'module' => 'reports', 'description' => 'Export HR reports to Excel or PDF'],
            ['slug' => 'settings.manage', 'name' => 'Manage Settings', 'module' => 'settings', 'description' => 'Manage company-level HRMS settings'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                $permission
            );
        }

        $roles = [
            [
                'slug' => Role::SLUG_SUPER_ADMIN,
                'name' => 'Super Admin',
                'description' => 'Platform owner with full SaaS control across all companies.',
                'scope' => 'platform',
                'level' => 100,
                'permissions' => Permission::pluck('slug')->all(),
            ],
            [
                'slug' => Role::SLUG_COMPANY_ADMIN,
                'name' => 'Company Admin',
                'description' => 'Company owner or administrator with full access inside their organization.',
                'scope' => 'company',
                'level' => 90,
                'permissions' => [
                    'employees.manage', 'employees.view',
                    'departments.manage', 'departments.view',
                    'documents.manage', 'documents.view',
                    'assets.manage', 'assets.view',
                    'shifts.manage', 'shifts.view',
                    'attendance.manage', 'attendance.view', 'attendance.regularize', 'attendance.approve',
                    'leave.manage', 'leave.approve', 'leave.apply',
                    'payroll.manage', 'payroll.view',
                    'reports.export', 'settings.manage',
                ],
            ],
            [
                'slug' => Role::SLUG_HR_MANAGER,
                'name' => 'HR Manager',
                'description' => 'Handles employee lifecycle, attendance, leave, and HR reporting.',
                'scope' => 'company',
                'level' => 70,
                'permissions' => [
                    'employees.manage', 'employees.view',
                    'departments.manage', 'departments.view',
                    'documents.manage', 'documents.view',
                    'assets.manage', 'assets.view',
                    'shifts.manage', 'shifts.view',
                    'attendance.manage', 'attendance.view', 'attendance.regularize', 'attendance.approve',
                    'leave.manage', 'leave.approve', 'leave.apply',
                    'payroll.view', 'reports.export',
                ],
            ],
            [
                'slug' => Role::SLUG_DEPARTMENT_HEAD,
                'name' => 'Department Head',
                'description' => 'Head of a department. Team Leads and Employees report up through this role.',
                'scope' => 'company',
                'level' => 60,
                'permissions' => [
                    'employees.view',
                    'departments.view',
                    'shifts.view',
                    'attendance.view', 'attendance.regularize',
                    'leave.approve', 'leave.apply',
                    'payroll.view',
                ],
            ],
            [
                'slug' => Role::SLUG_TEAM_LEAD,
                'name' => 'Team Lead',
                'description' => 'Leads a team within a department. Employees report to the Team Lead.',
                'scope' => 'company',
                'level' => 40,
                'permissions' => [
                    'employees.view',
                    'attendance.view', 'attendance.regularize',
                    'leave.approve', 'leave.apply',
                    'payroll.view',
                ],
            ],
            [
                'slug' => Role::SLUG_EMPLOYEE,
                'name' => 'Employee',
                'description' => 'Standard employee reporting to a Team Lead, with self-service attendance and leave.',
                'scope' => 'company',
                'level' => 10,
                'permissions' => [
                    'attendance.view', 'attendance.regularize',
                    'leave.apply',
                    'payroll.view',
                ],
            ],
        ];

        foreach ($roles as $roleData) {
            $permissionSlugs = $roleData['permissions'];
            unset($roleData['permissions']);

            $role = Role::updateOrCreate(
                ['slug' => $roleData['slug']],
                array_merge($roleData, ['is_system' => true, 'status' => 'active'])
            );

            $permissionIds = Permission::query()
                ->whereIn('slug', $permissionSlugs)
                ->pluck('id');

            $role->permissions()->sync($permissionIds);
        }

        Role::query()
            ->where('slug', 'manager')
            ->delete();
    }
}
