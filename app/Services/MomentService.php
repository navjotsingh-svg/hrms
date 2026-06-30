<?php

namespace App\Services;

use App\Models\CompanyMoment;
use App\Models\CompanyMomentAttachment;
use App\Models\CompanyMomentComment;
use App\Models\CompanyMomentReaction;
use App\Models\Employee;
use App\Models\User;
use App\Models\UserMomentFeedState;
use App\Models\UserNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MomentService
{
    public function __construct(
        private MomentAttachmentService $attachmentService,
        private MomentTemplateService $templateService,
    ) {}

    public function feedForUser(User $user, array $filters = [], int $page = 1, int $perPage = 15): array
    {
        if (! $user->company_id) {
            return $this->emptyFeed($page, $perPage);
        }

        $this->syncTodaySystemMoments((int) $user->company_id);

        $unread = $this->unreadCountsForUser($user);

        $query = CompanyMoment::query()
            ->with([
                'author.employee',
                'reactions.user.employee',
                'attachments',
                'comments' => fn ($query) => $query->latest()->limit(5)->with('user.employee'),
            ])
            ->withCount('comments')
            ->where('company_id', $user->company_id)
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $paginator = $query->paginate(max(1, min(50, $perPage)), ['*'], 'page', max(1, $page));
        $postCountByUser = $this->authorPostCountMap((int) $user->company_id);

        return [
            'moments' => collect($paginator->items())
                ->map(fn (CompanyMoment $moment) => $this->transformMoment($moment, $user, $postCountByUser))
                ->values()
                ->all(),
            'author_stats' => $this->authorPostStatsForCompany((int) $user->company_id, $postCountByUser),
            'unread' => $unread,
            'pagination' => $this->paginationMeta($paginator),
        ];
    }

    public function summaryForUser(User $user): array
    {
        if (! $user->company_id || ! $user->hasPermission('home.moments.view')) {
            return [
                'unread' => $this->emptyUnreadCounts(),
                'templates' => null,
                'employees' => [],
                'can_manage_templates' => false,
            ];
        }

        $this->syncTodaySystemMoments((int) $user->company_id);

        $templates = $this->templateService->forCompany((int) $user->company_id);

        $employees = Employee::query()
            ->where('company_id', $user->company_id)
            ->where('status', 'active')
            ->orderedByName()
            ->get(['id', 'first_name', 'last_name', 'employee_code', 'date_of_birth', 'joining_date'])
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employee_code' => $employee->employee_code,
            ])
            ->values()
            ->all();

        return [
            'unread' => $this->unreadCountsForUser($user),
            'templates' => $this->templateService->toArray($templates),
            'employees' => $employees,
            'can_manage_templates' => $user->isCompanyAdmin(),
        ];
    }

    public function markFeedSeen(User $user): array
    {
        if (! $user->company_id) {
            return ['unread' => $this->emptyUnreadCounts()];
        }

        UserMomentFeedState::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'company_id' => $user->company_id,
                'last_seen_at' => now(),
            ],
        );

        return ['unread' => $this->emptyUnreadCounts()];
    }

    /** @return array<string, int> */
    public function unreadCountsForUser(User $user): array
    {
        if (! $user->company_id) {
            return $this->emptyUnreadCounts();
        }

        $lastSeen = UserMomentFeedState::query()
            ->where('user_id', $user->id)
            ->value('last_seen_at');

        $query = CompanyMoment::query()
            ->where('company_id', $user->company_id)
            ->whereNotNull('published_at');

        if ($lastSeen) {
            $query->where('published_at', '>', $lastSeen);
        }

        $counts = (clone $query)
            ->selectRaw('type, COUNT(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type')
            ->map(fn ($count) => (int) $count)
            ->all();

        $byType = [
            'post' => $counts[CompanyMoment::TYPE_POST] ?? 0,
            'birthday' => $counts[CompanyMoment::TYPE_BIRTHDAY] ?? 0,
            'work_anniversary' => $counts[CompanyMoment::TYPE_WORK_ANNIVERSARY] ?? 0,
            'new_joinee' => $counts[CompanyMoment::TYPE_NEW_JOINEE] ?? 0,
        ];

        return [
            'total' => array_sum($byType),
            'by_type' => $byType,
        ];
    }

    /** @return array<string, mixed> */
    private function emptyUnreadCounts(): array
    {
        return [
            'total' => 0,
            'by_type' => [
                'post' => 0,
                'birthday' => 0,
                'work_anniversary' => 0,
                'new_joinee' => 0,
            ],
        ];
    }

    /** @return array<int, int> */
    private function authorPostCountMap(int $companyId): array
    {
        return CompanyMoment::query()
            ->where('company_id', $companyId)
            ->where('type', CompanyMoment::TYPE_POST)
            ->whereNotNull('author_user_id')
            ->selectRaw('author_user_id, COUNT(*) as post_count')
            ->groupBy('author_user_id')
            ->pluck('post_count', 'author_user_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /** @param  array<int, int>  $postCountByUser */
    private function authorPostStatsForCompany(int $companyId, array $postCountByUser): array
    {
        if ($postCountByUser === []) {
            return [];
        }

        arsort($postCountByUser);

        $users = User::query()
            ->with('employee:id,user_id,first_name,last_name,employee_code')
            ->where('company_id', $companyId)
            ->whereIn('id', array_keys($postCountByUser))
            ->get()
            ->keyBy('id');

        return collect($postCountByUser)
            ->map(function (int $count, int $userId) use ($users) {
                $author = $users->get($userId);

                return [
                    'user_id' => $userId,
                    'name' => $author?->employee?->full_name ?: $author?->name ?: 'Team Member',
                    'employee_id' => $author?->employee?->id,
                    'employee_code' => $author?->employee?->employee_code,
                    'post_count' => $count,
                ];
            })
            ->values()
            ->all();
    }

    public function syncTodaySystemMoments(int $companyId): void
    {
        $today = $this->today();
        $todayString = $today->toDateString();
        $templates = $this->templateService->forCompany($companyId);

        $employees = Employee::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->get(['id', 'first_name', 'last_name', 'employee_code', 'date_of_birth', 'joining_date']);

        foreach ($employees as $employee) {
            if ($employee->date_of_birth && $this->matchesMonthDay($employee->date_of_birth, $today)) {
                $this->upsertSystemMoment(
                    $companyId,
                    CompanyMoment::TYPE_BIRTHDAY,
                    $todayString,
                    $employee,
                    $this->templateService->render(
                        $templates->birthday_template,
                        $employee,
                    ),
                );
            }

            if ($employee->joining_date) {
                if ($employee->joining_date->isSameDay($today)) {
                    $this->upsertSystemMoment(
                        $companyId,
                        CompanyMoment::TYPE_NEW_JOINEE,
                        $todayString,
                        $employee,
                        $this->templateService->render(
                            $templates->new_joinee_template,
                            $employee,
                        ),
                    );
                } elseif ($employee->joining_date->lt($today) && $this->matchesMonthDay($employee->joining_date, $today)) {
                    $years = $this->workAnniversaryYears($employee, $today);

                    if ($years >= 1) {
                        $this->upsertSystemMoment(
                            $companyId,
                            CompanyMoment::TYPE_WORK_ANNIVERSARY,
                            $todayString,
                            $employee,
                            $this->templateService->render(
                                $templates->work_anniversary_template,
                                $employee,
                                $years,
                            ),
                            ['years' => $years],
                        );
                    }
                }
            }
        }
    }

    /** @param  array<int, \Illuminate\Http\UploadedFile>  $attachments */
    public function createPost(
        User $user,
        string $type,
        string $content,
        array $attachments = [],
        ?int $celebratedEmployeeId = null,
    ): array {
        if (! $user->hasPermission('home.moments.post')) {
            throw new AccessDeniedHttpException('You are not allowed to post moments.');
        }

        if (! $user->company_id) {
            throw ValidationException::withMessages([
                'content' => ['You must belong to a company to post moments.'],
            ]);
        }

        if (! in_array($type, [
            CompanyMoment::TYPE_POST,
            CompanyMoment::TYPE_BIRTHDAY,
            CompanyMoment::TYPE_WORK_ANNIVERSARY,
            CompanyMoment::TYPE_NEW_JOINEE,
        ], true)) {
            throw ValidationException::withMessages([
                'type' => ['Invalid moment type.'],
            ]);
        }

        $content = trim($content);
        $hasAttachments = collect($attachments)->contains(fn ($file) => $file instanceof \Illuminate\Http\UploadedFile);

        if ($content === '' && ! $hasAttachments) {
            throw ValidationException::withMessages([
                'content' => ['Write a message or attach at least one file.'],
            ]);
        }

        $metadata = [];
        $occasionDate = null;

        if ($type !== CompanyMoment::TYPE_POST) {
            if (! $celebratedEmployeeId) {
                throw ValidationException::withMessages([
                    'employee_id' => ['Select the team member this celebration is for.'],
                ]);
            }

            $employee = Employee::query()
                ->where('company_id', $user->company_id)
                ->where('status', 'active')
                ->find($celebratedEmployeeId);

            if (! $employee) {
                throw ValidationException::withMessages([
                    'employee_id' => ['The selected employee is invalid.'],
                ]);
            }

            $years = null;

            if ($type === CompanyMoment::TYPE_WORK_ANNIVERSARY && $employee->joining_date) {
                $years = max(1, $this->workAnniversaryYears($employee, $this->today()));
            }

            if ($content === '') {
                $content = $this->templateService->preview($type, $employee);
            }

            $metadata = [
                'employee_id' => $employee->id,
                'employee_name' => $employee->full_name,
                'employee_code' => $employee->employee_code,
            ];

            if ($years !== null) {
                $metadata['years'] = $years;
            }

            $occasionDate = $this->today()->toDateString();
        }

        $moment = CompanyMoment::query()->create([
            'company_id' => $user->company_id,
            'type' => $type,
            'author_user_id' => $user->id,
            'content' => $content !== '' ? $content : null,
            'metadata' => $metadata !== [] ? $metadata : null,
            'occasion_date' => $occasionDate,
            'published_at' => now(),
        ]);

        if ($hasAttachments) {
            $this->attachmentService->storeMany($moment, $attachments);
        }

        $moment->load(['author.employee', 'reactions.user.employee', 'attachments', 'comments.user.employee']);
        $moment->loadCount('comments');

        $this->notifyCompanyAboutMoment($moment, $user);

        return $this->transformMoment($moment, $user);
    }

    public function addComment(User $user, int $momentId, string $content): array
    {
        if (! $user->hasPermission('home.moments.comment') && ! $user->hasPermission('home.moments.view')) {
            throw new AccessDeniedHttpException('You are not allowed to comment on moments.');
        }

        if (! $user->company_id) {
            throw ValidationException::withMessages([
                'content' => ['You must belong to a company to comment on moments.'],
            ]);
        }

        $content = trim($content);

        if ($content === '') {
            throw ValidationException::withMessages([
                'content' => ['Comment cannot be empty.'],
            ]);
        }

        $moment = CompanyMoment::query()
            ->where('company_id', $user->company_id)
            ->find($momentId);

        if (! $moment) {
            throw new NotFoundHttpException('Moment not found.');
        }

        $comment = CompanyMomentComment::query()->create([
            'company_moment_id' => $moment->id,
            'user_id' => $user->id,
            'content' => $content,
        ]);

        $comment->load('user.employee');

        return $this->transformComment($comment);
    }

    public function commentsForMoment(User $user, int $momentId, int $page = 1, int $perPage = 20): array
    {
        if (! $user->hasPermission('home.moments.view')) {
            throw new AccessDeniedHttpException('You are not allowed to view moments.');
        }

        $moment = CompanyMoment::query()
            ->where('company_id', $user->company_id)
            ->find($momentId);

        if (! $moment) {
            throw new NotFoundHttpException('Moment not found.');
        }

        $paginator = CompanyMomentComment::query()
            ->with('user.employee')
            ->where('company_moment_id', $moment->id)
            ->latest()
            ->paginate(max(1, min(50, $perPage)), ['*'], 'page', max(1, $page));

        return [
            'comments' => collect($paginator->items())
                ->map(fn (CompanyMomentComment $comment) => $this->transformComment($comment))
                ->values()
                ->all(),
            'pagination' => $this->paginationMeta($paginator),
        ];
    }

    public function toggleReaction(User $user, int $momentId, ?string $reaction): array
    {
        if (! $user->hasPermission('home.moments.view')) {
            throw new AccessDeniedHttpException('You are not allowed to react to moments.');
        }

        $moment = CompanyMoment::query()
            ->with(['author.employee', 'reactions.user.employee'])
            ->where('company_id', $user->company_id)
            ->find($momentId);

        if (! $moment) {
            throw new NotFoundHttpException('Moment not found.');
        }

        $existing = CompanyMomentReaction::query()
            ->where('company_moment_id', $moment->id)
            ->where('user_id', $user->id)
            ->first();

        if ($reaction === null) {
            $existing?->delete();
        } elseif ($existing) {
            if ($existing->reaction === $reaction) {
                $existing->delete();
            } else {
                $existing->update(['reaction' => $reaction]);
            }
        } else {
            CompanyMomentReaction::query()->create([
                'company_moment_id' => $moment->id,
                'user_id' => $user->id,
                'reaction' => $reaction,
            ]);
        }

        $moment = CompanyMoment::query()
            ->with([
                'author.employee',
                'reactions.user.employee',
                'attachments',
                'comments' => fn ($query) => $query->latest()->limit(5)->with('user.employee'),
            ])
            ->withCount('comments')
            ->where('company_id', $user->company_id)
            ->find($momentId);

        return $this->transformMoment($moment, $user);
    }

    private function upsertSystemMoment(
        int $companyId,
        string $type,
        string $occasionDate,
        Employee $employee,
        string $content,
        array $extraMetadata = [],
    ): void {
        $metadata = array_merge([
            'employee_id' => $employee->id,
            'employee_name' => $employee->full_name,
            'employee_code' => $employee->employee_code,
        ], $extraMetadata);

        $moment = CompanyMoment::query()->firstOrCreate(
            [
                'company_id' => $companyId,
                'type' => $type,
                'occasion_date' => $occasionDate,
                'metadata->employee_id' => $employee->id,
            ],
            [
                'content' => $content,
                'metadata' => $metadata,
                'published_at' => now(),
            ]
        );

        if ($moment->wasRecentlyCreated) {
            $this->notifyCompanyAboutMoment($moment);
        }
    }

    private function notifyCompanyAboutMoment(CompanyMoment $moment, ?User $excludeUser = null): void
    {
        $typeLabel = match ($moment->type) {
            CompanyMoment::TYPE_BIRTHDAY => 'Birthday',
            CompanyMoment::TYPE_WORK_ANNIVERSARY => 'Work anniversary',
            CompanyMoment::TYPE_NEW_JOINEE => 'New joiner',
            default => 'New post',
        };

        $metadata = $moment->metadata ?? [];
        $celebratedName = $metadata['employee_name'] ?? null;
        $authorName = $moment->author?->employee?->full_name ?: $moment->author?->name;

        $title = $celebratedName
            ? "New {$typeLabel}: {$celebratedName}"
            : "New moment: {$typeLabel}";

        $body = $moment->content
            ?: ($authorName ? "{$authorName} shared a moment." : 'A new moment was added to the company feed.');

        $actionUrl = '/home/moments';

        User::query()
            ->where('company_id', $moment->company_id)
            ->when($excludeUser, fn ($query) => $query->where('id', '!=', $excludeUser->id))
            ->chunkById(100, function ($users) use ($moment, $title, $body, $actionUrl) {
                foreach ($users as $user) {
                    if (! $user->hasPermission('home.moments.view')) {
                        continue;
                    }

                    try {
                        UserNotification::query()->create([
                            'company_id' => $moment->company_id,
                            'user_id' => $user->id,
                            'type' => UserNotification::TYPE_MOMENT_NEW,
                            'title' => $title,
                            'body' => mb_strimwidth($body, 0, 240, '…'),
                            'action_url' => $actionUrl,
                            'related_type' => 'company_moment',
                            'related_id' => $moment->id,
                        ]);
                    } catch (\Throwable $exception) {
                        Log::warning('Moment notification failed.', [
                            'user_id' => $user->id,
                            'moment_id' => $moment->id,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            });
    }

    /** @param  array<int, int>  $postCountByUser */
    private function transformMoment(CompanyMoment $moment, User $viewer, array $postCountByUser = []): array
    {
        $reactions = $moment->reactions;
        $counts = [];

        foreach (CompanyMomentReaction::REACTIONS as $reaction) {
            $counts[$reaction] = $reactions->where('reaction', $reaction)->count();
        }

        $viewerReaction = $reactions->firstWhere('user_id', $viewer->id);

        return [
            'id' => $moment->id,
            'type' => $moment->type,
            'content' => $moment->content,
            'metadata' => $moment->metadata ?? [],
            'occasion_date' => $moment->occasion_date?->format('Y-m-d'),
            'published_at' => $moment->published_at?->toIso8601String(),
            'author' => $this->transformAuthor($moment, $postCountByUser),
            'reactions' => [
                'counts' => $counts,
                'total' => $reactions->count(),
                'viewer_reaction' => $viewerReaction?->reaction,
            ],
            'comments' => [
                'count' => (int) ($moment->comments_count ?? $moment->comments->count()),
                'items' => $moment->comments
                    ->map(fn (CompanyMomentComment $comment) => $this->transformComment($comment))
                    ->values()
                    ->all(),
            ],
            'attachments' => $moment->attachments
                ->map(fn (CompanyMomentAttachment $attachment) => $this->transformAttachment($attachment))
                ->values()
                ->all(),
        ];
    }

    private function transformAttachment(CompanyMomentAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'url' => $attachment->fileUrl(),
            'mime_type' => $attachment->mime_type,
            'file_size' => $attachment->file_size,
            'is_image' => $attachment->isImage(),
            'is_pdf' => $attachment->isPdf(),
        ];
    }

    private function transformComment(CompanyMomentComment $comment): array
    {
        $author = $comment->user;

        return [
            'id' => $comment->id,
            'content' => $comment->content,
            'created_at' => $comment->created_at?->toIso8601String(),
            'author' => [
                'id' => $author?->id,
                'name' => $author?->employee?->full_name ?: $author?->name,
            ],
        ];
    }

    /** @param  array<int, int>  $postCountByUser */
    private function transformAuthor(CompanyMoment $moment, array $postCountByUser = []): ?array
    {
        if ($moment->type !== CompanyMoment::TYPE_POST) {
            $metadata = $moment->metadata ?? [];
            $author = $moment->author;

            if ($author) {
                return [
                    'type' => 'user',
                    'id' => $author->id,
                    'name' => $author->employee?->full_name ?: $author->name,
                    'employee_id' => $author->employee?->id,
                    'employee_code' => $author->employee?->employee_code,
                    'celebrated_name' => $metadata['employee_name'] ?? null,
                    'post_count' => (int) ($postCountByUser[$author->id] ?? 0),
                ];
            }

            return [
                'type' => 'system',
                'name' => $metadata['employee_name'] ?? 'Team Member',
                'employee_id' => $metadata['employee_id'] ?? null,
                'employee_code' => $metadata['employee_code'] ?? null,
            ];
        }

        $author = $moment->author;

        if (! $author) {
            return null;
        }

        return [
            'type' => 'user',
            'id' => $author->id,
            'name' => $author->employee?->full_name ?: $author->name,
            'employee_id' => $author->employee?->id,
            'employee_code' => $author->employee?->employee_code,
            'post_count' => (int) ($postCountByUser[$author->id] ?? 0),
        ];
    }

    private function today(): Carbon
    {
        return Carbon::today(config('app.timezone'));
    }

    private function matchesMonthDay(Carbon $date, Carbon $today): bool
    {
        return (int) $date->format('m') === (int) $today->format('m')
            && (int) $date->format('d') === (int) $today->format('d');
    }

    private function workAnniversaryYears(Employee $employee, Carbon $occasion): int
    {
        $joiningDate = Carbon::parse(
            $employee->joining_date->toDateString(),
            config('app.timezone')
        )->startOfDay();

        return max(0, (int) $joiningDate->diffInYears($occasion));
    }

    /** @return array<string, int|null> */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    private function emptyFeed(int $page, int $perPage): array
    {
        return [
            'moments' => [],
            'pagination' => [
                'current_page' => max(1, $page),
                'last_page' => 1,
                'per_page' => max(1, min(50, $perPage)),
                'total' => 0,
                'from' => null,
                'to' => null,
            ],
        ];
    }
}
