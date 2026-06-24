<?php

namespace App\Services;

use App\Models\PerformanceFeedbackForm;
use App\Models\PerformanceFeedbackFormQuestion;
use App\Models\PerformanceQuestionBank;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PerformanceFeedbackFormService
{
    public function listForUser(User $user, array $filters = []): Collection
    {
        $this->assertManage($user);

        $query = PerformanceFeedbackForm::query()
            ->withCount('questions')
            ->where('company_id', $user->company_id)
            ->orderByDesc('updated_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->get();
    }

    public function store(User $user, array $data): PerformanceFeedbackForm
    {
        $this->assertManage($user);

        return DB::transaction(function () use ($user, $data) {
            $form = PerformanceFeedbackForm::create([
                'company_id' => $user->company_id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? PerformanceFeedbackForm::STATUS_DRAFT,
                'created_by_user_id' => $user->id,
            ]);

            $this->syncQuestions($form, $data['questions'] ?? []);

            return $form->fresh(['questions']);
        });
    }

    public function update(User $user, PerformanceFeedbackForm $form, array $data): PerformanceFeedbackForm
    {
        $this->resolve($user, $form);
        $this->assertManage($user);

        return DB::transaction(function () use ($form, $data) {
            $form->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? $form->status,
            ]);

            if (isset($data['questions'])) {
                $this->syncQuestions($form, $data['questions']);
            }

            return $form->fresh(['questions']);
        });
    }

    public function delete(User $user, PerformanceFeedbackForm $form): void
    {
        $this->resolve($user, $form);
        $this->assertManage($user);

        $form->delete();
    }

    public function resolve(User $user, PerformanceFeedbackForm $form): PerformanceFeedbackForm
    {
        if ((int) $form->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Feedback form not found.');
        }

        $form->loadMissing('questions');

        return $form;
    }

    private function syncQuestions(PerformanceFeedbackForm $form, array $questions): void
    {
        $form->questions()->delete();

        foreach (array_values($questions) as $index => $item) {
            $bankQuestion = null;

            if (! empty($item['question_bank_id'])) {
                $bankQuestion = PerformanceQuestionBank::query()
                    ->where('company_id', $form->company_id)
                    ->where('id', $item['question_bank_id'])
                    ->first();
            }

            PerformanceFeedbackFormQuestion::create([
                'feedback_form_id' => $form->id,
                'question_bank_id' => $bankQuestion?->id,
                'question' => $item['question'] ?? $bankQuestion?->question ?? '',
                'question_type' => $item['question_type'] ?? $bankQuestion?->question_type ?? PerformanceQuestionBank::TYPE_RATING,
                'weight' => $item['weight'] ?? $bankQuestion?->default_weight ?? 1,
                'sort_order' => $index,
            ]);
        }
    }

    private function assertManage(User $user): void
    {
        if (! $user->canManagePerformance()) {
            throw new AccessDeniedHttpException('You do not have permission to manage feedback forms.');
        }
    }
}
