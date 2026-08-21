<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\PhoneController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (微信小程序后台)
|--------------------------------------------------------------------------
|
| 所有小程序接口统一挂载在 /api 前缀下，并使用 api 限流中间件。
| 受保护接口需携带 Authorization: Bearer <token>（auth:sanctum）。
|
*/

// 公开接口：微信登录、已发布公告
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/announcements', [AnnouncementController::class, 'index']);
Route::get('/announcements/{id}', [AnnouncementController::class, 'show']);

// 受保护接口
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [UserController::class, 'show']);
    Route::put('/user', [UserController::class, 'update']);
    Route::post('/user/phone', [PhoneController::class, 'bind']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // 用户反馈（登录后提交）
    Route::post('/feedback', [FeedbackController::class, 'store']);
});

