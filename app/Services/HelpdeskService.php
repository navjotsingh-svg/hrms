<?php

namespace App\Services;

use App\Models\HelpdeskTicket;
use App\Models\HelpdeskTicketComment;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class HelpdeskService
{
    public function __construct(
        private EmployeeAccessService $employeeAccessService,
        private ActivityLogService $activityLogService,
        private HelpdeskCategoryService $categoryService,
        private HelpdeskAttachmentService $attachmentService,
    ) {}

    public function listForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = HelpdeskTicket::query()
            ->with(['employee', 'createdBy', 'assignedTo', 'category'])
            ->where('company_id', $user->company_id)
            ->latest('updated_at');

        if ($user->canManageHelpdesk()) {
            if (! empty($filters['employee_id'])) {
                $query->where('employee_id', (int) $filters['employee_id']);
            }
        } else {
            $employee = $this->employeeAccessService->linkedEmployee($user);

            if (! $employee) {
                throw new AccessDeniedHttpException('No employee profile is linked to your account.');
            }

            $query->where('employee_id', $employee->id);
        }

        foreach (['status', 'priority'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (! empty($filters['helpdesk_category_id'])) {
            $query->where('helpdesk_category_id', (int) $filters['helpdesk_category_id']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($builder) use ($search) {
                $builder->where('subject', 'like', "%{$search}%")
                    ->orWhere('ticket_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function openCountForManagers(User $user): int
    {
        if (! $user->canManageHelpdesk()) {
            return 0;
        }

        return HelpdeskTicket::query()
            ->where('company_id', $user->company_id)
            ->whereIn('status', [HelpdeskTicket::STATUS_OPEN, HelpdeskTicket::STATUS_IN_PROGRESS])
            ->count();
    }

    public function create(User $user, array $data, array $files = []): HelpdeskTicket
    {
        if (! $user->canApplyHelpdesk()) {
            throw new AccessDeniedHttpException('You are not allowed to raise helpdesk tickets.');
        }

        $employee = $this->employeeAccessService->linkedEmployee($user);

        if (! $employee) {
            throw ValidationException::withMessages([
                'employee' => ['No employee profile is linked to your account.'],
            ]);
        }

        $category = $this->categoryService->resolveActiveCategory(
            (int) $employee->company_id,
            (int) $data['helpdesk_category_id'],
        );

        return DB::transaction(function () use ($user, $employee, $data, $category, $files) {
            $ticket = HelpdeskTicket::query()->create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'created_by_user_id' => $user->id,
                'ticket_number' => $this->generateTicketNumber((int) $employee->company_id),
                'subject' => trim($data['subject']),
                'description' => trim($data['description']),
                'helpdesk_category_id' => $category->id,
                'priority' => $data['priority'] ?? 'medium',
                'status' => HelpdeskTicket::STATUS_OPEN,
            ]);

            if ($files !== []) {
                $this->attachmentService->storeMany($ticket, $employee, $files);
            }

            HelpdeskTicketComment::query()->create([
                'helpdesk_ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'body' => trim($data['description']),
                'is_internal' => false,
            ]);

            $this->activityLogService->logChange(
                $user,
                'helpdesk_tickets',
                'helpdesk.created',
                $ticket,
                (int) $ticket->id,
                "Helpdesk ticket {$ticket->ticket_number} created.",
                [],
                ['status' => $ticket->status],
                request(),
            );

            $this->notifyManagers($ticket, $user, 'New helpdesk ticket raised', "{$user->name} raised ticket {$ticket->ticket_number}: {$ticket->subject}");

            return $ticket->fresh(['employee', 'createdBy', 'assignedTo', 'resolvedBy', 'category', 'attachments']);
        });
    }

    public function showForUser(User $user, HelpdeskTicket $ticket): HelpdeskTicket
    {
        $this->assertSameCompany($user, $ticket);

        if (! $user->canViewHelpdeskTicket($ticket)) {
            throw new AccessDeniedHttpException('You are not allowed to view this ticket.');
        }

        $ticket->load([
            'employee',
            'createdBy',
            'assignedTo',
            'resolvedBy',
            'category',
            'attachments',
            'comments' => fn ($query) => $query
                ->with('user')
                ->when(! $user->canManageHelpdesk(), fn ($builder) => $builder->where('is_internal', false))
                ->oldest(),
        ]);

        return $ticket;
    }

    public function addComment(User $user, HelpdeskTicket $ticket, array $data): HelpdeskTicketComment
    {
        $this->assertSameCompany($user, $ticket);

        if (! $user->canCommentOnHelpdeskTicket($ticket)) {
            throw new AccessDeniedHttpException('You are not allowed to comment on this ticket.');
        }

        $isInternal = (bool) ($data['is_internal'] ?? false);

        if ($isInternal && ! $user->canManageHelpdesk()) {
            throw new AccessDeniedHttpException('Only helpdesk managers can add internal notes.');
        }

        if ($ticket->status === HelpdeskTicket::STATUS_CLOSED) {
            throw ValidationException::withMessages([
                'status' => ['This ticket is closed. Reopen it before adding updates.'],
            ]);
        }

        $comment = DB::transaction(function () use ($user, $ticket, $data, $isInternal) {
            $comment = HelpdeskTicketComment::query()->create([
                'helpdesk_ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'body' => trim($data['body']),
                'is_internal' => $isInternal,
            ]);

            $ticket->touch();

            return $comment->load('user');
        });

        if (! $isInternal) {
            if ($user->canManageHelpdesk()) {
                $this->notifyTicketOwner($ticket, $user, 'Helpdesk update on your ticket', "HR replied on {$ticket->ticket_number}: {$ticket->subject}");
            } else {
                $this->notifyManagers($ticket, $user, 'New reply on helpdesk ticket', "{$user->name} replied on {$ticket->ticket_number}");
            }
        }

        return $comment;
    }

    public function updateStatus(User $user, HelpdeskTicket $ticket, string $status): HelpdeskTicket
    {
        $this->assertSameCompany($user, $ticket);

        if (! $user->canManageHelpdeskTicket($ticket)) {
            throw new AccessDeniedHttpException('You are not allowed to update this ticket.');
        }

        $allowed = array_keys(config('helpdesk.statuses', []));
        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => ['Invalid ticket status.']]);
        }

        DB::transaction(function () use ($user, $ticket, $status) {
            $previous = $ticket->status;
            $updates = ['status' => $status];

            if ($status === HelpdeskTicket::STATUS_IN_PROGRESS && ! $ticket->assigned_to_user_id) {
                $updates['assigned_to_user_id'] = $user->id;
            }

            if ($status === HelpdeskTicket::STATUS_RESOLVED) {
                $updates['resolved_by_user_id'] = $user->id;
                $updates['resolved_at'] = now();
            }

            if ($status === HelpdeskTicket::STATUS_CLOSED) {
                $updates['closed_at'] = now();
            }

            if (in_array($status, [HelpdeskTicket::STATUS_OPEN, HelpdeskTicket::STATUS_IN_PROGRESS], true)) {
                $updates['resolved_by_user_id'] = null;
                $updates['resolved_at'] = null;
                $updates['closed_at'] = null;
            }

            $ticket->update($updates);

            $this->activityLogService->logChange(
                $user,
                'helpdesk_tickets',
                'helpdesk.status_updated',
                $ticket,
                (int) $ticket->id,
                "Helpdesk ticket {$ticket->ticket_number} status changed to {$status}.",
                ['status' => $previous],
                ['status' => $status],
                request(),
            );
        });

        $ticket = $ticket->fresh(['employee', 'createdBy', 'assignedTo', 'resolvedBy']);

        $this->notifyTicketOwner(
            $ticket,
            $user,
            'Helpdesk ticket status updated',
            "Ticket {$ticket->ticket_number} is now ".config("helpdesk.statuses.{$status}", ucfirst($status))
        );

        return $ticket;
    }

    public function assign(User $user, HelpdeskTicket $ticket, ?int $assigneeUserId): HelpdeskTicket
    {
        $this->assertSameCompany($user, $ticket);

        if (! $user->canManageHelpdeskTicket($ticket)) {
            throw new AccessDeniedHttpException('You are not allowed to assign this ticket.');
        }

        $ticket->update([
            'assigned_to_user_id' => $assigneeUserId,
            'status' => $ticket->status === HelpdeskTicket::STATUS_OPEN
                ? HelpdeskTicket::STATUS_IN_PROGRESS
                : $ticket->status,
        ]);

        return $ticket->fresh(['employee', 'createdBy', 'assignedTo', 'resolvedBy']);
    }

    private function generateTicketNumber(int $companyId): string
    {
        $latest = HelpdeskTicket::query()
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->value('ticket_number');

        $next = 1;
        if ($latest && preg_match('/(\d+)$/', $latest, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return 'HD-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function assertSameCompany(User $user, HelpdeskTicket $ticket): void
    {
        if ((int) $user->company_id !== (int) $ticket->company_id) {
            throw new NotFoundHttpException('Helpdesk ticket not found.');
        }
    }

    private function notifyManagers(HelpdeskTicket $ticket, User $actor, string $title, string $body): void
    {
        User::query()
            ->where('company_id', $ticket->company_id)
            ->where('id', '!=', $actor->id)
            ->chunkById(100, function ($users) use ($ticket, $title, $body) {
                foreach ($users as $user) {
                    if (! $user->canManageHelpdesk()) {
                        continue;
                    }

                    UserNotification::query()->create([
                        'company_id' => $ticket->company_id,
                        'user_id' => $user->id,
                        'type' => UserNotification::TYPE_HELPDESK_SUBMITTED,
                        'title' => $title,
                        'body' => mb_strimwidth($body, 0, 240, '…'),
                        'action_url' => '/helpdesk/'.$ticket->id,
                        'related_type' => 'helpdesk_ticket',
                        'related_id' => $ticket->id,
                    ]);
                }
            });
    }

    private function notifyTicketOwner(HelpdeskTicket $ticket, User $actor, string $title, string $body): void
    {
        $owner = $ticket->employee?->user;

        if (! $owner || (int) $owner->id === (int) $actor->id) {
            return;
        }

        UserNotification::query()->create([
            'company_id' => $ticket->company_id,
            'user_id' => $owner->id,
            'type' => UserNotification::TYPE_HELPDESK_UPDATE,
            'title' => $title,
            'body' => mb_strimwidth($body, 0, 240, '…'),
            'action_url' => '/helpdesk/'.$ticket->id,
            'related_type' => 'helpdesk_ticket',
            'related_id' => $ticket->id,
        ]);
    }
}
