<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FeedbackController extends Controller
{
    /**
     * 小程序端提交反馈（需登录）。
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'in:suggestion,bug,complaint,other'],
            'content' => ['required', 'string', 'max:2000'],
            'contact' => ['nullable', 'string', 'max:200'],
        ], [
            'type.required' => '请选择反馈类型',
            'type.in' => '反馈类型不合法',
            'content.required' => '反馈内容不能为空',
            'content.max' => '反馈内容过长',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 42200,
                'message' => '参数校验失败',
                'errors' => $validator->errors(),
            ], 422);
        }

        $feedback = Feedback::create([
            'user_id' => $request->user()->id,
            'type' => $request->input('type'),
            'content' => $request->input('content'),
            'contact' => $request->input('contact'),
            'status' => Feedback::STATUS_PENDING,
        ]);

        return response()->json([
            'code' => 0,
            'message' => '反馈已提交，感谢您的支持',
            'data' => ['id' => $feedback->id],
        ]);
    }
}
