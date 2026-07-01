<?php

namespace App\Services;

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
use App\Models\ExitCase;
use App\Models\ResignationRequest;
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
            emailIntro: 'A new Work From Home request requires your attention.',
            emailDetails: [
                'Employee' => $employee->full_name,
                'Dates' => $dateSummary,
                'Duration' => $request->total_days.' day(s)',
                'Reason' => $request->reason ?: '—',
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
            emailIntro: 'A new asset request requires your attention.',
            emailDetails: [
                'Employee' => $employee->full_name,
                'Asset' => $assetName,
                'Reason' => $request->reason ?: '—',
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
            emailIntro: 'A resignation request requires your attention.',
            emailDetails: [
                'Employee' => $employee->full_name,
                'Proposed LWD' => $request->proposed_last_working_date?->format('d M Y') ?? '—',
                'Reason' => $request->reason ?: '—',
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
            emailIntro: 'Your resignation request has been '.$statusLabel.'.',
            emailDetails: $details,
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
            emailIntro: 'Your offboarding process has been completed.',
            emailDetails: [
                'Last working date' => $exitCase->last_working_date?->format('d M Y') ?? '—',
                'Processed by' => $processedBy->name,
            ],
        );
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

        $this->notifyStakeholders(
            employee: $employee,
            exclude: $submittedBy,
            type: UserNotification::TYPE_DOCUMENT_VERIFICATION,
            title: 'Document verification',
            body: $body,
            actionUrl: route('web.requests.show', ['category' => 'document', 'id' => $document->id]),
            relatedType: 'employee_document',
            relatedId: $document->id,
            emailSubject: 'Document verification requested – '.$employee->full_name,
            emailIntro: 'An employee document requires your verification.',
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
            emailIntro: 'Your Work From Home request has been '.$statusLabel.'.',
            emailDetails: $details,
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
            emailIntro: 'Your asset request has been '.$statusLabel.'.',
            emailDetails: $details,
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
            emailIntro: 'An asset in your request has been '.$statusLabel.'.',
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
            actionUrl: route('web.requests.show', ['category' => 'regularization', 'id' => $request->id]),
            relatedType: 'attendance_regularization',
            relatedId: $request->id,
            emailSubject: 'Attendance regularization '.$statusLabel.' – '.$dateLabel,
            emailIntro: 'Your attendance regularization request has been '.$statusLabel.'.',
            emailDetails: $details,
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

        $this->notifyStakeholders(
            employee: $employee,
            exclude: $submittedBy,
            type: UserNotification::TYPE_PROFILE_PHOTO_SUBMITTED,
            title: 'Profile photo approval',
            body: $body,
            actionUrl: route('web.requests.show', ['category' => 'profile_photo', 'id' => $photo->id]),
            relatedType: 'employee_profile_photo',
            relatedId: $photo->id,
            emailSubject: 'Profile photo submitted – '.$employee->full_name,
            emailIntro: 'An employee profile photo is pending your approval.',
            emailDetails: [
                'Employee' => $employee->full_name,
                'Status' => 'Pending approval',
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
            emailIntro: 'Your profile photo request has been '.$statusLabel.'.',
            emailDetails: $details,
            actionLabel: 'View profile',
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
