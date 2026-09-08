<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserNotification;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserNotificationService
{
    public function __construct(private RequestHubService $requestHubService) {}

    public function summaryForUser(User $user): array
    {
        $unreadCount = UserNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        $hubSummary = $this->requestHubService->summaryForUser($user);

        return [
            'unread_count' => $unreadCount,
            'pending_actions_count' => $hubSummary['pending_count'],
            'badge_count' => $unreadCount + $hubSummary['pending_count'],
            'can_review' => $hubSummary['can_review'],
        ];
    }

    /** @return array{notifications: array<int, array<string, mixed>>} */
    public function listForUser(User $user, int $limit = 20): array
    {
        $notifications = UserNotification::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(max(1, min(50, $limit)))
            ->get()
            ->map(fn (UserNotification $notification) => $this->formatNotification($notification))
            ->values()
            ->all();

        return ['notifications' => $notifications];
    }

    public function markRead(User $user, int $notificationId): UserNotification
    {
        $notification = UserNotification::query()
            ->where('user_id', $user->id)
            ->find($notificationId);

        if (! $notification) {
            throw new NotFoundHttpException('Notification not found.');
        }

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return $notification->fresh();
    }

    public function markAllRead(User $user): int
    {
        return UserNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /** @return array<string, mixed> */
    private function formatNotification(UserNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'body' => $notification->body,
            'action_url' => $notification->action_url,
            'related_type' => $notification->related_type,
            'related_id' => $notification->related_id,
            'is_read' => $notification->read_at !== null,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
            'created_at_label' => $notification->created_at?->diffForHumans(),
        ];
    }
}
