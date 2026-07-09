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
            ['slug' => 'employees.assign_admin', 'name' => 'Assign Company Admin', 'module' => 'employees', 'description' => 'Promote or remove company administrator access for employees'],
            ['slug' => 'departments.manage', 'name' => 'Manage Departments', 'module' => 'departments', 'description' => 'Create and organize departments'],
            ['slug' => 'departments.view', 'name' => 'View Departments', 'module' => 'departments', 'description' => 'View department structure'],
            ['slug' => 'documents.manage', 'name' => 'Manage Documents', 'module' => 'documents', 'description' => 'Create templates, issue letters, and organize document types'],
            ['slug' => 'documents.view', 'name' => 'View Documents', 'module' => 'documents', 'description' => 'View documents, letters, and document type master'],
            ['slug' => 'documents.sign', 'name' => 'Sign Documents', 'module' => 'documents', 'description' => 'Review and e-sign issued letters such as offer letters'],
            ['slug' => 'assets.manage', 'name' => 'Manage Assets', 'module' => 'assets', 'description' => 'Create and organize asset types'],
            ['slug' => 'assets.view', 'name' => 'View Assets', 'module' => 'assets', 'description' => 'View asset type master'],
            ['slug' => 'assets.apply', 'name' => 'Request Assets', 'module' => 'assets', 'description' => 'Submit asset requests from the company catalog'],
            ['slug' => 'assets.approve', 'name' => 'Approve Asset Requests', 'module' => 'assets', 'description' => 'Approve or reject employee asset requests'],
            ['slug' => 'shifts.manage', 'name' => 'Manage Shifts', 'module' => 'shifts', 'description' => 'Create and organize work shifts'],
            ['slug' => 'shifts.view', 'name' => 'View Shifts', 'module' => 'shifts', 'description' => 'View shift schedules'],
            ['slug' => 'attendance.manage', 'name' => 'Manage Attendance', 'module' => 'attendance', 'description' => 'Manage attendance policies and corrections'],
            ['slug' => 'attendance.view', 'name' => 'View Attendance', 'module' => 'attendance', 'description' => 'View own attendance records'],
            ['slug' => 'attendance.view_team', 'name' => 'View Team Attendance', 'module' => 'attendance', 'description' => 'View attendance for direct reports or the wider team'],
            ['slug' => 'attendance.regularize', 'name' => 'Regularize Attendance', 'module' => 'attendance', 'description' => 'Submit attendance regularization requests'],
            ['slug' => 'attendance.approve', 'name' => 'Approve Attendance', 'module' => 'attendance', 'description' => 'Approve or reject attendance regularization requests'],
            ['slug' => 'leave.manage', 'name' => 'Manage Leave', 'module' => 'leave', 'description' => 'Configure leave types and policies'],
            ['slug' => 'leave.approve', 'name' => 'Approve Leave', 'module' => 'leave', 'description' => 'Approve or reject leave requests'],
            ['slug' => 'leave.apply', 'name' => 'Apply Leave', 'module' => 'leave', 'description' => 'Submit personal leave requests'],
            ['slug' => 'wfh.apply', 'name' => 'Apply WFH', 'module' => 'wfh', 'description' => 'Submit work from home requests'],
            ['slug' => 'wfh.approve', 'name' => 'Approve WFH', 'module' => 'wfh', 'description' => 'Approve or reject work from home requests'],
            ['slug' => 'offboarding.apply', 'name' => 'Submit Resignation', 'module' => 'offboarding', 'description' => 'Submit resignation requests'],
            ['slug' => 'offboarding.approve', 'name' => 'Approve Resignation', 'module' => 'offboarding', 'description' => 'Approve or reject resignation requests'],
            ['slug' => 'offboarding.manage', 'name' => 'Manage Offboarding', 'module' => 'offboarding', 'description' => 'Manage exit cases, clearance, and asset return'],
            ['slug' => 'clearance.review', 'name' => 'Review Clearance', 'module' => 'offboarding', 'description' => 'Sign off department clearance items'],
            ['slug' => 'offboarding.fnf.manage', 'name' => 'Manage F&F Settlement', 'module' => 'offboarding', 'description' => 'Process full and final settlements'],
            ['slug' => 'payroll.manage', 'name' => 'Manage Payroll', 'module' => 'payroll', 'description' => 'Run payroll and salary structures'],
            ['slug' => 'payroll.view', 'name' => 'View Payroll', 'module' => 'payroll', 'description' => 'View payroll and payslips'],
            ['slug' => 'reports.export', 'name' => 'Export Reports', 'module' => 'reports', 'description' => 'Export HR reports to Excel or PDF'],
            ['slug' => 'settings.manage', 'name' => 'Manage Settings', 'module' => 'settings', 'description' => 'Manage company-level HRMS settings'],
            ['slug' => 'projects.manage', 'name' => 'Manage Projects', 'module' => 'projects', 'description' => 'Create and update projects and assign team members'],
            ['slug' => 'projects.view', 'name' => 'View Projects', 'module' => 'projects', 'description' => 'View assigned projects'],
            ['slug' => 'timesheets.submit', 'name' => 'Submit Timesheets', 'module' => 'timesheets', 'description' => 'Log daily work hours against assigned projects'],
            ['slug' => 'expenses.apply', 'name' => 'Apply Expenses', 'module' => 'expenses', 'description' => 'Submit expense claims and groups'],
            ['slug' => 'expenses.approve', 'name' => 'Approve Expenses', 'module' => 'expenses', 'description' => 'Reserved for HR and admin expense approval routing'],
            ['slug' => 'expenses.manage', 'name' => 'Manage Expenses', 'module' => 'expenses', 'description' => 'Manage expense types and view all company expenses'],
            ['slug' => 'performance.manage', 'name' => 'Manage Performance', 'module' => 'performance', 'description' => 'Configure review cycles, goals visibility, and PIPs'],
            ['slug' => 'performance.participate', 'name' => 'Participate in Performance', 'module' => 'performance', 'description' => 'Create goals and complete self-reviews'],
            ['slug' => 'performance.review', 'name' => 'Review Performance', 'module' => 'performance', 'description' => 'Complete manager and peer performance reviews'],
            ['slug' => 'pip.manage', 'name' => 'Manage PIPs', 'module' => 'performance', 'description' => 'Create and track performance improvement plans'],
            ['slug' => 'hiring.manage', 'name' => 'Manage Hiring', 'module' => 'hiring', 'description' => 'Configure hiring settings, templates, and pipelines'],
            ['slug' => 'hiring.requisition.create', 'name' => 'Create Job Requisitions', 'module' => 'hiring', 'description' => 'Submit job requisition requests'],
            ['slug' => 'hiring.requisition.approve', 'name' => 'Approve Job Requisitions', 'module' => 'hiring', 'description' => 'Review and approve or reject job requisitions'],
            ['slug' => 'hiring.interview', 'name' => 'Conduct Interviews', 'module' => 'hiring', 'description' => 'Schedule and manage candidate interviews'],
            ['slug' => 'hiring.careers.publish', 'name' => 'Publish Careers Page', 'module' => 'hiring', 'description' => 'Publish and manage the public careers page'],
            ['slug' => 'logs.view', 'name' => 'View Activity Logs', 'module' => 'logs', 'description' => 'View company activity and audit logs'],
            ['slug' => 'roles.manage', 'name' => 'Manage Roles', 'module' => 'roles', 'description' => 'Configure role permissions and create custom roles'],
            ['slug' => 'roles.view', 'name' => 'View Roles', 'module' => 'roles', 'description' => 'View company roles and permission assignments'],
            ['slug' => 'home.view', 'name' => 'View Home', 'module' => 'home', 'description' => 'Access the Home section'],
            ['slug' => 'home.dashboard.view', 'name' => 'View Home Dashboard', 'module' => 'home', 'description' => 'View dashboard widgets and charts on Home'],
            ['slug' => 'home.dashboard.manage', 'name' => 'Manage Home Dashboard', 'module' => 'home', 'description' => 'Customize dashboard widget layout'],
            ['slug' => 'home.moments.view', 'name' => 'View Moments', 'module' => 'home', 'description' => 'View company moments feed'],
            ['slug' => 'home.moments.post', 'name' => 'Post Moments', 'module' => 'home', 'description' => 'Create posts in the moments feed'],
            ['slug' => 'home.moments.comment', 'name' => 'Comment on Moments', 'module' => 'home', 'description' => 'Add comments on company moments posts'],
            ['slug' => 'helpdesk.apply', 'name' => 'Raise Helpdesk Tickets', 'module' => 'helpdesk', 'description' => 'Submit and track employee support tickets'],
            ['slug' => 'helpdesk.manage', 'name' => 'Manage Helpdesk', 'module' => 'helpdesk', 'description' => 'Assign, respond to, and resolve helpdesk tickets'],
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
                'permissions' => Permission::pluck('slug')->all(),
            ],
            [
                'slug' => Role::SLUG_COMPANY_ADMIN,
                'name' => 'Company Admin',
                'description' => 'Company owner or administrator with full access inside their organization.',
                'scope' => 'company',
                'permissions' => [
                    'employees.manage', 'employees.view',
                    'departments.manage', 'departments.view',
                    'documents.manage', 'documents.view', 'documents.sign',
                    'assets.manage', 'assets.view', 'assets.apply', 'assets.approve',
                    'shifts.manage', 'shifts.view',
                    'attendance.manage', 'attendance.view', 'attendance.view_team', 'attendance.regularize', 'attendance.approve',
                    'leave.manage', 'leave.approve', 'leave.apply',
                    'wfh.apply', 'wfh.approve',
                    'offboarding.apply', 'offboarding.approve', 'offboarding.manage', 'clearance.review', 'offboarding.fnf.manage',
                    'assets.apply', 'assets.approve',
                    'payroll.manage', 'payroll.view',
                    'reports.export', 'settings.manage',
                    'projects.manage', 'projects.view',
                    'timesheets.submit',
                    'expenses.apply', 'expenses.approve', 'expenses.manage',
                    'performance.manage', 'performance.participate', 'performance.review', 'pip.manage',
                    'hiring.manage', 'hiring.requisition.create', 'hiring.requisition.approve', 'hiring.interview', 'hiring.careers.publish',
                    'logs.view',
                    'roles.manage', 'roles.view',
                    'home.view', 'home.dashboard.view', 'home.dashboard.manage', 'home.moments.view', 'home.moments.post', 'home.moments.comment',
                    'helpdesk.apply', 'helpdesk.manage',
                ],
            ],
            [
                'slug' => Role::SLUG_HR_MANAGER,
                'name' => 'HR Manager',
                'description' => 'Handles employee lifecycle, attendance, leave, and HR reporting.',
                'scope' => 'company',
                'permissions' => [
                    'employees.manage', 'employees.view',
                    'departments.manage', 'departments.view',
                    'documents.manage', 'documents.view', 'documents.sign',
                    'assets.manage', 'assets.view', 'assets.apply', 'assets.approve',
                    'shifts.manage', 'shifts.view',
                    'attendance.manage', 'attendance.view', 'attendance.view_team', 'attendance.regularize', 'attendance.approve',
                    'leave.manage', 'leave.approve', 'leave.apply',
                    'wfh.apply', 'wfh.approve',
                    'offboarding.apply', 'offboarding.approve', 'offboarding.manage', 'clearance.review', 'offboarding.fnf.manage',
                    'assets.apply', 'assets.approve',
                    'payroll.view', 'reports.export',
                    'timesheets.submit',
                    'expenses.apply', 'expenses.approve', 'expenses.manage',
                    'performance.manage', 'performance.participate', 'performance.review', 'pip.manage',
                    'hiring.manage', 'hiring.requisition.create', 'hiring.requisition.approve', 'hiring.interview', 'hiring.careers.publish',
                    'logs.view',
                    'home.view', 'home.dashboard.view', 'home.dashboard.manage', 'home.moments.view', 'home.moments.post', 'home.moments.comment',
                    'helpdesk.apply', 'helpdesk.manage',
                ],
            ],
            [
                'slug' => Role::SLUG_DEPARTMENT_HEAD,
                'name' => 'Department Head',
                'description' => 'Head of a department. Team Leads and Employees report up through this role.',
                'scope' => 'company',
                'permissions' => [
                    'employees.view',
                    'departments.view',
                    'documents.sign',
                    'shifts.view',
                    'attendance.view', 'attendance.view_team', 'attendance.regularize',
                    'leave.approve', 'leave.apply',
                    'wfh.apply', 'wfh.approve',
                    'offboarding.apply', 'offboarding.approve', 'offboarding.manage', 'clearance.review', 'offboarding.fnf.manage',
                    'assets.apply', 'assets.approve',
                    'payroll.view',
                    'projects.manage', 'projects.view',
                    'timesheets.submit',
                    'expenses.apply',
                    'hiring.requisition.create', 'hiring.interview',
                    'home.view', 'home.dashboard.view', 'home.moments.view', 'home.moments.post', 'home.moments.comment',
                    'helpdesk.apply',
                ],
            ],
            [
                'slug' => Role::SLUG_TEAM_LEAD,
                'name' => 'Team Lead',
                'description' => 'Leads a team within a department. Employees report to the Team Lead.',
                'scope' => 'company',
                'permissions' => [
                    'employees.view',
                    'documents.sign',
                    'attendance.view', 'attendance.view_team', 'attendance.regularize',
                    'leave.approve', 'leave.apply',
                    'wfh.apply', 'wfh.approve',
                    'offboarding.apply', 'offboarding.approve', 'offboarding.manage', 'clearance.review', 'offboarding.fnf.manage',
                    'assets.apply', 'assets.approve',
                    'payroll.view',
                    'projects.manage', 'projects.view',
                    'timesheets.submit',
                    'expenses.apply',
                    'hiring.requisition.create', 'hiring.interview',
                    'home.view', 'home.dashboard.view', 'home.moments.view', 'home.moments.post', 'home.moments.comment',
                ],
            ],
            [
                'slug' => Role::SLUG_EMPLOYEE,
                'name' => 'Employee',
                'description' => 'Standard employee reporting to a Team Lead, with self-service attendance and leave.',
                'scope' => 'company',
                'permissions' => [
                    'attendance.view', 'attendance.regularize',
                    'leave.apply',
                    'documents.sign',
                    'wfh.apply',
                    'offboarding.apply',
                    'assets.apply',
                    'payroll.view',
                    'projects.view',
                    'timesheets.submit',
                    'expenses.apply',
                    'home.view', 'home.moments.view', 'home.moments.comment',
                    'helpdesk.apply',
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
