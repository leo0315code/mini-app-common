<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * 小程序端：当前用户可见且已发布的通知列表（含已读状态）。
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = Notification::query()
            ->where('published', true)
            ->whereHas('recipients', fn ($q) => $q->where('user_id', $user->id))
            ->with([
                'recipients' => fn ($q) => $q->where('user_id', $user->id)->withPivot('read', 'read_at'),
            ])
            ->orderByDesc('published_at')
            ->paginate(20);

        $items = $notifications->getCollection()->map(function (Notification $n) use ($user) {
            $pivot = $n->recipients->firstWhere('id', $user->id)?->pivot;

            return [
                'id' => $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'type' => $n->type,
                'read' => (bool) ($pivot->read ?? false),
                'read_at' => $pivot->read_at ?? null,
                'published_at' => $n->published_at,
            ];
        });

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => [
                'items' => $items,
                'unread_count' => Notification::query()
                    ->where('published', true)
                    ->whereHas('recipients', fn ($q) => $q->where('user_id', $user->id)->where('read', false))
                    ->count(),
                'meta' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * 小程序端：标记单条通知已读。
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $notification = Notification::query()
            ->where('published', true)
            ->whereHas('recipients', fn ($q) => $q->where('user_id', $user->id))
            ->findOrFail($id);

        $notification->recipients()->updateExistingPivot($user->id, [
            'read' => true,
            'read_at' => now(),
        ]);

        return response()->json([
            'code' => 0,
            'message' => '已标记为已读',
        ]);
    }

    /**
     * 小程序端：全部已读。
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();

        Notification::query()
            ->where('published', true)
            ->whereHas('recipients', fn ($q) => $q->where('user_id', $user->id)->where('read', false))
            ->get()
            ->each(fn (Notification $n) => $n->recipients()->updateExistingPivot($user->id, [
                'read' => true,
                'read_at' => now(),
            ]));

        return response()->json([
            'code' => 0,
            'message' => '已全部标记为已读',
        ]);
    }
}
