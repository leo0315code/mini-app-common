<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\NotificationController;
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

// 公开接口：微信登录、已发布公告、首页运营位
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/announcements', [AnnouncementController::class, 'index']);
Route::get('/announcements/{id}', [AnnouncementController::class, 'show']);
Route::get('/banners', [BannerController::class, 'index']);

// 公开接口：内容中心 CMS
Route::get('/article-categories', [ArticleController::class, 'categories']);
Route::get('/articles', [ArticleController::class, 'index']);
Route::get('/articles/{id}', [ArticleController::class, 'show']);

// 受保护接口（auth:banned 在鉴权后拦截已封禁用户）
Route::middleware(['auth:sanctum', 'auth.banned'])->group(function () {
    Route::get('/user', [UserController::class, 'show']);
    Route::put('/user', [UserController::class, 'update']);
    Route::post('/user/phone', [PhoneController::class, 'bind']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // 我的设备 / 会话管理（P3-11）：设备列表、单台踢下线、一键踢下线
    Route::get('/me/devices', [DeviceController::class, 'index']);
    Route::delete('/me/devices/{id}', [DeviceController::class, 'destroy']);
    Route::delete('/me/devices', [DeviceController::class, 'destroyOthers']);

    // 用户反馈（登录后提交）
    Route::post('/feedback', [FeedbackController::class, 'store']);

    // 站内通知（登录后查看/已读）
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    // 文件/媒体上传
    Route::post('/upload', [MediaController::class, 'upload']);
});

/*
|--------------------------------------------------------------------------
| 管理员 API 接口（基于菜单权限保护）
|--------------------------------------------------------------------------
|
| 以下接口受 auth:sanctum + auth.banned + menu.permission 三重保护。
| 只有拥有对应菜单权限的后台账号才能访问。
|
| 中间件用法：menu.permission:article.view,article.manage
| 表示需要至少拥有 article.view 或 article.manage 权限之一。
|
*/

Route::middleware(['auth:sanctum', 'auth.banned', 'menu.permission'])->group(function () {
    // 只读接口：拥有对应菜单的 .view 权限即可访问
    Route::middleware('menu.permission:article.view')->group(function () {
        // Route::get('/admin/articles', [AdminArticleController::class, 'index']);
    });

    // 写操作：需要 .manage 权限
    Route::middleware('menu.permission:article.manage')->group(function () {
        // Route::post('/admin/articles', [AdminArticleController::class, 'store']);
        // Route::put('/admin/articles/{id}', [AdminArticleController::class, 'update']);
        // Route::delete('/admin/articles/{id}', [AdminArticleController::class, 'destroy']);
    });

    // 系统管理：需要 menu.manage 或 role.manage 权限
    Route::middleware('menu.permission:menu.manage,role.manage')->group(function () {
        // Route::get('/admin/roles', [AdminRoleController::class, 'index']);
        // Route::post('/admin/roles', [AdminRoleController::class, 'store']);
    });
});

