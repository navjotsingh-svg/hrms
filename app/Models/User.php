<?php

namespace App\Models;

use App\Services\EmployeeAccessService;
use App\Services\MenuAccessService;
use App\Services\RoleService;
use App\Services\TimesheetService;
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

    public function hasFullAccess(): bool
    {
        return $this->isCompanyAdmin();
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
        return $this->hasPermission('employees.manage')
            || $this->hasPermission('employees.view');
    }

    public function canManageEmployees(): bool
    {
        return $this->hasPermission('employees.manage');
    }

    public function canViewEmployeeProfile(): bool
    {
        return $this->hasPermission('employees.view')
            || $this->hasPermission('employees.manage');
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
        return $this->hasPermission('employees.manage');
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

        if ($document->status !== 'pending') {
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

        if ($method->status !== 'pending') {
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

        if ($field->status !== 'pending') {
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

        if ($member->status !== 'pending') {
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

        if ($section->status !== 'pending') {
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
        return $this->hasPermission('employees.manage');
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
        if ($this->hasFullAccess()) {
            return true;
        }

        return (bool) $this->employee
            || $this->hasPermission('attendance.view')
            || $this->hasPermission('attendance.manage');
    }

    public function canViewAllAttendance(): bool
    {
        return $this->hasFullAccess() || $this->hasPermission('attendance.manage');
    }

    public function canViewCompanyTeamAttendance(): bool
    {
        if ($this->hasFullAccess() || $this->hasPermission('attendance.manage')) {
            return false;
        }

        return $this->hasPermission('attendance.view_team')
            && (
                $this->isHrManager()
                || $this->hasPermission('employees.view')
                || $this->hasPermission('employees.manage')
            );
    }

    public function canViewTeamAttendance(): bool
    {
        if ($this->hasFullAccess() || $this->hasPermission('attendance.manage')) {
            return true;
        }

        if ($this->canViewCompanyTeamAttendance()) {
            return true;
        }

        if ($this->hasPermission('attendance.view_team') || $this->hasPermission('attendance.view')) {
            return app(EmployeeAccessService::class)->subordinateIdsForUser($this) !== [];
        }

        return false;
    }

    public function canManageAttendanceMasters(): bool
    {
        return $this->hasPermission('attendance.manage');
    }

    public function canRegularizeAttendance(): bool
    {
        if (! $this->employee && ! $this->hasPermission('attendance.manage')) {
            return false;
        }

        return $this->hasPermission('attendance.regularize')
            || $this->hasPermission('attendance.manage');
    }

    public function canApproveRegularization(): bool
    {
        return $this->hasPermission('attendance.approve')
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

        if ($request->status !== AttendanceRegularizationRequest::STATUS_PENDING) {
            return false;
        }

        return (int) $request->applied_by_user_id === (int) $this->id;
    }

    public function canManageCompanyMasters(): bool
    {
        if ($this->hasFullAccess()) {
            return true;
        }

        return $this->hasPermission('departments.view')
            || $this->hasPermission('departments.manage')
            || $this->hasPermission('documents.view')
            || $this->hasPermission('documents.manage')
            || $this->hasPermission('assets.view')
            || $this->hasPermission('assets.manage')
            || $this->hasPermission('shifts.view')
            || $this->hasPermission('shifts.manage')
            || $this->hasPermission('attendance.manage')
            || $this->hasPermission('leave.manage')
            || $this->hasPermission('roles.view')
            || $this->hasPermission('roles.manage')
            || $this->hasPermission('settings.manage');
    }

    public function canApplyLeave(): bool
    {
        return (bool) $this->employee
            && $this->hasPermission('leave.apply');
    }

    public function canManageLeaveTypes(): bool
    {
        return $this->hasPermission('leave.manage');
    }

    public function canManageLeaveBalances(): bool
    {
        return $this->hasPermission('leave.manage');
    }

    public function canViewLeaveAnalytics(): bool
    {
        return $this->hasPermission('leave.manage')
            || $this->hasPermission('reports.export');
    }

    public function canManagePerformance(): bool
    {
        return $this->hasPermission('performance.manage');
    }

    public function canParticipateInPerformance(): bool
    {
        return $this->canManagePerformance()
            || $this->hasPermission('performance.participate');
    }

    public function canReviewPerformance(): bool
    {
        return $this->canManagePerformance()
            || $this->hasPermission('performance.review');
    }

    public function canManagePips(): bool
    {
        return $this->canManagePerformance()
            || $this->hasPermission('pip.manage');
    }

    public function canViewPerformance(): bool
    {
        return $this->canParticipateInPerformance()
            || $this->canReviewPerformance()
            || $this->canManagePips();
    }

    public function canViewPayroll(): bool
    {
        return $this->hasPermission('payroll.view')
            || $this->hasPermission('payroll.manage');
    }

    public function canManagePayroll(): bool
    {
        return $this->hasPermission('payroll.manage');
    }

    public function canApproveLeave(): bool
    {
        return $this->hasPermission('leave.approve')
            || $this->hasPermission('leave.manage');
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

        if ($this->hasPermission('leave.manage')) {
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

        if ($this->hasPermission('leave.manage') || $this->hasPermission('leave.approve')) {
            return true;
        }

        if ($this->hasPermission('leave.approve') && $this->isDirectReportingManagerOfEmployee($request->employee)) {
            return true;
        }

        return false;
    }

    public function canViewAllLeaveRequests(): bool
    {
        return $this->hasPermission('leave.manage')
            || $this->hasPermission('leave.approve');
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

    public function canBypassLeaveProofRequirement(LeaveRequest $request): bool
    {
        if ((int) $request->company_id !== (int) $this->company_id) {
            return false;
        }

        if ($request->status !== LeaveRequest::STATUS_PENDING) {
            return false;
        }

        $request->loadMissing('leaveType');

        if (! $request->leaveType?->requires_proof) {
            return false;
        }

        if (! $request->from_date?->equalTo($request->to_date)) {
            return false;
        }

        return $this->hasFullAccess()
            || $this->isHrManager()
            || $this->hasPermission('leave.manage');
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

        return $this->hasPermission('leave.manage');
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

    public function canManageProjects(): bool
    {
        return $this->hasPermission('projects.manage');
    }

    public function canViewProjects(): bool
    {
        return $this->hasPermission('projects.view')
            || $this->hasPermission('projects.manage');
    }

    public function canSubmitTimesheets(): bool
    {
        return $this->company_id
            && $this->employee
            && $this->hasPermission('timesheets.submit');
    }

    public function canReviewTeamTimesheets(): bool
    {
        if (! $this->company_id) {
            return false;
        }

        if ($this->hasPermission('projects.manage') || $this->hasPermission('attendance.manage')) {
            return true;
        }

        return app(TimesheetService::class)->reviewableEmployeeIds($this) !== [];
    }

    public function canAccessTimesheets(): bool
    {
        return $this->hasFullAccess()
            || $this->canSubmitTimesheets()
            || $this->canReviewTeamTimesheets();
    }

    public function canApplyExpenses(): bool
    {
        if (! $this->company_id) {
            return false;
        }

        if (! app(EmployeeAccessService::class)->linkedEmployee($this)) {
            return false;
        }

        return $this->hasPermission('expenses.apply');
    }

    public function canEditOwnExpense(Expense $expense): bool
    {
        if ((int) $expense->company_id !== (int) $this->company_id) {
            return false;
        }

        if (! in_array($expense->status, [Expense::STATUS_DRAFT, Expense::STATUS_PENDING], true)) {
            return false;
        }

        if (! $this->canApplyExpenses()) {
            return false;
        }

        $employee = app(EmployeeAccessService::class)->linkedEmployee($this);

        if (! $employee || (int) $employee->id !== (int) $expense->employee_id) {
            return false;
        }

        if ($expense->is_independent) {
            return true;
        }

        $expense->loadMissing('expenseGroup');

        return $expense->expenseGroup
            && $expense->expenseGroup->status === ExpenseGroup::STATUS_DRAFT;
    }

    public function canEditOwnExpenseGroup(ExpenseGroup $group): bool
    {
        if ((int) $group->company_id !== (int) $this->company_id) {
            return false;
        }

        if ($group->status !== ExpenseGroup::STATUS_DRAFT) {
            return false;
        }

        if (! $this->canApplyExpenses()) {
            return false;
        }

        $employee = app(EmployeeAccessService::class)->linkedEmployee($this);

        return $employee && (int) $employee->id === (int) $group->employee_id;
    }

    public function canApproveExpenses(): bool
    {
        return $this->hasPermission('expenses.approve')
            || $this->hasPermission('expenses.manage');
    }

    public function canViewAllExpenses(): bool
    {
        return $this->hasPermission('expenses.manage');
    }

    public function canViewExpenses(): bool
    {
        if (! $this->company_id) {
            return false;
        }

        return $this->canViewAllExpenses()
            || $this->canApproveExpenses()
            || $this->hasPermission('expenses.apply');
    }

    public function canReviewExpense(Expense $expense): bool
    {
        if ((int) $expense->company_id !== (int) $this->company_id) {
            return false;
        }

        if (! $expense->is_independent || $expense->status !== Expense::STATUS_PENDING) {
            return false;
        }

        if ($this->employee && (int) $this->employee->id === (int) $expense->employee_id) {
            return false;
        }

        return $this->canApproveExpenses();
    }

    public function canReviewExpenseGroup(ExpenseGroup $group): bool
    {
        if ((int) $group->company_id !== (int) $this->company_id) {
            return false;
        }

        if ($group->status !== ExpenseGroup::STATUS_PENDING) {
            return false;
        }

        if ($this->employee && (int) $this->employee->id === (int) $group->employee_id) {
            return false;
        }

        return $this->canApproveExpenses();
    }

    public function canViewExpense(Expense $expense): bool
    {
        if ((int) $expense->company_id !== (int) $this->company_id) {
            return false;
        }

        if ($this->canViewAllExpenses()) {
            return true;
        }

        if ($this->employee && (int) $this->employee->id === (int) $expense->employee_id) {
            return true;
        }

        return false;
    }

    public function canViewExpenseGroup(ExpenseGroup $group): bool
    {
        if ((int) $group->company_id !== (int) $this->company_id) {
            return false;
        }

        if ($this->canViewAllExpenses()) {
            return true;
        }

        if ($this->employee && (int) $this->employee->id === (int) $group->employee_id) {
            return true;
        }

        return false;
    }

    public function canManageHiring(): bool
    {
        return $this->hasPermission('hiring.manage');
    }

    public function canCreateRequisition(): bool
    {
        return $this->canManageHiring()
            || $this->hasPermission('hiring.requisition.create');
    }

    public function canApproveRequisitions(): bool
    {
        return $this->canManageHiring()
            || $this->hasPermission('hiring.requisition.approve');
    }

    public function canReviewRequisition(\App\Models\JobRequisition $requisition): bool
    {
        if ((int) $requisition->company_id !== (int) $this->company_id) {
            return false;
        }

        if ($requisition->status !== \App\Models\JobRequisition::STATUS_PENDING) {
            return false;
        }

        if ($requisition->approver_user_id && (int) $requisition->approver_user_id === (int) $this->id) {
            return true;
        }

        return $this->canApproveRequisitions();
    }

    public function canInterviewCandidates(): bool
    {
        return $this->canManageHiring()
            || $this->hasPermission('hiring.interview');
    }

    public function canPublishCareers(): bool
    {
        return $this->canManageHiring()
            || $this->hasPermission('hiring.careers.publish');
    }

    public function canViewHiring(): bool
    {
        return $this->canManageHiring()
            || $this->canCreateRequisition()
            || $this->canApproveRequisitions()
            || $this->canInterviewCandidates()
            || $this->canPublishCareers();
    }

    public function canViewActivityLogs(): bool
    {
        return $this->hasPermission('logs.view');
    }

    public function canViewReports(): bool
    {
        return $this->hasPermission('reports.export');
    }

    public function canSeeMenu(string $key): bool
    {
        return app(MenuAccessService::class)->canSee($this, $key);
    }

    /** @param  array<int, string>  $keys */
    public function canSeeMenuSection(array $keys): bool
    {
        return app(MenuAccessService::class)->canSeeSection($this, $keys);
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->hasAssignedPermission($slug)) {
            return true;
        }

        if (str_ends_with($slug, '.view')) {
            $manageSlug = substr($slug, 0, -strlen('.view')).'.manage';

            return $this->hasAssignedPermission($manageSlug);
        }

        return false;
    }

    public function canManageRoles(): bool
    {
        return $this->hasPermission('roles.manage');
    }

    public function canAssignCompanyAdmin(): bool
    {
        return $this->hasPermission('employees.assign_admin');
    }

    private function hasAssignedPermission(string $slug): bool
    {
        return app(RoleService::class)->userHasPermission($this, $slug);
    }

    public function canSignIn(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $this->loadMissing(['employee', 'company']);

        if (! $this->company_id || $this->company?->status === 'inactive') {
            return false;
        }

        if (! $this->employee) {
            return true;
        }

        return $this->employee->status === 'active'
            && (int) $this->employee->user_id === (int) $this->id;
    }

    private function mustUseAdminForOwnHrReview(int $employeeId): bool
    {
        return $this->isHrManager()
            && ! $this->isCompanyAdmin()
            && $this->employee
            && (int) $this->employee->id === $employeeId;
    }
}
