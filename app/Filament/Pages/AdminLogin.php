<?php

namespace App\Filament\Pages;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * 后台登录页（在 Filament 内置 Login 基础上叠加账号级防爆破限流）。
 *
 * Filament 内置 authenticate() 已带 IP 级限流（每 IP 5 次/分钟），
 * 这里额外按「邮箱 + 来源 IP」维度计数，防止攻击者换 IP 针对单个管理员账号暴力破解。
 */
class AdminLogin extends BaseLogin
{
    /**
     * 账号级限流阈值：同一邮箱+IP 在 1 分钟内最多尝试 5 次。
     */
    protected int $accountMaxAttempts = 5;

    public function authenticate(): ?LoginResponse
    {
        $data = $this->form->getState();
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $key = $this->throttleKey($email);

        if (RateLimiter::tooManyAttempts($key, $this->accountMaxAttempts)) {
            Notification::make()
                ->title('登录尝试过于频繁')
                ->body('该账号在 '.RateLimiter::availableIn($key).' 秒内尝试次数过多，请稍后再试。')
                ->danger()
                ->send();

            return null;
        }

        try {
            $response = parent::authenticate();
        } catch (ValidationException $e) {
            // 失败尝试累加计数（爆破防护）
            RateLimiter::hit($key);

            throw $e;
        }

        // 成功登录后清除该账号失败计数
        RateLimiter::clear($key);

        return $response;
    }

    protected function throttleKey(string $email): string
    {
        return 'admin-login:'.sha1($email.'|'.request()->ip());
    }

    /**
     * 登录页副标题（品牌标语）。本后台关闭了注册，基类默认返回 null，
     * 这里展示一句系统定位标语，强化品牌认知。
     */
    public function getSubheading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return '社区健康服务后台 · 用户运营与内容管理';
    }
}
