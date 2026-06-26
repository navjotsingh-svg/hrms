<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeFamilyMember;
use App\Models\EmployeePersonalSection;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EmployeePersonalSectionService
{
    public function __construct(private EmployeeProfileApprovalService $approvalService) {}

    public function assertCanSubmit(Employee $employee, string $sectionType, ?User $user = null): void
    {
        $existing = $this->findForType($employee, $sectionType);

        if (! $existing) {
            return;
        }

        if (in_array($existing->status, ['rejected', 'approved'], true)) {
            return;
        }

        if ($user && $this->approvalService->shouldAutoApprove($user, $employee)) {
            return;
        }

        throw ValidationException::withMessages([
            'section_type' => ['This section is pending approval and cannot be changed.'],
        ]);
    }

    public function submit(User $user, Employee $employee, string $sectionType, array $payload): array
    {
        $autoApproved = $this->approvalService->shouldAutoApprove($user, $employee);
        $this->assertCanSubmit($employee, $sectionType, $user);
        $this->validatePayload($employee, $sectionType, $payload);

        return DB::transaction(function () use ($user, $employee, $sectionType, $payload, $autoApproved) {
            $existing = $this->findForType($employee, $sectionType);
            $isResubmit = $existing && in_array($existing->status, ['rejected', 'approved'], true);
            $isChange = $existing && $existing->status === 'approved';
            $isPendingUpdate = $existing && $existing->status === 'pending';

            $sectionPayload = [
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'section_type' => $sectionType,
                'payload' => $payload,
                'submitted_by_user_id' => $user->id,
                'submitted_at' => now(),
                ...$this->approvalService->submissionMeta($user, $employee),
            ];

            if ($existing) {
                $existing->update($sectionPayload);
                $section = $existing->fresh()->load(['submittedBy.role', 'reviewedBy', 'employee']);
            } else {
                $section = EmployeePersonalSection::create($sectionPayload)
                    ->load(['submittedBy.role', 'reviewedBy', 'employee']);
            }

            if ($section->status === 'approved') {
                $this->syncApprovedPayload($section);
            }

            return [
                'personal_section' => $section,
                'is_resubmit' => $isResubmit,
                'is_change' => $isChange,
                'is_pending_update' => $isPendingUpdate,
                'auto_approved' => $autoApproved,
            ];
        });
    }

    public function pendingForReviewer(User $user): Collection
    {
        if (! $user->canReviewEmployeeDocuments()) {
            return collect();
        }

        return EmployeePersonalSection::query()
            ->with(['employee.familyMembers', 'submittedBy.role', 'reviewedBy'])
            ->where('company_id', $user->company_id)
            ->where('status', 'pending')
            ->latest('submitted_at')
            ->get()
            ->filter(fn (EmployeePersonalSection $section) => $user->canReviewPersonalSection($section))
            ->values();
    }

    public function approve(User $user, EmployeePersonalSection $section, ?string $notes = null): EmployeePersonalSection
    {
        $this->assertCanReview($user, $section);

        if ($section->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending personal sections can be approved.'],
            ]);
        }

        DB::transaction(function () use ($user, $section, $notes) {
            $section->update([
                'status' => 'approved',
                'reviewed_by_user_id' => $user->id,
                'reviewed_at' => now(),
                'notes' => $notes ? trim($notes) : null,
            ]);

            $this->syncApprovedPayload($section);
        });

        return $section->fresh()->load(['employee', 'submittedBy.role', 'reviewedBy']);
    }

    public function reject(User $user, EmployeePersonalSection $section, string $notes): EmployeePersonalSection
    {
        $this->assertCanReview($user, $section);

        if ($section->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending personal sections can be rejected.'],
            ]);
        }

        $section->update([
            'status' => 'rejected',
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'notes' => $notes,
        ]);

        return $section->fresh()->load(['employee', 'submittedBy.role', 'reviewedBy']);
    }

    public function assertBelongsToCompany(User $user, EmployeePersonalSection $section): void
    {
        if ((int) $section->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Personal section not found.');
        }
    }

    private function assertCanReview(User $user, EmployeePersonalSection $section): void
    {
        $this->assertBelongsToCompany($user, $section);

        if (! $user->canReviewPersonalSection($section)) {
            throw new AccessDeniedHttpException('You are not allowed to review this personal section.');
        }
    }

    private function findForType(Employee $employee, string $sectionType): ?EmployeePersonalSection
    {
        return EmployeePersonalSection::query()
            ->where('employee_id', $employee->id)
            ->where('section_type', $sectionType)
            ->first();
    }

    private function validatePayload(Employee $employee, string $sectionType, array $payload): void
    {
        match ($sectionType) {
            'address' => $this->validateAddressPayload($payload),
            'emergency_contact' => $this->validateEmergencyPayload($payload),
            default => throw ValidationException::withMessages([
                'section_type' => ['Invalid personal section type.'],
            ]),
        };
    }

    private function validateAddressPayload(array $payload): void
    {
        if (empty($payload['permanent']) || ! is_array($payload['permanent'])) {
            throw ValidationException::withMessages([
                'permanent' => ['Permanent address is required.'],
            ]);
        }
    }

    private function validateEmergencyPayload(array $payload): void
    {
        if (empty(trim((string) ($payload['name'] ?? '')))) {
            throw ValidationException::withMessages([
                'name' => ['Emergency contact name is required.'],
            ]);
        }

        if (empty(trim((string) ($payload['relation'] ?? '')))) {
            throw ValidationException::withMessages([
                'relation' => ['Emergency contact relation is required.'],
            ]);
        }
    }

    private function syncApprovedPayload(EmployeePersonalSection $section): void
    {
        match ($section->section_type) {
            'address' => $this->syncAddress($section),
            'emergency_contact' => $this->syncEmergencyContact($section),
            default => null,
        };
    }

    private function syncAddress(EmployeePersonalSection $section): void
    {
        $permanent = $section->payload['permanent'] ?? [];
        $sameAsPermanent = (bool) ($section->payload['same_as_permanent'] ?? false);
        $temporary = $sameAsPermanent ? $permanent : ($section->payload['temporary'] ?? []);

        $section->employee->update([
            'address_line_1' => $permanent['address_line_1'] ?? null,
            'address_line_2' => $permanent['address_line_2'] ?? null,
            'city' => $permanent['city'] ?? null,
            'state' => $permanent['state'] ?? null,
            'country' => $permanent['country'] ?? null,
            'postal_code' => $permanent['postal_code'] ?? null,
            'temp_address_line_1' => $temporary['address_line_1'] ?? null,
            'temp_address_line_2' => $temporary['address_line_2'] ?? null,
            'temp_city' => $temporary['city'] ?? null,
            'temp_state' => $temporary['state'] ?? null,
            'temp_country' => $temporary['country'] ?? null,
            'temp_postal_code' => $temporary['postal_code'] ?? null,
        ]);
    }

    private function syncEmergencyContact(EmployeePersonalSection $section): void
    {
        $payload = $section->payload ?? [];

        if (! empty($payload['name'])) {
            $section->employee->update([
                'emergency_contact_name' => trim((string) $payload['name']),
                'emergency_contact_phone' => $payload['phone'] ?? null,
                'emergency_contact_relation' => trim((string) ($payload['relation'] ?? '')),
                'emergency_contact_family_member_id' => null,
            ]);

            return;
        }

        $familyMemberId = $payload['family_member_id'] ?? null;
        $member = EmployeeFamilyMember::query()
            ->where('employee_id', $section->employee_id)
            ->where('id', $familyMemberId)
            ->first();

        if (! $member) {
            return;
        }

        $section->employee->update([
            'emergency_contact_name' => $member->name,
            'emergency_contact_phone' => $member->phone,
            'emergency_contact_relation' => $member->relation,
            'emergency_contact_family_member_id' => $member->id,
        ]);
    }
}
