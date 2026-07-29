<?php

namespace App\Services;

use App\Mail\EmployeeStatusChangedMail;
use App\Mail\ProbationCompletedMail;
use App\Mail\WorkflowActionMail;
use App\Models\AttendanceRegularizationRequest;
use App\Models\EmployeeDocument;
use App\Models\EmployeeProfilePhoto;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\WfhRequest;
use App\Models\AssetRequest;
use App\Models\AssetRequestItem;
use App\Models\Employee;
use App\Models\ExitCase;
use App\Models\ResignationRequest;
use App\Models\TimesheetComment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WorkflowNotificationService
{
    public function __construct(private WorkflowRecipientService $recipientService) {}

    public function notifyLeaveSubmitted(LeaveRequest $request, User $submittedBy): void
    {
        $request->loadMissing(['employee', 'leaveType', 'appliedBy']);

        $employee = $request->employee;

        if (! $employee) {
            return;
        }

        $dateSummary = $request->from_date?->equalTo($request->to_date)
            ? $request->from_date->format('d M Y')
            : ($request->from_date?->format('d M Y').' - '.$request->to_date?->format('d M Y'));

        $body = trim(sprintf(
            '%s applied for %s (%s). Reason: %s',
            $employee->full_name,
            $request->leaveType?->name ?? 'leave',
            $dateSummary,
            $request->reason ?: '—',
        ));

        $this->notifyStakeholders(
            employee: $employee,
            exclude: $submittedBy,
            type: UserNotification::TYPE_LEAVE_SUBMITTED,
            title: 'New leave request',
            body: $body,
            actionUrl: route('web.leave.show', $request->id),
            relatedType: 'leave_request',
            relatedId: $request->id,
            emailSubject: 'Leave request submitted – '.$employee->full_name,
            emailPurpose: 'This email notifies the employee\'s direct manager that a new leave request has been submitted. All request details are included below so they can evaluate coverage and approve or reject the request.',
            emailIntro: 'A leave request has been submitted and is waiting for the direct manager\'s review. Please read the employee, dates, duration, and reason below, then sign in to the HRMS portal to take action.',
            emailSummary: sprintf(
                '%s has applied for %s from %s, totaling %s day(s). Reason: %s',
                $employee->full_name,
                $request->leaveType?->name ?? 'leave',
                $dateSummary,
                $request->total_days,
                $request->reason ?: 'Not provided',
            ),
            emailDetails: [
                'Employee' => $employee->full_name,
                'Leave type' => $request->leaveType?->name ?? '—',
                'Dates' => $dateSummary,
                'Duration' => $request->total_days.' day(s)',
                'Reason' => $request->reason ?: '—',
            ],
            emailNextSteps: [
                'Verify the requested dates against team schedules and blackout periods.',
                'Review the leave type balance and reason provided by the employee.',
                'Approve or reject the request in HRMS and add remarks for the record.',
            ],
        );
    }

    public function notifyWfhSubmitted(WfhRequest $request, User $submittedBy): void
    {
        $request->loadMissing(['employee', 'appliedBy']);

        $employee = $request->employee;

        if (! $employee) {
            return;
        }

        $dateSummary = $request->from_date?->equalTo($request->to_date)
            ? $request->from_date->format('d M Y')
            : ($request->from_date?->format('d M Y').' - '.$request->to_date?->format('d M Y'));

        $body = trim(sprintf(
            '%s applied for Work From Home (%s). Reason: %s',
            $employee->full_name,
            $dateSummary,
            $request->reason ?: '—',
        ));

        $this->notifyStakeholders(
            employee: $employee,
            exclude: $submittedBy,
            type: UserNotification::TYPE_WFH_SUBMITTED,
            title: 'New WFH request',
            body: $body,
            actionUrl: route('web.wfh.show', $request->id),
            relatedType: 'wfh_request',
            relatedId: $request->id,
            emailSubject: 'WFH request submitted – '.$employee->full_name,
            emailPurpose: 'This email notifies the employee\'s direct manager that a Work From Home (WFH) request has been submitted. The complete request information is included so they can approve or reject it promptly.',
            emailIntro: 'A Work From Home request has been submitted and requires the direct manager\'s approval. Review the employee, dates, duration, and reason below before taking action in the HRMS portal.',
            emailSummary: sprintf(
                '%s has requested to work from home for %s (%s day(s)). Reason: %s',
                $employee->full_name,
                $dateSummary,
                $request->total_days,
                $request->reason ?: 'Not provided',
            ),
            emailDetails: [
                'Employee' => $employee->full_name,
                'Dates' => $dateSummary,
                'Duration' => $request->total_days.' day(s)',
                'Reason' => $request->reason ?: '—',
            ],
            emailNextSteps: [
                'Confirm the WFH dates align with team and company remote-work policy.',
                'Review the reason and any operational impact on the team.',
                'Approve or reject the request in HRMS with clear remarks.',
            ],
        );
    }

    public function notifyAssetRequestSubmitted(AssetRequest $request, User $submittedBy): void
    {
        $request->loadMissing(['employee', 'items.assetType', 'appliedBy']);

        $employee = $request->employee;

        if (! $employee) {
            return;
        }

        $assetName = $request->assetNamesLabel();

        $body = trim(sprintf(
            '%s requested %s. Reason: %s',
            $employee->full_name,
            $assetName,
            $request->reason ?: '—',
        ));

        $this->notifyStakeholders(
            employee: $employee,
            exclude: $submittedBy,
            type: UserNotification::TYPE_ASSET_REQUEST_SUBMITTED,
            title: 'New asset request',
            body: $body,
            actionUrl: route('web.asset-requests.show', $request->id),
            relatedType: 'asset_request',
            relatedId: $request->id,
            emailSubject: 'Asset request submitted – '.$employee->full_name,
            emailPurpose: 'This email notifies the employee\'s direct manager that company equipment or resources have been requested. Review the item and justification below before approving allocation.',
            emailIntro: 'An asset request has been submitted and requires the direct manager\'s review. The employee, requested item, and business reason are listed below.',
            emailSummary: sprintf('%s requested %s. Reason: %s', $employee->full_name, $assetName, $request->reason ?: 'Not provided'),
            emailDetails: [
                'Employee' => $employee->full_name,
                'Asset' => $assetName,
                'Reason' => $request->reason ?: '—',
            ],
            emailNextSteps: [
                'Confirm inventory availability for the requested asset.',
                'Validate the business justification provided by the employee.',
                'Approve or reject the request and notify the employee through HRMS.',
            ],
        );
    }

    public function notifyResignationSubmitted(ResignationRequest $request, User $submittedBy): void
    {
        $request->loadMissing(['employee', 'appliedBy']);
        $employee = $request->employee;

        if (! $employee) {
            return;
        }

        $body = trim(sprintf(
            '%s submitted a resignation request. Proposed LWD: %s. Reason: %s',
            $employee->full_name,
            $request->proposed_last_working_date?->format('d M Y') ?? '—',
            $request->reason ?: '—',
        ));

        $this->notifyStakeholders(
            employee: $employee,
            exclude: $submittedBy,
            type: UserNotification::TYPE_RESIGNATION_SUBMITTED,
            title: 'New resignation request',
            body: $body,
            actionUrl: route('web.offboarding.index'),
            relatedType: 'resignation_request',
            relatedId: $request->id,
            emailSubject: 'Resignation submitted – '.$employee->full_name,
            emailPurpose: 'This email notifies the employee\'s direct manager that a resignation request has been submitted. Early visibility helps plan knowledge transfer, exit formalities, and backfill timing.',
            emailIntro: 'A resignation request has been submitted to the employee\'s direct manager. Please review the employee details, proposed last working date, and reason below.',
            emailSummary: sprintf(
                '%s submitted a resignation with proposed last working date %s. Reason: %s',
                $employee->full_name,
                $request->proposed_last_working_date?->format('d M Y') ?? 'Not specified',
                $request->reason ?: 'Not provided',
            ),
            emailDetails: [
                'Employee' => $employee->full_name,
                'Proposed LWD' => $request->proposed_last_working_date?->format('d M Y') ?? '—',
                'Reason' => $request->reason ?: '—',
            ],
            emailNextSteps: [
                'Review the resignation reason and proposed last working date.',
                'Schedule a discussion with the employee and reporting manager if required.',
                'Initiate offboarding steps after the request is approved.',
            ],
        );
    }

    public function notifyResignationDecision(ResignationRequest $request, User $reviewedBy, string $decision): void
    {
        $request->loadMissing(['employee', 'appliedBy', 'exitCase']);
        $employee = $request->employee;

        if (! $employee) {
            return;
        }

        $approved = $decision === 'approved';
        $statusLabel = $approved ? 'approved' : 'rejected';
        $body = sprintf(
            'Your resignation request has been %s by %s.',
            $statusLabel,
            $reviewedBy->name,
        );

        $details = [
            'Status' => ucfirst($statusLabel),
            'Reviewed by' => $reviewedBy->name,
        ];

        if ($approved && $request->approved_last_working_date) {
            $details['Last working date'] = $request->approved_last_working_date->format('d M Y');
        }

        if (filled($request->review_notes)) {
            $details['Remarks'] = $request->review_notes;
        }

        $this->notifyApplicant(
            applicant: $request->appliedBy ?? $employee->user,
            companyId: (int) $employee->company_id,
            type: UserNotification::TYPE_RESIGNATION_DECISION,
            title: 'Resignation '.$statusLabel,
            body: $body,
            actionUrl: $approved && $request->exitCase
                ? route('web.offboarding.show', $request->exitCase->id)
                : route('web.offboarding.index'),
            relatedType: 'resignation_request',
            relatedId: $request->id,
            emailSubject: 'Resignation '.$statusLabel,
            emailPurpose: 'This email confirms the outcome of your resignation request. It includes the reviewer decision, approved last working date if applicable, and any remarks recorded by HR or management.',
            emailIntro: 'Your resignation request has been '.$statusLabel.' by '.$reviewedBy->name.'. Please review the decision details below and follow the next steps for exit formalities if approved.',
            emailSummary: $body,
            emailDetails: $details,
            emailNextSteps: $approved ? [
                'Review your approved last working date and offboarding checklist.',
                'Coordinate handover activities with your manager.',
                'Complete exit formalities shown in the offboarding module.',
            ] : [
                'Review the remarks provided by the reviewer.',
                'Speak with HR or your manager if you need clarification.',
                'Submit an updated request if your circumstances have changed.',
            ],
        );
    }

    public function notifyOffboardingCompleted(ExitCase $exitCase, User $processedBy): void
    {
        $exitCase->loadMissing(['employee', 'resignationRequest']);
        $employee = $exitCase->employee;

        if (! $employee) {
            return;
        }

        $body = sprintf(
            'Offboarding for %s has been completed. Last working date: %s.',
            $employee->full_name,
            $exitCase->last_working_date?->format('d M Y') ?? '—',
        );

        $this->notifyApplicant(
            applicant: $employee->user,
            companyId: (int) $employee->company_id,
            type: UserNotification::TYPE_OFFBOARDING_COMPLETED,
            title: 'Offboarding completed',
            body: $body,
            actionUrl: route('web.offboarding.show', $exitCase->id),
            relatedType: 'exit_case',
            relatedId: $exitCase->id,
            emailSubject: 'Offboarding completed – '.$employee->full_name,
            emailPurpose: 'This email confirms that your employee offboarding process has been fully completed in HRMS. It summarizes your last working date and who processed the exit.',
            emailIntro: 'Your offboarding for '.$employee->full_name.' has been marked complete. This message serves as a confirmation record of your exit processing.',
            emailSummary: $body,
            emailDetails: [
                'Last working date' => $exitCase->last_working_date?->format('d M Y') ?? '—',
                'Processed by' => $processedBy->name,
            ],
            emailNextSteps: [
                'Retain this email for your personal records.',
                'Return any pending company assets if not already handed over.',
                'Contact HR if any clearance item remains unresolved.',
            ],
        );
    }

    public function notifyOffboardingInitiated(ExitCase $exitCase, ?User $initiatedBy = null): void
    {
        $exitCase->loadMissing(['employee.user']);
        $employee = $exitCase->employee;

        if (! $employee) {
            return;
        }

        $lwd = $exitCase->last_working_date?->format('d M Y') ?? '—';
        $exitTypeLabel = ucfirst(str_replace('_', ' ', (string) $exitCase->exit_type));
        $body = sprintf(
            'Your offboarding process has started. Last working date: %s. Exit type: %s.',
            $lwd,
            $exitTypeLabel,
        );

        $this->notifyApplicant(
            applicant: $employee->user,
            companyId: (int) $employee->company_id,
            type: UserNotification::TYPE_OFFBOARDING_INITIATED,
            title: 'Offboarding started',
            body: $body,
            actionUrl: route('web.offboarding.show', $exitCase->id),
            relatedType: 'exit_case',
            relatedId: $exitCase->id,
            emailSubject: 'Offboarding process started – '.$employee->full_name,
            emailPurpose: 'This email confirms that your employee offboarding process has been initiated in HRMS. It includes your last working date and links to your exit checklist (clearance, asset return, exit survey, and F&F settlement).',
            emailIntro: 'Your offboarding at '.$employee->company?->name.' has been initiated'.($initiatedBy ? ' by '.$initiatedBy->name : '').'. Please review the details below and complete the required steps before your last working date.',
            emailSummary: $body,
            emailDetails: [
                'Employee' => $employee->full_name,
                'Exit type' => $exitTypeLabel,
                'Last working date' => $lwd,
                'Initiated by' => $initiatedBy?->name ?? 'HR',
            ],
            emailNextSteps: [
                'Open your offboarding checklist in HRMS.',
                'Complete the exit survey and coordinate asset returns.',
                'Work with HR on clearance and full & final settlement.',
            ],
        );
    }

    public function notifyEmployeeStatusChanged(
        Employee $employee,
        string $previousStatus,
        string $newStatus,
        ?User $changedBy = null,
    ): void {
        if ($previousStatus === $newStatus || ! filled($employee->email)) {
            return;
        }

        $employee->loadMissing(['user', 'company']);
        $statusLabel = $newStatus === 'active' ? 'reactivated' : 'deactivated';
        $body = sprintf(
            'Your employee account has been %s in HRMS.',
            $statusLabel,
        );

        if ($employee->user) {
            $this->persistNotification(
                companyId: (int) $employee->company_id,
                userId: $employee->user->id,
                type: UserNotification::TYPE_EMPLOYEE_STATUS_CHANGED,
                title: 'Account '.ucfirst($statusLabel),
                body: $body,
                actionUrl: route('web.profile'),
                relatedType: 'employee',
                relatedId: (int) $employee->id,
            );
        }

        try {
            Mail::to($employee->email)->send(new EmployeeStatusChangedMail(
                employee: $employee,
                previousStatus: $previousStatus,
                newStatus: $newStatus,
                changedBy: $changedBy,
            ));
        } catch (\Throwable $exception) {
            Log::warning('Employee status change email failed.', [
                'employee_id' => $employee->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function notifyProbationCompleted(Employee $employee, ?User $processedBy = null): void
    {
        $employee->loadMissing(['user', 'company', 'manager.user', 'role']);
        $endDate = $employee->probation_end_date?->format('d M Y') ?? '—';
        $body = sprintf(
            '%s has completed probation. Probation status updated to Confirmed (end date: %s).',
            $employee->full_name,
            $endDate,
        );
        $employeeProfileUrl = route('web.employees.show', $employee->id);

        if ($employee->user) {
            $this->persistNotification(
                companyId: (int) $employee->company_id,
                userId: $employee->user->id,
                type: UserNotification::TYPE_PROBATION_COMPLETED,
                title: 'Probation completed',
                body: 'Your probation period has ended and your employment is now confirmed.',
                actionUrl: route('web.profile'),
                relatedType: 'employee',
                relatedId: (int) $employee->id,
            );
        }

        if (filled($employee->email)) {
            try {
                Mail::to($employee->email)->send(new ProbationCompletedMail($employee));
            } catch (\Throwable $exception) {
                Log::warning('Probation completed email failed.', [
                    'employee_id' => $employee->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $stakeholderDetails = [
            'Employee' => $employee->full_name,
            'Employee code' => $employee->employee_code ?? '—',
            'Probation end date' => $endDate,
            'New status' => 'Confirmed',
            'Designation' => $employee->designation ?? '—',
        ];

        if ($processedBy) {
            $stakeholderDetails['Processed by'] = $processedBy->name;
        }

        $this->notifyProbationStakeholders(
            employee: $employee,
            type: UserNotification::TYPE_PROBATION_COMPLETED,
            title: 'Probation completed',
            body: $body,
            actionUrl: $employeeProfileUrl,
            emailSubject: 'Probation completed – '.$employee->full_name,
            emailIntro: 'An employee has completed their probation period. Their probation status has been automatically updated to Confirmed in HRMS.',
            emailPurpose: 'This email notifies HR and the reporting manager that an employee\'s probation period has ended and their employment has been confirmed.',
            emailSummary: $body,
            emailDetails: $stakeholderDetails,
            emailNextSteps: [
                'Review the employee\'s performance during probation.',
                'Update compensation or role details in HRMS if applicable.',
                'Schedule a confirmation discussion with the employee if not already done.',
            ],
        );
    }

    public function notifyProbationEndingSoon(Employee $employee): void
    {
        $employee->loadMissing(['company', 'manager.user', 'role']);
        $endDate = $employee->probation_end_date?->format('d M Y') ?? '—';
        $daysRemaining = $employee->probation_end_date
            ? max(0, (int) now()->startOfDay()->diffInDays($employee->probation_end_date->copy()->startOfDay(), false))
            : 0;
        $body = sprintf(
            '%s\'s probation ends on %s (%d day(s) remaining).',
            $employee->full_name,
            $endDate,
            $daysRemaining,
        );
        $employeeProfileUrl = route('web.employees.show', $employee->id);

        $this->notifyProbationStakeholders(
            employee: $employee,
            type: UserNotification::TYPE_PROBATION_ENDING_SOON,
            title: 'Probation ending soon',
            body: $body,
            actionUrl: $employeeProfileUrl,
            emailSubject: 'Probation ending soon – '.$employee->full_name,
            emailIntro: 'An employee\'s probation period is approaching its end date. Review their performance and confirm or extend probation before the deadline.',
            emailPurpose: 'This email is an advance reminder that an employee\'s probation period will end soon. HR and the reporting manager should evaluate confirmation, extension, or further action.',
            emailSummary: $body,
            emailDetails: [
                'Employee' => $employee->full_name,
                'Employee code' => $employee->employee_code ?? '—',
                'Probation end date' => $endDate,
                'Days remaining' => (string) $daysRemaining,
                'Current status' => ucfirst(str_replace('_', ' ', (string) $employee->probation_status)),
            ],
            emailNextSteps: [
                'Review the employee\'s performance and attendance during probation.',
                'Confirm, extend, or update probation status in HRMS before the end date.',
                'Discuss outcomes with the employee and document any decisions.',
            ],
        );
    }

    /** @param  array<string, string>  $emailDetails */
    /** @param  array<int, string>  $emailNextSteps */
    private function notifyProbationStakeholders(
        Employee $employee,
        string $type,
        string $title,
        string $body,
        string $actionUrl,
        string $emailSubject,
        string $emailIntro,
        string $emailPurpose,
        string $emailSummary,
        array $emailDetails,
        array $emailNextSteps,
    ): void {
        $recipients = $this->recipientService
            ->hrRecipientsForCompany((int) $employee->company_id)
            ->merge($this->recipientService->directManagerRecipientsForEmployee($employee))
            ->unique('id')
            ->values();

        foreach ($recipients as $recipient) {
            $this->persistNotification(
                companyId: (int) $employee->company_id,
                userId: $recipient->id,
                type: $type,
                title: $title,
                body: $body,
                actionUrl: $actionUrl,
                relatedType: 'employee',
                relatedId: (int) $employee->id,
            );

            $this->sendWorkflowEmail(
                recipient: $recipient,
                subjectLine: $emailSubject,
                intro: $emailIntro,
                details: $emailDetails,
                actionUrl: $actionUrl,
                actionLabel: 'View employee',
                purpose: $emailPurpose,
                summary: $emailSummary,
                nextSteps: $emailNextSteps,
                logContext: ['type' => $type, 'related_id' => $employee->id, 'channel' => 'probation'],
            );
        }
    }

    public function notifyDocumentVerification(EmployeeDocument $document, User $submittedBy): void
    {
        if ($document->status !== 'pending') {
            return;
        }

        $document->loadMissing(['employee', 'documentType', 'uploadedBy']);

        $employee = $document->employee;

        if (! $employee) {
            return;
        }

        $body = sprintf(
            '%s uploaded a %s document (%s) for verification.',
            $employee->full_name,
            $document->documentType?->name ?? 'document',
            $document->original_name,
        );

        $this->notifyDocumentReviewers(
            companyId: (int) $employee->company_id,
            exclude: $submittedBy,
            type: UserNotification::TYPE_DOCUMENT_VERIFICATION,
            title: 'Document verification',
            body: $body,
            actionUrl: route('web.requests.show', ['category' => 'document', 'id' => $document->id]),
            relatedType: 'employee_document',
            relatedId: $document->id,
            emailSubject: 'Document verification requested – '.$employee->full_name,
            emailPurpose: 'This email notifies HR or document verifiers that an employee uploaded a document that requires review. The file name and document type are included for quick verification.',
            emailIntro: 'An employee document has been uploaded and is pending verification. Please confirm authenticity and compliance using the details below.',
            emailSummary: $body,
            emailDetails: [
                'Employee' => $employee->full_name,
                'Document type' => $document->documentType?->name ?? '—',
                'File' => $document->original_name,
            ],
            emailNextSteps: [
                'Open the document in HRMS and verify legibility and validity.',
                'Approve the document if it meets policy requirements.',
                'Reject with clear notes if the upload is incomplete or invalid.',
            ],
        );
    }

    public function notifyRegularizationSubmitted(
        AttendanceRegularizationRequest $request,
        User $submittedBy,
        int $dayCount = 1,
    ): void {
        $request->loadMissing(['employee', 'appliedBy']);

        $employee = $request->employee;

        if (! $employee) {
            return;
        }

        $dateLabel = $dayCount > 1
            ? $dayCount.' day(s) starting '.$request->attendance_date?->format('d M Y')
            : $request->attendance_date?->format('d M Y');

        $body = trim(sprintf(
            '%s submitted an attendance regularization request for %s. Reason: %s',
            $employee->full_name,
            $dateLabel,
            $request->reason ?: '—',
        ));

        $this->notifyHrAndAdminStakeholders(
            companyId: (int) $employee->company_id,
            exclude: $submittedBy,
            type: UserNotification::TYPE_REGULARIZATION_SUBMITTED,
            title: 'Attendance regularization request',
            body: $body,
            actionUrl: route('web.requests.show', ['category' => 'regularization', 'id' => $request->id]),
            relatedType: 'attendance_regularization',
            relatedId: $request->id,
            emailSubject: 'Attendance regularization submitted – '.$employee->full_name,
            emailPurpose: 'This email notifies HR administrators that an employee submitted an attendance regularization request to correct missed or incorrect attendance records.',
            emailIntro: 'An attendance regularization request needs HR review. Verify the affected date(s) and employee explanation before approving.',
            emailSummary: $body,
            emailDetails: [
                'Employee' => $employee->full_name,
                'Date(s)' => $dateLabel,
                'Reason' => $request->reason ?: '—',
            ],
            emailNextSteps: [
                'Cross-check attendance logs for the listed date(s).',
                'Confirm the regularization reason with policy guidelines.',
                'Approve or reject the correction in HRMS with remarks.',
            ],
        );
    }

    public function notifyTimesheetSubmitted(
        Employee $employee,
        User $submittedBy,
        string $workDate,
        EloquentCollection $entries,
    ): void {
        $totalHours = round($entries->sum('hours'), 2);
        $projectNames = $entries->loadMissing('project')->pluck('project.name')->filter()->unique()->implode(', ');
        $dateLabel = Carbon::parse($workDate)->format('d M Y');

        $body = sprintf(
            '%s submitted a daily report for %s (%s hour(s) across %s).',
            $employee->full_name,
            $dateLabel,
            $totalHours,
            $projectNames ?: 'projects',
        );

        $recipients = $this->recipientService
            ->managersInChainRecipientsForEmployee($employee, $submittedBy);

        foreach ($recipients as $recipient) {
            $this->persistNotification(
                companyId: (int) $employee->company_id,
                userId: $recipient->id,
                type: UserNotification::TYPE_TIMESHEET_SUBMITTED,
                title: 'Daily report submitted',
                body: $body,
                actionUrl: route('web.timesheets.index').'?employee_id='.$employee->id.'&work_date='.$workDate,
                relatedType: 'timesheet_day',
                relatedId: (int) $employee->id,
            );

            try {
                $this->sendWorkflowEmail(
                    recipient: $recipient,
                    subjectLine: 'Daily report submitted – '.$employee->full_name,
                    intro: 'A team member has submitted their daily status report. The summary below includes hours logged and projects covered so you can review progress without opening the portal first.',
                    details: [
                        'Employee' => $employee->full_name,
                        'Date' => $dateLabel,
                        'Total hours' => (string) $totalHours,
                        'Projects' => $projectNames ?: '—',
                    ],
                    actionUrl: route('web.timesheets.index').'?employee_id='.$employee->id.'&work_date='.$workDate,
                    actionLabel: 'View report',
                    purpose: 'This email notifies managers that an employee submitted a daily timesheet or status report. It includes logged hours and project coverage for your review.',
                    summary: $body,
                    nextSteps: [
                        'Review the hours and projects listed above.',
                        'Open the full report in HRMS if you need task-level detail.',
                        'Add feedback or comments if follow-up is required.',
                    ],
                    logContext: [
                        'type' => UserNotification::TYPE_TIMESHEET_SUBMITTED,
                        'related_id' => $employee->id,
                        'channel' => 'timesheet',
                    ],
                );
            } catch (\Throwable $exception) {
                Log::warning('Workflow notification email failed.', [
                    'recipient_id' => $recipient->id,
                    'type' => UserNotification::TYPE_TIMESHEET_SUBMITTED,
                    'related_id' => $employee->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    public function notifyTimesheetComment(
        TimesheetComment $comment,
        User $author,
        bool $isReply,
    ): void {
        $comment->loadMissing(['employee.user', 'project']);
        $employee = $comment->employee;

        if (! $employee) {
            return;
        }

        $workDate = $comment->work_date?->format('Y-m-d') ?? '';
        $dateLabel = $workDate !== '' ? Carbon::parse($workDate)->format('d M Y') : 'the selected date';
        $projectName = $comment->project?->name ?? 'project';
        $actionUrl = route('web.timesheets.index').'?employee_id='.$employee->id.'&work_date='.$workDate;
        $authorIsEmployee = $employee->user && (int) $employee->user->id === (int) $author->id;

        if ($authorIsEmployee) {
            $recipients = $this->recipientService
                ->managersInChainRecipientsForEmployee($employee, $author);

            $title = 'Reply on daily report';
            $intro = $employee->full_name.' replied to a comment on their daily report.';
        } else {
            $employeeUser = $employee->user;

            if (! $employeeUser || (int) $employeeUser->id === (int) $author->id) {
                return;
            }

            $recipients = collect([$employeeUser]);
            $title = $isReply ? 'New reply on your daily report' : 'Feedback on your daily report';
            $intro = $author->name.' left a comment on your '.$projectName.' submission for '.$dateLabel.'.';
        }

        foreach ($recipients as $recipient) {
            $this->persistNotification(
                companyId: (int) $employee->company_id,
                userId: $recipient->id,
                type: UserNotification::TYPE_TIMESHEET_COMMENT,
                title: $title,
                body: $intro,
                actionUrl: $actionUrl,
                relatedType: 'timesheet_comment',
                relatedId: (int) $comment->id,
            );

            try {
                $this->sendWorkflowEmail(
                    recipient: $recipient,
                    subjectLine: $title.' – '.$employee->full_name,
                    intro: $intro,
                    details: [
                        'Employee' => $employee->full_name,
                        'Date' => $dateLabel,
                        'Project' => $projectName,
                        'Comment' => $comment->body,
                    ],
                    actionUrl: $actionUrl,
                    actionLabel: 'View report',
                    purpose: $authorIsEmployee
                        ? 'This email notifies managers that an employee replied to feedback on their daily report. The full comment is included below.'
                        : 'This email notifies an employee that their manager left feedback on a daily report submission. The complete comment is included below.',
                    summary: $intro,
                    nextSteps: [
                        'Read the comment text included in this email.',
                        'Open the daily report in HRMS to view context and respond if needed.',
                        'Continue the discussion until the report is finalized.',
                    ],
                    logContext: [
                        'type' => UserNotification::TYPE_TIMESHEET_COMMENT,
                        'related_id' => $comment->id,
                        'channel' => 'timesheet_comment',
                    ],
                );
            } catch (\Throwable $exception) {
                Log::warning('Workflow notification email failed.', [
                    'recipient_id' => $recipient->id,
                    'type' => UserNotification::TYPE_TIMESHEET_COMMENT,
                    'related_id' => $comment->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    public function notifyLeaveDecision(LeaveRequest $request, User $reviewedBy, string $decision): void
    {
        $request->loadMissing(['employee', 'leaveType', 'appliedBy']);

        $employee = $request->employee;

        if (! $employee) {
            return;
        }

        $approved = $decision === 'approved';
        $dateSummary = $request->from_date?->equalTo($request->to_date)
            ? $request->from_date->format('d M Y')
            : ($request->from_date?->format('d M Y').' - '.$request->to_date?->format('d M Y'));

        $statusLabel = $approved ? 'approved' : 'rejected';
        $body = sprintf(
            'Your %s leave request for %s has been %s by %s.',
            $request->leaveType?->name ?? 'leave',
            $dateSummary,
            $statusLabel,
            $reviewedBy->name,
        );

        $details = [
            'Leave type' => $request->leaveType?->name ?? '—',
            'Dates' => $dateSummary,
            'Duration' => $request->total_days.' day(s)',
            'Status' => ucfirst($statusLabel),
            'Reviewed by' => $reviewedBy->name,
        ];

        if (! $approved && filled($request->review_notes)) {
            $details['Reason'] = $request->review_notes;
        }

        $this->notifyApplicant(
            applicant: $this->applicantForLeave($request),
            companyId: (int) $employee->company_id,
            type: UserNotification::TYPE_LEAVE_DECISION,
            title: 'Leave request '.$statusLabel,
            body: $body,
            actionUrl: route('web.leave.show', $request->id),
            relatedType: 'leave_request',
            relatedId: $request->id,
            emailSubject: 'Leave request '.$statusLabel.' – '.$dateSummary,
            emailPurpose: 'This email confirms the decision on your leave request. It includes leave type, dates, duration, reviewer name, and remarks if the request was rejected.',
            emailIntro: 'Your leave request has been '.$statusLabel.' by '.$reviewedBy->name.'. Please review the complete decision summary below.',
            emailSummary: $body,
            emailDetails: $details,
            emailNextSteps: $approved ? [
                'Add the approved leave dates to your calendar.',
                'Coordinate handover with your team before your leave begins.',
                'View the request in HRMS for your records.',
            ] : [
                'Review the reason or remarks provided by the reviewer.',
                'Contact your manager or HR if you need clarification.',
                'Submit a revised request if your dates or leave type can change.',
            ],
        );
    }

    public function notifyWfhDecision(WfhRequest $request, User $reviewedBy, string $decision): void
    {
        $request->loadMissing(['employee', 'appliedBy']);

        $employee = $request->employee;

        if (! $employee) {
            return;
        }

        $approved = $decision === 'approved';
        $dateSummary = $request->from_date?->equalTo($request->to_date)
            ? $request->from_date->format('d M Y')
            : ($request->from_date?->format('d M Y').' - '.$request->to_date?->format('d M Y'));

        $statusLabel = $approved ? 'approved' : 'rejected';
        $body = sprintf(
            'Your Work From Home request for %s has been %s by %s.',
            $dateSummary,
            $statusLabel,
            $reviewedBy->name,
        );

        $details = [
            'Dates' => $dateSummary,
            'Duration' => $request->total_days.' day(s)',
            'Status' => ucfirst($statusLabel),
            'Reviewed by' => $reviewedBy->name,
        ];

        if (filled($request->review_notes)) {
            $details['Remarks'] = $request->review_notes;
        }

        $this->notifyApplicant(
            applicant: $this->applicantForWfh($request),
            companyId: (int) $employee->company_id,
            type: UserNotification::TYPE_WFH_DECISION,
            title: 'WFH request '.$statusLabel,
            body: $body,
            actionUrl: route('web.wfh.show', $request->id),
            relatedType: 'wfh_request',
            relatedId: $request->id,
            emailSubject: 'WFH request '.$statusLabel.' – '.$dateSummary,
            emailPurpose: 'This email confirms the decision on your Work From Home request, including dates, duration, reviewer, and any remarks.',
            emailIntro: 'Your Work From Home request has been '.$statusLabel.' by '.$reviewedBy->name.'. The full decision details are listed below.',
            emailSummary: $body,
            emailDetails: $details,
            emailNextSteps: $approved ? [
                'Ensure you have remote-work access and equipment ready for the approved dates.',
                'Stay reachable on official channels during WFH days.',
                'Follow company security and availability guidelines while remote.',
            ] : [
                'Review the remarks provided by the reviewer.',
                'Discuss alternative dates or arrangements with your manager if needed.',
            ],
        );
    }

    public function notifyAssetRequestDecision(AssetRequest $request, User $reviewedBy, string $decision): void
    {
        $request->loadMissing(['employee', 'items.assetType', 'appliedBy']);

        $employee = $request->employee;

        if (! $employee) {
            return;
        }

        $approved = $decision === 'approved';
        $assetName = $request->assetNamesLabel();
        $statusLabel = $approved ? 'approved' : 'rejected';
        $body = sprintf(
            'Your request for %s has been %s by %s.',
            $assetName,
            $statusLabel,
            $reviewedBy->name,
        );

        $details = [
            'Asset' => $assetName,
            'Status' => ucfirst($statusLabel),
            'Reviewed by' => $reviewedBy->name,
        ];

        if (filled($request->review_notes)) {
            $details['Remarks'] = $request->review_notes;
        }

        $this->notifyApplicant(
            applicant: $this->applicantForAssetRequest($request),
            companyId: (int) $employee->company_id,
            type: UserNotification::TYPE_ASSET_REQUEST_DECISION,
            title: 'Asset request '.$statusLabel,
            body: $body,
            actionUrl: route('web.asset-requests.show', $request->id),
            relatedType: 'asset_request',
            relatedId: $request->id,
            emailSubject: 'Asset request '.$statusLabel.' – '.$assetName,
            emailPurpose: 'This email confirms the decision on your asset request, including the item requested, reviewer, status, and any remarks.',
            emailIntro: 'Your asset request has been '.$statusLabel.' by '.$reviewedBy->name.'. See the full summary below.',
            emailSummary: $body,
            emailDetails: $details,
            emailNextSteps: $approved ? [
                'Coordinate with IT or admin for asset pickup or delivery.',
                'Acknowledge receipt once the asset is issued to you.',
            ] : [
                'Review the remarks to understand why the request was not approved.',
                'Contact HR or IT if you need an alternative arrangement.',
            ],
        );
    }

    public function notifyAssetRequestItemDecision(
        AssetRequest $request,
        AssetRequestItem $item,
        User $reviewedBy,
        string $decision,
    ): void {
        $request->loadMissing(['employee', 'appliedBy']);
        $item->loadMissing('assetType');

        $employee = $request->employee;

        if (! $employee) {
            return;
        }

        $approved = $decision === 'approved';
        $assetName = $item->assetType?->name ?? 'Asset';
        $statusLabel = $approved ? 'approved' : 'rejected';
        $body = sprintf(
            'Your request for %s has been %s by %s.',
            $assetName,
            $statusLabel,
            $reviewedBy->name,
        );

        $details = [
            'Asset' => $assetName,
            'Status' => ucfirst($statusLabel),
            'Reviewed by' => $reviewedBy->name,
        ];

        if (filled($item->review_notes)) {
            $details['Remarks'] = $item->review_notes;
        }

        $this->notifyApplicant(
            applicant: $this->applicantForAssetRequest($request),
            companyId: (int) $employee->company_id,
            type: UserNotification::TYPE_ASSET_REQUEST_DECISION,
            title: 'Asset request '.$statusLabel,
            body: $body,
            actionUrl: route('web.asset-requests.show', $request->id),
            relatedType: 'asset_request',
            relatedId: $request->id,
            emailSubject: 'Asset request '.$statusLabel.' – '.$assetName,
            emailPurpose: 'This email confirms the decision on one item within your asset request. Each line item may be approved or rejected independently.',
            emailIntro: 'An item in your asset request has been '.$statusLabel.' by '.$reviewedBy->name.'. Review the item details below.',
            emailSummary: $body,
            emailDetails: $details,
            emailNextSteps: [
                'Check whether other items in the same request remain pending.',
                'Follow up with IT or admin for approved items.',
                'Contact HR if you need clarification on a rejection.',
            ],
        );
    }

    public function notifyDocumentDecision(EmployeeDocument $document, User $reviewedBy, string $decision): void
    {
        $document->loadMissing(['employee.user', 'documentType', 'uploadedBy']);

        $employee = $document->employee;

        if (! $employee) {
            return;
        }

        $approved = $decision === 'approved';
        $statusLabel = $approved ? 'approved' : 'rejected';
        $body = sprintf(
            'Your %s document (%s) has been %s by %s.',
            $document->documentType?->name ?? 'document',
            $document->original_name,
            $statusLabel,
            $reviewedBy->name,
        );

        $details = [
            'Document type' => $document->documentType?->name ?? '—',
            'File' => $document->original_name,
            'Status' => ucfirst($statusLabel),
            'Reviewed by' => $reviewedBy->name,
        ];

        if (! $approved && filled($document->notes)) {
            $details['Reason'] = $document->notes;
        }

        $this->notifyApplicant(
            applicant: $document->uploadedBy ?? $employee->user,
            companyId: (int) $employee->company_id,
            type: UserNotification::TYPE_DOCUMENT_DECISION,
            title: 'Document '.$statusLabel,
            body: $body,
            actionUrl: route('web.requests.show', ['category' => 'document', 'id' => $document->id]),
            relatedType: 'employee_document',
            relatedId: $document->id,
            emailSubject: 'Document '.$statusLabel.' – '.$document->original_name,
            emailPurpose: 'This email confirms the verification outcome for a document you uploaded to HRMS, including document type, file name, reviewer, and rejection reason if applicable.',
            emailIntro: 'Your document verification request has been '.$statusLabel.' by '.$reviewedBy->name.'. See the complete details below.',
            emailSummary: $body,
            emailDetails: $details,
            emailNextSteps: $approved ? [
                'No further action is required for this document.',
                'Keep a personal copy if needed for your records.',
            ] : [
                'Review the rejection reason carefully.',
                'Upload a corrected document version if required.',
                'Contact HR if you need help meeting document standards.',
            ],
        );
    }

    public function notifyRegularizationDecision(
        AttendanceRegularizationRequest $request,
        User $reviewedBy,
        string $decision,
    ): void {
        $request->loadMissing(['employee', 'appliedBy']);

        $employee = $request->employee;

        if (! $employee) {
            return;
        }

        $approved = $decision === 'approved';
        $statusLabel = $approved ? 'approved' : 'rejected';
        $dateLabel = $request->attendance_date?->format('d M Y') ?? '—';

        $body = sprintf(
            'Your attendance regularization request for %s has been %s by %s.',
            $dateLabel,
            $statusLabel,
            $reviewedBy->name,
        );

        $details = [
            'Date' => $dateLabel,
            'Status' => ucfirst($statusLabel),
            'Reviewed by' => $reviewedBy->name,
        ];

        if (! $approved && filled($request->review_notes)) {
            $details['Reason'] = $request->review_notes;
        }

        $this->notifyApplicant(
            applicant: $this->applicantForRegularization($request),
            companyId: (int) $employee->company_id,
            type: UserNotification::TYPE_REGULARIZATION_DECISION,
            title: 'Regularization '.$statusLabel,
            body: $body,
            actionUrl: route('web.requests.show', ['category' => 'regularization', 'id' => $request->id]),
            relatedType: 'attendance_regularization',
            relatedId: $request->id,
            emailSubject: 'Attendance regularization '.$statusLabel.' – '.$dateLabel,
            emailPurpose: 'This email confirms the decision on your attendance regularization request, including affected date, reviewer, and remarks if rejected.',
            emailIntro: 'Your attendance regularization request has been '.$statusLabel.' by '.$reviewedBy->name.'. The decision summary is below.',
            emailSummary: $body,
            emailDetails: $details,
            emailNextSteps: $approved ? [
                'Verify your attendance record reflects the correction in HRMS.',
                'Report any remaining discrepancies to HR promptly.',
            ] : [
                'Review the rejection reason provided.',
                'Submit supporting evidence or a revised request if appropriate.',
            ],
        );
    }

    public function notifyProfilePhotoSubmitted(EmployeeProfilePhoto $photo, User $submittedBy): void
    {
        if ($photo->status !== 'pending') {
            return;
        }

        $photo->loadMissing(['employee']);

        $employee = $photo->employee;

        if (! $employee) {
            return;
        }

        $body = sprintf(
            '%s submitted a profile photo for approval.',
            $employee->full_name,
        );

        $this->notifyDocumentReviewers(
            companyId: (int) $employee->company_id,
            exclude: $submittedBy,
            type: UserNotification::TYPE_PROFILE_PHOTO_SUBMITTED,
            title: 'Profile photo approval',
            body: $body,
            actionUrl: route('web.requests.show', ['category' => 'profile_photo', 'id' => $photo->id]),
            relatedType: 'employee_profile_photo',
            relatedId: $photo->id,
            emailSubject: 'Profile photo submitted – '.$employee->full_name,
            emailPurpose: 'This email notifies HR or profile approvers that an employee submitted a new profile photo that requires approval before it appears across HRMS.',
            emailIntro: 'An employee profile photo is pending approval. Review the submission details below and approve or reject in HRMS.',
            emailSummary: $body,
            emailDetails: [
                'Employee' => $employee->full_name,
                'Status' => 'Pending approval',
            ],
            emailNextSteps: [
                'Open the photo request in HRMS and verify it meets company guidelines.',
                'Approve if the photo is professional and clearly identifies the employee.',
                'Reject with guidance if the image does not meet policy.',
            ],
        );
    }

    public function notifyProfilePhotoDecision(EmployeeProfilePhoto $photo, User $reviewedBy, string $decision): void
    {
        $photo->loadMissing(['employee.user', 'submittedBy']);

        $employee = $photo->employee;

        if (! $employee) {
            return;
        }

        $approved = $decision === 'approved';
        $statusLabel = $approved ? 'approved' : 'rejected';
        $body = sprintf(
            'Your profile photo has been %s by %s.',
            $statusLabel,
            $reviewedBy->name,
        );

        $details = [
            'Status' => ucfirst($statusLabel),
            'Reviewed by' => $reviewedBy->name,
        ];

        if (! $approved && filled($photo->notes)) {
            $details['Reason'] = $photo->notes;
        }

        $this->notifyApplicant(
            applicant: $photo->submittedBy ?? $employee->user,
            companyId: (int) $employee->company_id,
            type: UserNotification::TYPE_PROFILE_PHOTO_DECISION,
            title: 'Profile photo '.$statusLabel,
            body: $body,
            actionUrl: route('web.profile'),
            relatedType: 'employee_profile_photo',
            relatedId: $photo->id,
            emailSubject: 'Profile photo '.$statusLabel,
            emailPurpose: 'This email confirms the outcome of your profile photo approval request, including reviewer and rejection reason if applicable.',
            emailIntro: 'Your profile photo request has been '.$statusLabel.' by '.$reviewedBy->name.'. See the decision details below.',
            emailSummary: $body,
            emailDetails: $details,
            actionLabel: 'View profile',
            emailNextSteps: $approved ? [
                'Your updated photo should now appear on your HRMS profile.',
                'Contact HR if the photo does not update within a few minutes.',
            ] : [
                'Review the rejection reason and upload a compliant photo.',
                'Ensure the image is clear, professional, and recent.',
            ],
        );
    }

    /** @param  array<string, string>  $emailDetails */
    /** @param  array<int, string>  $emailNextSteps */
    private function notifyApplicant(
        ?User $applicant,
        int $companyId,
        string $type,
        string $title,
        string $body,
        string $actionUrl,
        string $relatedType,
        int $relatedId,
        string $emailSubject,
        string $emailIntro,
        array $emailDetails,
        string $actionLabel = 'View details',
        string $emailPurpose = '',
        ?string $emailSummary = null,
        array $emailNextSteps = [],
    ): void {
        if (! $applicant || ! filled($applicant->email)) {
            return;
        }

        $this->persistNotification(
            companyId: $companyId,
            userId: $applicant->id,
            type: $type,
            title: $title,
            body: $body,
            actionUrl: $actionUrl,
            relatedType: $relatedType,
            relatedId: $relatedId,
        );

        $this->sendWorkflowEmail(
            recipient: $applicant,
            subjectLine: $emailSubject,
            intro: $emailIntro,
            details: $emailDetails,
            actionUrl: $actionUrl,
            actionLabel: $actionLabel,
            purpose: $emailPurpose ?: 'This email is an official update from your organization\'s HRMS platform regarding a workflow decision that affects you. All relevant details are included below.',
            summary: $emailSummary,
            nextSteps: $emailNextSteps,
            logContext: ['type' => $type, 'related_id' => $relatedId, 'channel' => 'applicant'],
        );
    }

    private function applicantForLeave(LeaveRequest $request): ?User
    {
        $request->loadMissing(['appliedBy', 'employee.user']);

        return $request->appliedBy ?? $request->employee?->user;
    }

    private function applicantForWfh(WfhRequest $request): ?User
    {
        $request->loadMissing(['appliedBy', 'employee.user']);

        return $request->appliedBy ?? $request->employee?->user;
    }

    private function applicantForAssetRequest(AssetRequest $request): ?User
    {
        $request->loadMissing(['appliedBy', 'employee.user']);

        return $request->appliedBy ?? $request->employee?->user;
    }

    private function applicantForRegularization(AttendanceRegularizationRequest $request): ?User
    {
        $request->loadMissing(['appliedBy', 'employee.user']);

        return $request->appliedBy ?? $request->employee?->user;
    }

    /** @param  array<string, string>  $emailDetails */
    /** @param  array<int, string>  $emailNextSteps */
    private function notifyDocumentReviewers(
        int $companyId,
        ?User $exclude,
        string $type,
        string $title,
        string $body,
        string $actionUrl,
        string $relatedType,
        int $relatedId,
        string $emailSubject,
        string $emailIntro,
        array $emailDetails,
        string $emailPurpose = '',
        ?string $emailSummary = null,
        array $emailNextSteps = [],
    ): void {
        $recipients = $this->recipientService->documentReviewRecipientsForCompany($companyId, $exclude);

        foreach ($recipients as $recipient) {
            $this->persistNotification(
                companyId: $companyId,
                userId: $recipient->id,
                type: $type,
                title: $title,
                body: $body,
                actionUrl: $actionUrl,
                relatedType: $relatedType,
                relatedId: $relatedId,
            );

            $this->sendWorkflowEmail(
                recipient: $recipient,
                subjectLine: $emailSubject,
                intro: $emailIntro,
                details: $emailDetails,
                actionUrl: $actionUrl,
                actionLabel: 'Review request',
                purpose: $emailPurpose ?: 'This email notifies HR or document reviewers that an employee submission requires review. Full details are included below.',
                summary: $emailSummary,
                nextSteps: $emailNextSteps,
                logContext: ['type' => $type, 'related_id' => $relatedId, 'channel' => 'document_review'],
            );
        }
    }

    /** @param  array<string, string>  $emailDetails */
    /** @param  array<int, string>  $emailNextSteps */
    private function notifyHrAndAdminStakeholders(
        int $companyId,
        ?User $exclude,
        string $type,
        string $title,
        string $body,
        string $actionUrl,
        string $relatedType,
        int $relatedId,
        string $emailSubject,
        string $emailIntro,
        array $emailDetails,
        string $emailPurpose = '',
        ?string $emailSummary = null,
        array $emailNextSteps = [],
    ): void {
        $recipients = $this->recipientService->hrAndAdminRecipientsForCompany($companyId, $exclude);

        foreach ($recipients as $recipient) {
            $this->persistNotification(
                companyId: $companyId,
                userId: $recipient->id,
                type: $type,
                title: $title,
                body: $body,
                actionUrl: $actionUrl,
                relatedType: $relatedType,
                relatedId: $relatedId,
            );

            $this->sendWorkflowEmail(
                recipient: $recipient,
                subjectLine: $emailSubject,
                intro: $emailIntro,
                details: $emailDetails,
                actionUrl: $actionUrl,
                actionLabel: 'Review request',
                purpose: $emailPurpose ?: 'This email notifies HR that an employee request requires review. Full details are included below.',
                summary: $emailSummary,
                nextSteps: $emailNextSteps,
                logContext: ['type' => $type, 'related_id' => $relatedId, 'channel' => 'hr_admin'],
            );
        }
    }

    /** @param  array<string, string>  $emailDetails */
    /** @param  array<int, string>  $emailNextSteps */
    private function notifyStakeholders(
        $employee,
        ?User $exclude,
        string $type,
        string $title,
        string $body,
        string $actionUrl,
        string $relatedType,
        int $relatedId,
        string $emailSubject,
        string $emailIntro,
        array $emailDetails,
        string $emailPurpose = '',
        ?string $emailSummary = null,
        array $emailNextSteps = [],
    ): void {
        $recipients = $this->recipientService->stakeholdersForEmployee($employee, $exclude);

        foreach ($recipients as $recipient) {
            $this->persistNotification(
                companyId: (int) $employee->company_id,
                userId: $recipient->id,
                type: $type,
                title: $title,
                body: $body,
                actionUrl: $actionUrl,
                relatedType: $relatedType,
                relatedId: $relatedId,
            );

            $this->sendWorkflowEmail(
                recipient: $recipient,
                subjectLine: $emailSubject,
                intro: $emailIntro,
                details: $emailDetails,
                actionUrl: $actionUrl,
                actionLabel: 'Review request',
                purpose: $emailPurpose ?: 'This email notifies the employee\'s direct manager that a request requires review. All details are included below.',
                summary: $emailSummary,
                nextSteps: $emailNextSteps,
                logContext: ['type' => $type, 'related_id' => $relatedId, 'channel' => 'stakeholder'],
            );
        }
    }

    /** @param  array<string, string>  $details */
    /** @param  array<int, string>  $nextSteps */
    /** @param  array<string, mixed>  $logContext */
    private function sendWorkflowEmail(
        User $recipient,
        string $subjectLine,
        string $intro,
        array $details,
        string $actionUrl,
        string $actionLabel,
        string $purpose,
        ?string $summary = null,
        array $nextSteps = [],
        array $logContext = [],
    ): void {
        if (! filled($recipient->email)) {
            return;
        }

        try {
            Mail::to($recipient->email)->send(new WorkflowActionMail(
                recipientName: $recipient->name,
                subjectLine: $subjectLine,
                intro: $intro,
                details: $details,
                actionUrl: $actionUrl,
                actionLabel: $actionLabel,
                purpose: $purpose,
                summary: $summary,
                nextSteps: $nextSteps,
            ));
        } catch (\Throwable $exception) {
            Log::warning('Workflow notification email failed.', array_merge($logContext, [
                'recipient_id' => $recipient->id,
                'message' => $exception->getMessage(),
            ]));
        }
    }

    private function persistNotification(
        int $companyId,
        int $userId,
        string $type,
        string $title,
        string $body,
        string $actionUrl,
        string $relatedType,
        int $relatedId,
    ): void {
        try {
            UserNotification::query()->create([
                'company_id' => $companyId,
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'action_url' => $actionUrl,
                'related_type' => $relatedType,
                'related_id' => $relatedId,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Workflow in-app notification failed.', [
                'user_id' => $userId,
                'type' => $type,
                'related_id' => $relatedId,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
