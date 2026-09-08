<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Services\UserNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponse;

    public function __construct(private UserNotificationService $notificationService) {}

    public function summary(Request $request): JsonResponse
    {
        return $this->success($this->notificationService->summaryForUser($request->user()));
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return $this->success($this->notificationService->listForUser(
            $request->user(),
            (int) ($validated['limit'] ?? 20),
        ));
    }

    public function markRead(Request $request, int $notification): JsonResponse
    {
        $updated = $this->notificationService->markRead($request->user(), $notification);

        return $this->success([
            'notification' => [
                'id' => $updated->id,
                'is_read' => $updated->read_at !== null,
            ],
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = $this->notificationService->markAllRead($request->user());

        return $this->success(['marked' => $count], "{$count} notification(s) marked as read.");
    }
}
