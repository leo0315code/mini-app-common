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
            'file' => ['required', 'file', 'max:10240'], // 10MB
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
            'mime_type' => $file->getClientMimeType(),
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
