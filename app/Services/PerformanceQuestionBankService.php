<?php

namespace App\Services;

use App\Models\PerformanceQuestionBank;
use App\Models\User;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PerformanceQuestionBankService
{
    public function listForUser(User $user, array $filters = []): Collection
    {
        $this->assertManage($user);

        $query = PerformanceQuestionBank::query()
            ->where('company_id', $user->company_id)
            ->orderBy('category')
            ->orderBy('sort_order');

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('question', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        return $query->get();
    }

    public function store(User $user, array $data): PerformanceQuestionBank
    {
        $this->assertManage($user);

        return PerformanceQuestionBank::create([
            'company_id' => $user->company_id,
            'category' => $data['category'] ?? null,
            'question' => $data['question'],
            'question_type' => $data['question_type'] ?? PerformanceQuestionBank::TYPE_RATING,
            'default_weight' => $data['default_weight'] ?? 1,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
            'created_by_user_id' => $user->id,
        ]);
    }

    public function update(User $user, PerformanceQuestionBank $question, array $data): PerformanceQuestionBank
    {
        $this->resolve($user, $question);
        $this->assertManage($user);

        $question->update([
            'category' => $data['category'] ?? null,
            'question' => $data['question'],
            'question_type' => $data['question_type'] ?? $question->question_type,
            'default_weight' => $data['default_weight'] ?? $question->default_weight,
            'sort_order' => $data['sort_order'] ?? $question->sort_order,
            'is_active' => $data['is_active'] ?? $question->is_active,
        ]);

        return $question->fresh();
    }

    public function delete(User $user, PerformanceQuestionBank $question): void
    {
        $this->resolve($user, $question);
        $this->assertManage($user);

        $question->delete();
    }

    public function resolve(User $user, PerformanceQuestionBank $question): PerformanceQuestionBank
    {
        if ((int) $question->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Question not found.');
        }

        return $question;
    }

    private function assertManage(User $user): void
    {
        if (! $user->canManagePerformance()) {
            throw new AccessDeniedHttpException('You do not have permission to manage the question bank.');
        }
    }
}
