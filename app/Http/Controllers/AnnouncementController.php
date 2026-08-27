<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Feedback;
use App\Support\ContentCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function __construct(
        protected ContentCacheService $cache,
    ) {}

    /**
     * 小程序端获取已发布公告列表。
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->input('type');

        $list = $this->cache->remember('announcements_'.($type ?: 'all'), function () use ($type) {
            $query = Announcement::published();

            if ($type) {
                $query->where('type', $type);
            }

            return $query->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate(20)
                ->items();
        });

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $list,
        ]);
    }

    /**
     * 小程序端获取公告详情。
     */
    public function show(int $id): JsonResponse
    {
        $announcement = Announcement::published()->find($id);

        if (! $announcement) {
            return response()->json([
                'code' => 40400,
                'message' => '公告不存在或未发布',
            ], 404);
        }

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $announcement,
        ]);
    }
}
