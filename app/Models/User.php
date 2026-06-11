<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'company_id',
        'role_id',
        'name',
        'email',
        'password',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function hasRole(string $slug): bool
    {
        return $this->role?->slug === $slug;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SLUG_SUPER_ADMIN);
    }

    public function isCompanyAdmin(): bool
    {
        return $this->hasRole(Role::SLUG_COMPANY_ADMIN);
    }

    public function isDepartmentHead(): bool
    {
        return $this->hasRole(Role::SLUG_DEPARTMENT_HEAD);
    }

    public function isTeamLead(): bool
    {
        return $this->hasRole(Role::SLUG_TEAM_LEAD);
    }

    public function isHrManager(): bool
    {
        return $this->hasRole(Role::SLUG_HR_MANAGER);
    }

    public function canViewEmployees(): bool
    {
        return $this->isCompanyAdmin()
            || $this->hasPermission('employees.manage')
            || $this->hasPermission('employees.view');
    }

    public function canManageEmployees(): bool
    {
        return $this->isCompanyAdmin() || $this->hasPermission('employees.manage');
    }

    public function canViewEmployeeProfile(): bool
    {
        return $this->canReviewEmployeeDocuments() || $this->canManageEmployees();
    }

    public function canReviewEmployeeProfile(Employee $employee): bool
    {
        if (! $this->canViewEmployeeProfile()) {
            return false;
        }

        if ($this->mustUseAdminForOwnHrReview((int) $employee->id)) {
            return false;
        }

        return true;
    }

    public function canReviewEmployeeDocuments(): bool
    {
        return $this->isCompanyAdmin() || $this->isHrManager();
    }

    public function canDeleteEmployeeDocument(EmployeeDocument $document): bool
    {
        if ((int) $document->company_id !== (int) $this->company_id) {
            return false;
        }

        if ($this->mustUseAdminForOwnHrReview((int) $document->employee_id)) {
            return false;
        }

        return $this->canReviewEmployeeDocuments();
    }

    public function canReviewDocument(EmployeeDocument $document): bool
    {
        if ((int) $document->company_id !== (int) $this->company_id) {
            return false;
        }

        if ($this->mustUseAdminForOwnHrReview((int) $document->employee_id)) {
            return false;
        }

        if ($document->uploadedBy?->isHrManager()) {
            return $this->isCompanyAdmin();
        }

        return $this->canReviewEmployeeDocuments();
    }

    public function canReviewPaymentMethod(EmployeePaymentMethod $method): bool
    {
        if ((int) $method->company_id !== (int) $this->company_id) {
            return false;
        }

        if ($this->mustUseAdminForOwnHrReview((int) $method->employee_id)) {
            return false;
        }

        if ($method->submittedBy?->isHrManager()) {
            return $this->isCompanyAdmin();
        }

        return $this->canReviewEmployeeDocuments();
    }

    public function canReviewComplianceField(EmployeeComplianceField $field): bool
    {
        if ((int) $field->company_id !== (int) $this->company_id) {
            return false;
        }

        if ($this->mustUseAdminForOwnHrReview((int) $field->employee_id)) {
            return false;
        }

        if ($field->submittedBy?->isHrManager()) {
            return $this->isCompanyAdmin();
        }

        return $this->canReviewEmployeeDocuments();
    }

    public function canReviewFamilyMember(EmployeeFamilyMember $member): bool
    {
        if ((int) $member->company_id !== (int) $this->company_id) {
            return false;
        }

        if ($this->mustUseAdminForOwnHrReview((int) $member->employee_id)) {
            return false;
        }

        if ($member->submittedBy?->isHrManager()) {
            return $this->isCompanyAdmin();
        }

        return $this->canReviewEmployeeDocuments();
    }

    public function canReviewPersonalSection(EmployeePersonalSection $section): bool
    {
        if ((int) $section->company_id !== (int) $this->company_id) {
            return false;
        }

        if ($this->mustUseAdminForOwnHrReview((int) $section->employee_id)) {
            return false;
        }

        if ($section->submittedBy?->isHrManager()) {
            return $this->isCompanyAdmin();
        }

        return $this->canReviewEmployeeDocuments();
    }

    public function canUpdateEmployeeContactInfo(): bool
    {
        return $this->isCompanyAdmin() || $this->isHrManager();
    }

    public function canEditEmployeeProfileWithoutApproval(Employee $employee): bool
    {
        return $this->canReviewEmployeeDocuments()
            && (int) $this->company_id === (int) $employee->company_id;
    }

    public function canMarkAttendance(): bool
    {
        return (bool) $this->employee
            && ! $this->isSuperAdmin()
            && ! $this->isCompanyAdmin();
    }

    public function canViewAttendance(): bool
    {
        return $this->employee
            || $this->isCompanyAdmin()
            || $this->isHrManager()
            || $this->hasPermission('attendance.view')
            || $this->hasPermission('attendance.manage');
    }

    public function canViewAllAttendance(): bool
    {
        return $this->isCompanyAdmin()
            || $this->isHrManager()
            || $this->hasPermission('attendance.manage');
    }

    public function canViewTeamAttendance(): bool
    {
        return $this->employee
            && $this->employee->directReports()->where('status', 'active')->exists();
    }

    public function canManageAttendanceMasters(): bool
    {
        return $this->isCompanyAdmin()
            || $this->isHrManager()
            || $this->hasPermission('attendance.manage');
    }

    public function canRegularizeAttendance(): bool
    {
        return ($this->employee || $this->canViewAllAttendance())
            && (
                $this->hasPermission('attendance.regularize')
                || $this->hasPermission('attendance.view')
                || $this->hasPermission('attendance.manage')
            );
    }

    public function canApproveRegularization(): bool
    {
        return $this->isCompanyAdmin()
            || $this->isHrManager()
            || $this->hasPermission('attendance.approve')
            || $this->hasPermission('attendance.manage');
    }

    public function canViewRegularizationRequests(): bool
    {
        return $this->canApproveRegularization() || $this->canRegularizeAttendance();
    }

    public function canReviewRegularizationRequest(AttendanceRegularizationRequest $request): bool
    {
        if ((int) $request->company_id !== (int) $this->company_id) {
            return false;
        }

        if ($request->status !== AttendanceRegularizationRequest::STATUS_PENDING) {
            return false;
        }

        if ($this->mustUseAdminForOwnHrReview((int) $request->employee_id)) {
            return false;
        }

        if ($request->employee?->user?->isHrManager() || $request->appliedBy?->isHrManager()) {
            return $this->isCompanyAdmin();
        }

        return $this->canApproveRegularization();
    }

    public function canCancelRegularizationRequest(AttendanceRegularizationRequest $request): bool
    {
        if ((int) $request->company_id !== (int) $this->company_id) {
            return false;
        }

        if (in_array($request->status, [
            AttendanceRegularizationRequest::STATUS_REJECTED,
            AttendanceRegularizationRequest::STATUS_CANCELLED,
        ], true)) {
            return false;
        }

        $isOwner = $this->employee && (int) $this->employee->id === (int) $request->employee_id;

        return $isOwner || $this->canApproveRegularization();
    }

    public function canManageCompanyMasters(): bool
    {
        return $this->isCompanyAdmin()
            || $this->isHrManager()
            || $this->hasPermission('departments.manage')
            || $this->hasPermission('documents.manage')
            || $this->hasPermission('assets.manage')
            || $this->hasPermission('shifts.manage')
            || $this->hasPermission('attendance.manage');
    }

    public function canApplyLeave(): bool
    {
        return (bool) $this->employee
            && ($this->hasPermission('leave.apply') || $this->isCompanyAdmin() || $this->isHrManager());
    }

    public function canManageLeaveTypes(): bool
    {
        return $this->isCompanyAdmin()
            || $this->isHrManager()
            || $this->hasPermission('leave.manage');
    }

    public function canManageLeaveBalances(): bool
    {
        return $this->isCompanyAdmin()
            || $this->isHrManager()
            || $this->hasPermission('leave.manage');
    }

    public function canViewPayroll(): bool
    {
        return $this->isCompanyAdmin()
            || $this->isHrManager()
            || $this->hasPermission('payroll.view');
    }

    public function canManagePayroll(): bool
    {
        return $this->isCompanyAdmin()
            || $this->isHrManager()
            || $this->hasPermission('payroll.manage');
    }

    public function canApproveLeave(): bool
    {
        return $this->isCompanyAdmin()
            || $this->isHrManager()
            || $this->hasPermission('leave.approve');
    }

    public function canReviewLeaveRequest(LeaveRequest $request): bool
    {
        if ((int) $request->company_id !== (int) $this->company_id) {
            return false;
        }

        if ($request->status !== LeaveRequest::STATUS_PENDING) {
            return false;
        }

        if ($this->isLeaveRequestOwner($request)) {
            return false;
        }

        $request->loadMissing(['employee.user', 'appliedBy']);

        if ($request->employee?->user?->isHrManager() || $request->appliedBy?->isHrManager()) {
            return $this->isCompanyAdmin();
        }

        if ($this->isCompanyAdmin() || $this->isHrManager()) {
            return true;
        }

        if (! $this->hasPermission('leave.approve')) {
            return false;
        }

        return $this->isDirectReportingManagerOfEmployee($request->employee);
    }

    public function canViewLeaveRequest(LeaveRequest $request): bool
    {
        if ((int) $request->company_id !== (int) $this->company_id) {
            return false;
        }

        if ($this->isLeaveRequestOwner($request)) {
            return true;
        }

        $request->loadMissing('employee');

        if ($this->canManageLeaveTypes()) {
            return true;
        }

        if ($this->isCompanyAdmin() || $this->isHrManager()) {
            return true;
        }

        if ($this->hasPermission('leave.approve') && $this->isDirectReportingManagerOfEmployee($request->employee)) {
            return true;
        }

        return false;
    }

    public function canViewAllLeaveRequests(): bool
    {
        return $this->isCompanyAdmin()
            || $this->isHrManager()
            || $this->canManageLeaveTypes();
    }

    public function canViewLeaveRequests(): bool
    {
        return $this->canViewAllLeaveRequests()
            || $this->canApproveLeave()
            || $this->canApplyLeave();
    }

    public function canUploadLeaveProof(LeaveRequest $request): bool
    {
        if ((int) $request->company_id !== (int) $this->company_id) {
            return false;
        }

        if ($request->status !== LeaveRequest::STATUS_PENDING) {
            return false;
        }

        return $this->employee && (int) $this->employee->id === (int) $request->employee_id;
    }

    public function canCancelLeaveRequest(LeaveRequest $request): bool
    {
        if ((int) $request->company_id !== (int) $this->company_id) {
            return false;
        }

        if (in_array($request->status, [LeaveRequest::STATUS_REJECTED, LeaveRequest::STATUS_CANCELLED], true)) {
            return false;
        }

        if ($this->isLeaveRequestOwner($request)) {
            return $request->status === LeaveRequest::STATUS_PENDING;
        }

        return $this->isCompanyAdmin() || $this->isHrManager();
    }

    public function isLeaveRequestOwner(LeaveRequest $request): bool
    {
        return $this->employee
            && (int) $this->employee->id === (int) $request->employee_id;
    }

    public function isDirectReportingManagerOfEmployee(?Employee $employee): bool
    {
        if (! $this->employee || ! $employee) {
            return false;
        }

        return (int) $employee->manager_id === (int) $this->employee->id;
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->role?->hasPermission($slug) ?? false;
    }

    private function mustUseAdminForOwnHrReview(int $employeeId): bool
    {
        return $this->isHrManager()
            && ! $this->isCompanyAdmin()
            && $this->employee
            && (int) $this->employee->id === $employeeId;
    }
}
