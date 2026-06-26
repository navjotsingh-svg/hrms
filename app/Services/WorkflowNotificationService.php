<?php

namespace App\Services;

use App\Mail\WorkflowActionMail;
use App\Models\AttendanceRegularizationRequest;
use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\UserNotification;
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
            emailIntro: 'A new leave request requires your attention.',
            emailDetails: [
                'Employee' => $employee->full_name,
                'Leave type' => $request->leaveType?->name ?? '—',
                'Dates' => $dateSummary,
                'Duration' => $request->total_days.' day(s)',
                'Reason' => $request->reason ?: '—',
            ],
        );
    }

    public function notifyDocumentVerification(EmployeeDocument $document, User $submittedBy): void
    {
        if ($document->status !== 'pending') {
            return;
        }

        $document->loadMissing(['employee', 'documentType']);

        $employee = $document->employee;

        if (! $employee) {
            return;
        }

        $body = trim(sprintf(
            '%s submitted %s for verification (%s).',
            $employee->full_name,
            $document->documentType?->name ?? 'a document',
            $document->original_name,
        ));

        $this->notifyStakeholders(
            employee: $employee,
            exclude: $submittedBy,
            type: UserNotification::TYPE_DOCUMENT_VERIFICATION,
            title: 'Document verification request',
            body: $body,
            actionUrl: route('web.requests.show', ['category' => 'document', 'id' => $document->id]),
            relatedType: 'employee_document',
            relatedId: $document->id,
            emailSubject: 'Document verification requested – '.$employee->full_name,
            emailIntro: 'An employee document has been submitted for verification.',
            emailDetails: [
                'Employee' => $employee->full_name,
                'Document type' => $document->documentType?->name ?? '—',
                'File' => $document->original_name,
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

        $this->notifyStakeholders(
            employee: $employee,
            exclude: $submittedBy,
            type: UserNotification::TYPE_REGULARIZATION_SUBMITTED,
            title: 'Attendance regularization request',
            body: $body,
            actionUrl: route('web.attendance.regularize.index'),
            relatedType: 'attendance_regularization',
            relatedId: $request->id,
            emailSubject: 'Attendance regularization submitted – '.$employee->full_name,
            emailIntro: 'A new attendance regularization request requires your review.',
            emailDetails: [
                'Employee' => $employee->full_name,
                'Date(s)' => $dateLabel,
                'Reason' => $request->reason ?: '—',
            ],
        );
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
            emailIntro: 'Your leave request has been '.$statusLabel.'.',
            emailDetails: $details,
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
            emailIntro: 'Your document verification request has been '.$statusLabel.'.',
            emailDetails: $details,
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
            actionUrl: route('web.attendance.regularize.index'),
            relatedType: 'attendance_regularization',
            relatedId: $request->id,
            emailSubject: 'Attendance regularization '.$statusLabel.' – '.$dateLabel,
            emailIntro: 'Your attendance regularization request has been '.$statusLabel.'.',
            emailDetails: $details,
        );
    }

    /** @param  array<string, string>  $emailDetails */
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

        try {
            Mail::to($applicant->email)->send(new WorkflowActionMail(
                recipientName: $applicant->name,
                subjectLine: $emailSubject,
                intro: $emailIntro,
                details: $emailDetails,
                actionUrl: $actionUrl,
                actionLabel: $actionLabel,
            ));
        } catch (\Throwable $exception) {
            Log::warning('Workflow applicant notification email failed.', [
                'recipient_id' => $applicant->id,
                'type' => $type,
                'related_id' => $relatedId,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function applicantForLeave(LeaveRequest $request): ?User
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

            try {
                Mail::to($recipient->email)->send(new WorkflowActionMail(
                    recipientName: $recipient->name,
                    subjectLine: $emailSubject,
                    intro: $emailIntro,
                    details: $emailDetails,
                    actionUrl: $actionUrl,
                    actionLabel: 'Review request',
                ));
            } catch (\Throwable $exception) {
                Log::warning('Workflow notification email failed.', [
                    'recipient_id' => $recipient->id,
                    'type' => $type,
                    'related_id' => $relatedId,
                    'message' => $exception->getMessage(),
                ]);
            }
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
