<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    /**
     * 小程序端：上传单个文件，落库 media 记录并返回可访问 URL。
     */
    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            // mime 白名单：覆盖业务声明的图片+文档类型；
            // 排除 svg（可内嵌脚本，存储型 XSS 风险）与一切可执行/脚本类型（php/html/js 等）
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,bmp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,md,csv,zip,rar'],
            'collection' => ['nullable', 'string', 'max:40'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 42200,
                'message' => '校验失败',
                'errors' => $validator->errors(),
            ], 422);
        }

        $file = $request->file('file');
        $collection = $request->input('collection', 'default');

        $path = $file->store('uploads/' . $collection, 'public');

        $media = Media::create([
            'user_id' => $request->user()->id,
            'collection' => $collection,
            'file_name' => $file->getClientOriginalName(),
            'path' => $path,
            'disk' => 'public',
            'mime_type' => $file->getMimeType(), // 服务端检测，不信任客户端声明的 mime
            'url' => Storage::disk('public')->url($path),
            'size' => $file->getSize(),
            'meta' => ['original_name' => $file->getClientOriginalName()],
        ]);

        return response()->json([
            'code' => 0,
            'message' => '上传成功',
            'data' => [
                'id' => $media->id,
                'url' => $media->url,
                'file_name' => $media->file_name,
                'size' => $media->size,
            ],
        ]);
    }
}
