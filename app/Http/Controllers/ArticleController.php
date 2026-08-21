<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    /**
     * 小程序端获取已发布文章列表（可经 ?category_id 按频道过滤）。
     */
    public function index(Request $request): JsonResponse
    {
        $query = Article::published()->with(['category:id,name,slug', 'author:id,nickname,name']);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', '%' . $keyword . '%')
                    ->orWhere('summary', 'like', '%' . $keyword . '%');
            });
        }

        $list = $query->ordered()->paginate(20);

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $list->items(),
        ]);
    }

    /**
     * 小程序端文章详情（自增浏览数）。
     */
    public function show(int $id): JsonResponse
    {
        $article = Article::published()
            ->with(['category:id,name,slug', 'author:id,nickname,name'])
            ->find($id);

        if (! $article) {
            return response()->json([
                'code' => 40400,
                'message' => '文章不存在或未发布',
            ], 404);
        }

        $article->increment('views');

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $article,
        ]);
    }

    /**
     * 小程序端分类列表（仅启用分类，按排序返回）。
     */
    public function categories(): JsonResponse
    {
        $categories = Category::active()->ordered()->get(['id', 'name', 'slug', 'description']);

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $categories,
        ]);
    }
}
