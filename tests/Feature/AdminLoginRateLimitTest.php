<?php

namespace Tests\Feature;

use App\Filament\Pages\AdminLogin;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class AdminLoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mini_program' => ['app_id' => 'x', 'secret' => 'y']]);
    }

    private function attemptWrongLogin(string $email = 'attacker@brute.test'): void
    {
        Livewire::test(AdminLogin::class)
            ->set('data.email', $email)
            ->set('data.password', 'wrong-password')
            ->call('authenticate');
    }

    public function test_wrong_password_does_not_authenticate(): void
    {
        Livewire::test(AdminLogin::class)
            ->set('data.email', 'nobody@brute.test')
            ->set('data.password', 'wrong')
            ->call('authenticate');

        // 登录失败：仍停留在登录页（未认证、未重定向到后台）
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'nobody@brute.test']);
    }

    public function test_account_level_rate_limit_kicks_in_after_threshold(): void
    {
        $email = 'attacker@brute.test';
        $key = 'admin-login:'.sha1(strtolower($email).'|127.0.0.1');

        // 清空可能存在的计数，保证测试确定性
        RateLimiter::clear($key);

        $before = AuditLog::where('type', 'login_failed')->count();

        // 前 5 次失败：每次都应触发 Failed 事件并写审计
        for ($i = 1; $i <= 5; $i++) {
            $this->attemptWrongLogin($email);
        }

        $this->assertEquals($before + 5, AuditLog::where('type', 'login_failed')->count(),
            '前 5 次失败应各写一条 login_failed 审计');

        // 第 6 次：应被账号级限流提前拦截，不再触发 Failed 事件 / 写审计
        $this->attemptWrongLogin($email);

        $this->assertEquals($before + 5, AuditLog::where('type', 'login_failed')->count(),
            '第 6 次应被限流拦截，不再写 login_failed 审计');

        // 限流窗口内 RateLimiter 应标记该 key 达到上限
        $this->assertTrue(RateLimiter::tooManyAttempts($key, 5));
    }

    public function test_successful_login_clears_rate_limit_counter(): void
    {
        $user = \App\Models\User::factory()->create([
            'email' => 'realadmin@brute.test',
            'password' => bcrypt('right-pass-123'),
        ]);

        $key = 'admin-login:'.sha1(strtolower($user->email).'|127.0.0.1');
        RateLimiter::clear($key);

        Livewire::test(AdminLogin::class)
            ->set('data.email', $user->email)
            ->set('data.password', 'right-pass-123')
            ->call('authenticate');

        $this->assertFalse(RateLimiter::tooManyAttempts($key, 5),
            '成功登录后应清除该账号失败计数');
    }
}
