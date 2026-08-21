<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    /**
     * 小程序端获取已发布公告列表。
     */
    public function index(Request $request): JsonResponse
    {
        $query = Announcement::published();

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $list = $query->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $list->items(),
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
