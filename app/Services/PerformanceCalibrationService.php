<?php

namespace App\Services;

use App\Models\PerformanceCalibrationEntry;
use App\Models\PerformanceCalibrationSession;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCycle;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PerformanceCalibrationService
{
    public function listSessions(User $user, array $filters = []): LengthAwarePaginator
    {
        $this->assertManage($user);

        $query = PerformanceCalibrationSession::query()
            ->with(['cycle', 'entries'])
            ->withCount('entries')
            ->where('company_id', $user->company_id)
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhereHas('cycle', fn ($cycle) => $cycle->where('name', 'like', "%{$search}%"));
            });
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function storeSession(User $user, array $data): PerformanceCalibrationSession
    {
        $this->assertManage($user);

        return DB::transaction(function () use ($user, $data) {
            $session = PerformanceCalibrationSession::create([
                'company_id' => $user->company_id,
                'cycle_id' => $data['cycle_id'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => PerformanceCalibrationSession::STATUS_DRAFT,
                'created_by_user_id' => $user->id,
            ]);

            if (! empty($data['cycle_id'])) {
                $this->importEntriesFromCycle($session, (int) $data['cycle_id']);
            }

            return $session->fresh(['cycle', 'entries.employee', 'entries.review']);
        });
    }

    public function resolveSession(User $user, PerformanceCalibrationSession $session): PerformanceCalibrationSession
    {
        if ((int) $session->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Calibration session not found.');
        }

        $this->assertManage($user);

        return $session->load(['cycle', 'entries.employee', 'entries.review']);
    }

    public function updateEntry(User $user, PerformanceCalibrationEntry $entry, array $data): PerformanceCalibrationEntry
    {
        $session = $entry->session;
        $this->resolveSession($user, $session);

        if ($session->status === PerformanceCalibrationSession::STATUS_FINALIZED) {
            throw new AccessDeniedHttpException('Cannot edit entries in a finalized session.');
        }

        $calibratedRating = $data['calibrated_rating'] ?? $entry->calibrated_rating;
        $status = PerformanceCalibrationEntry::STATUS_PENDING;

        if ($calibratedRating !== null && $entry->original_rating !== null && (float) $calibratedRating !== (float) $entry->original_rating) {
            $status = PerformanceCalibrationEntry::STATUS_ADJUSTED;
        } elseif ($calibratedRating !== null) {
            $status = PerformanceCalibrationEntry::STATUS_CONFIRMED;
        }

        $entry->update([
            'calibrated_rating' => $calibratedRating,
            'notes' => $data['notes'] ?? $entry->notes,
            'status' => $status,
        ]);

        if ($session->status === PerformanceCalibrationSession::STATUS_DRAFT) {
            $session->update(['status' => PerformanceCalibrationSession::STATUS_IN_PROGRESS]);
        }

        return $entry->fresh(['employee', 'review']);
    }

    public function finalizeSession(User $user, PerformanceCalibrationSession $session): PerformanceCalibrationSession
    {
        $session = $this->resolveSession($user, $session);

        if ($session->status === PerformanceCalibrationSession::STATUS_FINALIZED) {
            return $session;
        }

        return DB::transaction(function () use ($session) {
            foreach ($session->entries as $entry) {
                if (! $entry->review_id || $entry->calibrated_rating === null) {
                    continue;
                }

                PerformanceReview::query()
                    ->where('id', $entry->review_id)
                    ->update(['overall_rating' => $entry->calibrated_rating]);
            }

            $session->update([
                'status' => PerformanceCalibrationSession::STATUS_FINALIZED,
                'finalized_at' => now(),
            ]);

            return $session->fresh(['cycle', 'entries.employee', 'entries.review']);
        });
    }

    private function importEntriesFromCycle(PerformanceCalibrationSession $session, int $cycleId): void
    {
        $cycle = PerformanceReviewCycle::query()
            ->where('company_id', $session->company_id)
            ->findOrFail($cycleId);

        $reviews = PerformanceReview::query()
            ->with('pair')
            ->where('cycle_id', $cycle->id)
            ->where('status', PerformanceReview::STATUS_SUBMITTED)
            ->whereHas('pair', fn ($pair) => $pair->where('relationship', 'manager'))
            ->get()
            ->unique('reviewee_employee_id');

        foreach ($reviews as $review) {
            PerformanceCalibrationEntry::create([
                'session_id' => $session->id,
                'employee_id' => $review->reviewee_employee_id,
                'review_id' => $review->id,
                'original_rating' => $review->overall_rating,
                'calibrated_rating' => $review->overall_rating,
                'status' => PerformanceCalibrationEntry::STATUS_PENDING,
            ]);
        }
    }

    private function assertManage(User $user): void
    {
        if (! $user->canManagePerformance()) {
            throw new AccessDeniedHttpException('You are not allowed to manage calibration sessions.');
        }
    }
}
