<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use Illuminate\Http\JsonResponse;

class BannerController extends Controller
{
    /**
     * 小程序端获取生效中的运营位列表（公开接口）。
     * 按 sort_order 升序返回，包含图片地址与跳转信息。
     */
    public function index(): JsonResponse
    {
        $banners = Banner::active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (Banner $banner) {
                return [
                    'id' => $banner->id,
                    'title' => $banner->title,
                    'image' => $banner->imageUrl(),
                    'link_type' => $banner->link_type,
                    'article_id' => $banner->link_type === Banner::LINK_ARTICLE ? (int) $banner->article_id : null,
                    'url' => $banner->link_type === Banner::LINK_URL ? $banner->url : null,
                    'sort_order' => (int) $banner->sort_order,
                ];
            });

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $banners,
        ]);
    }
}
