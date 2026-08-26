<?php

namespace Tests\Feature;

use App\Filament\Pages\EditPassword;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login as LoginEvent;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mini_program' => ['app_id' => 'x', 'secret' => 'y']]);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('old-pass-123'),
        ]);
    }

    public function test_login_success_writes_audit(): void
    {
        $admin = $this->admin();

        // 真实派发，验证监听器已落地数据库（不要 Event::fake，否则拦截真实监听）
        event(new LoginEvent('web', $admin, true));

        $this->assertDatabaseHas('audit_logs', [
            'type' => 'login',
            'module' => 'auth',
            'user_id' => $admin->id,
        ]);
    }

    public function test_login_failed_writes_audit(): void
    {
        event(new Failed('web', null, ['email' => 'hacker@example.com']));

        $this->assertDatabaseHas('audit_logs', [
            'type' => 'login_failed',
            'module' => 'auth',
        ]);
    }

    public function test_logout_writes_audit(): void
    {
        $admin = $this->admin();

        event(new Logout('web', $admin));

        $this->assertDatabaseHas('audit_logs', [
            'type' => 'logout',
            'module' => 'auth',
            'user_id' => $admin->id,
        ]);
    }

    public function test_password_page_accessible_to_admin(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/console/edit-password')
            ->assertOk();
    }

    public function test_change_password_updates_and_old_invalid(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(EditPassword::class)
            ->set('current_password', 'old-pass-123')
            ->set('password', 'new-pass-456')
            ->set('password_confirmation', 'new-pass-456')
            ->call('submit');

        $admin->refresh();
        $this->assertTrue(Hash::check('new-pass-456', $admin->password));
        $this->assertFalse(Hash::check('old-pass-123', $admin->password));
    }

    public function test_change_password_rejects_wrong_current(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(EditPassword::class)
            ->set('current_password', 'wrong-pass')
            ->set('password', 'new-pass-456')
            ->set('password_confirmation', 'new-pass-456')
            ->call('submit');

        $admin->refresh();
        // 密码不应被修改
        $this->assertTrue(Hash::check('old-pass-123', $admin->password));
    }
}
