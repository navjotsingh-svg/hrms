<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;

class EmployeeProfileApprovalService
{
    public function shouldAutoApprove(User $user, Employee $employee): bool
    {
        if (! $user->canEditEmployeeProfileWithoutApproval($employee)) {
            return false;
        }

        return (int) $user->company_id === (int) $employee->company_id;
    }

    public function submissionMeta(User $user, Employee $employee): array
    {
        if ($this->shouldAutoApprove($user, $employee)) {
            return [
                'status' => 'approved',
                'reviewed_by_user_id' => $user->id,
                'reviewed_at' => now(),
                'notes' => null,
            ];
        }

        return [
            'status' => 'pending',
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'notes' => null,
        ];
    }
}
