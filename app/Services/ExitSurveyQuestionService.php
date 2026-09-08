<?php

namespace App\Services;

use App\Models\ExitSurveyQuestion;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ExitSurveyQuestionService
{
    public function meta(): array
    {
        return [
            'types' => collect(config('offboarding.survey_question_types', []))->map(fn ($label, $value) => [
                'value' => $value,
                'label' => $label,
            ])->values(),
            'default_questions' => config('offboarding.default_survey_questions', []),
        ];
    }

    public function listForCompany(int $companyId, array $filters = []): LengthAwarePaginator
    {
        $this->ensureDefaultsForCompany($companyId);

        $query = ExitSurveyQuestion::query()
            ->where('company_id', $companyId)
            ->orderBy('sort_order')
            ->orderBy('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where('question', 'like', "%{$search}%");
        }

        return $query->paginate($filters['per_page'] ?? 25);
    }

    /** @return Collection<int, ExitSurveyQuestion> */
    public function activeQuestionsForCompany(int $companyId): Collection
    {
        $this->ensureDefaultsForCompany($companyId);

        return ExitSurveyQuestion::query()
            ->where('company_id', $companyId)
            ->where('status', ExitSurveyQuestion::STATUS_ACTIVE)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function create(User $user, array $data): ExitSurveyQuestion
    {
        $this->assertCanManage($user);

        return ExitSurveyQuestion::query()->create([
            'company_id' => $user->company_id,
            'question' => trim($data['question']),
            'type' => $data['type'],
            'options' => $this->normalizeOptions($data['type'], $data['options'] ?? null),
            'is_required' => $data['is_required'] ?? true,
            'sort_order' => (int) ($data['sort_order'] ?? $this->nextSortOrder($user->company_id)),
            'status' => $data['status'] ?? ExitSurveyQuestion::STATUS_ACTIVE,
        ]);
    }

    public function update(User $user, ExitSurveyQuestion $question, array $data): ExitSurveyQuestion
    {
        $this->assertCanManage($user);
        $this->assertSameCompany($user, $question);

        $type = $data['type'] ?? $question->type;

        $question->update([
            'question' => array_key_exists('question', $data) ? trim($data['question']) : $question->question,
            'type' => $type,
            'options' => array_key_exists('options', $data)
                ? $this->normalizeOptions($type, $data['options'])
                : $question->options,
            'is_required' => array_key_exists('is_required', $data)
                ? (bool) $data['is_required']
                : $question->is_required,
            'sort_order' => array_key_exists('sort_order', $data)
                ? (int) $data['sort_order']
                : $question->sort_order,
            'status' => $data['status'] ?? $question->status,
        ]);

        return $question->fresh();
    }

    public function ensureDefaultsForCompany(int $companyId): void
    {
        if (ExitSurveyQuestion::query()->where('company_id', $companyId)->exists()) {
            return;
        }

        foreach (config('offboarding.default_survey_questions', []) as $item) {
            ExitSurveyQuestion::query()->create([
                'company_id' => $companyId,
                'question' => $item['question'],
                'type' => $item['type'],
                'options' => $item['options'] ?? null,
                'is_required' => $item['is_required'] ?? true,
                'sort_order' => $item['sort_order'] ?? 0,
                'status' => ExitSurveyQuestion::STATUS_ACTIVE,
            ]);
        }
    }

    /** @return array<int, array{question_id: int, question: string, answer: string}> */
    public function formatSubmittedResponses(int $companyId, array $responses): array
    {
        $questions = $this->activeQuestionsForCompany($companyId)->keyBy('id');
        $formatted = [];

        foreach ($questions as $question) {
            $key = (string) $question->id;
            $answer = trim((string) ($responses[$key] ?? $responses[$question->id] ?? ''));

            if ($question->is_required && $answer === '') {
                throw ValidationException::withMessages([
                    'responses' => ["Please answer: {$question->question}"],
                ]);
            }

            if ($answer !== '') {
                $this->validateAnswer($question, $answer);
            } elseif (! $question->is_required) {
                continue;
            }

            $formatted[] = [
                'question_id' => $question->id,
                'question' => $question->question,
                'type' => $question->type,
                'answer' => $answer,
            ];
        }

        if ($formatted === []) {
            throw ValidationException::withMessages([
                'responses' => ['Please answer at least one survey question.'],
            ]);
        }

        return $formatted;
    }

    private function validateAnswer(ExitSurveyQuestion $question, string $answer): void
    {
        match ($question->type) {
            ExitSurveyQuestion::TYPE_RATING => $this->validateRating($question, $answer),
            ExitSurveyQuestion::TYPE_YES_NO => $this->validateYesNo($question, $answer),
            ExitSurveyQuestion::TYPE_SELECT => $this->validateSelect($question, $answer),
            default => null,
        };
    }

    private function validateRating(ExitSurveyQuestion $question, string $answer): void
    {
        if (! preg_match('/^[1-5]$/', $answer)) {
            throw ValidationException::withMessages([
                'responses' => ["{$question->question} must be rated from 1 to 5."],
            ]);
        }
    }

    private function validateYesNo(ExitSurveyQuestion $question, string $answer): void
    {
        if (! in_array(strtolower($answer), ['yes', 'no'], true)) {
            throw ValidationException::withMessages([
                'responses' => ["{$question->question} must be answered Yes or No."],
            ]);
        }
    }

    private function validateSelect(ExitSurveyQuestion $question, string $answer): void
    {
        $options = collect($question->options ?? [])->map(fn ($option) => trim((string) $option))->filter()->values();

        if ($options->isEmpty()) {
            throw ValidationException::withMessages([
                'responses' => ["{$question->question} has no configured options."],
            ]);
        }

        if (! $options->contains($answer)) {
            throw ValidationException::withMessages([
                'responses' => ["Please choose a valid option for: {$question->question}"],
            ]);
        }
    }

    private function normalizeOptions(string $type, mixed $options): ?array
    {
        if ($type !== ExitSurveyQuestion::TYPE_SELECT) {
            return null;
        }

        $items = is_array($options) ? $options : preg_split('/\r\n|\r|\n/', (string) $options);
        $items = collect($items)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();

        if ($items === []) {
            throw ValidationException::withMessages([
                'options' => ['At least one option is required for single choice questions.'],
            ]);
        }

        return $items;
    }

    private function nextSortOrder(int $companyId): int
    {
        return ((int) ExitSurveyQuestion::query()->where('company_id', $companyId)->max('sort_order')) + 1;
    }

    public function reseedDefaults(User $user): void
    {
        $this->assertCanManage($user);

        ExitSurveyQuestion::query()
            ->where('company_id', $user->company_id)
            ->delete();

        foreach (config('offboarding.default_survey_questions', []) as $item) {
            ExitSurveyQuestion::query()->create([
                'company_id' => $user->company_id,
                'question' => $item['question'],
                'type' => $item['type'],
                'options' => $item['options'] ?? null,
                'is_required' => $item['is_required'] ?? true,
                'sort_order' => $item['sort_order'] ?? 0,
                'status' => ExitSurveyQuestion::STATUS_ACTIVE,
            ]);
        }
    }

    private function assertCanManage(User $user): void
    {
        if (! $user->canManageOffboarding()) {
            throw new AccessDeniedHttpException('You are not allowed to manage exit survey questions.');
        }
    }

    private function assertSameCompany(User $user, ExitSurveyQuestion $question): void
    {
        if ((int) $user->company_id !== (int) $question->company_id) {
            abort(404);
        }
    }
}
