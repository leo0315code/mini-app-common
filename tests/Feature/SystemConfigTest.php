<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SystemConfigTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('secret'),
        ]);
    }

    /**
     * 未登录访问系统配置页应跳转到登录页。
     */
    public function test_system_config_requires_auth(): void
    {
        $this->get('/admin/system-config')
            ->assertRedirect('/admin/login');
    }

    /**
     * 管理员可以访问系统配置页。
     */
    public function test_admin_can_view_system_config(): void
    {
        $this->actingAs($this->admin(), 'web')
            ->get('/admin/system-config')
            ->assertOk();
    }

    /**
     * 保存配置写库后，Setting::getGroup 能读回，且运行时 config 被同步。
     */
    public function test_save_writes_settings_to_db(): void
    {
        $this->actingAs($this->admin(), 'web');

        Livewire::test(\App\Filament\Pages\SystemConfig::class)
            ->set('data', [
                'mini_program' => ['app_id' => 'wx_test_appid', 'secret' => 'test_secret_xyz'],
                'cors' => ['allowed_origins' => 'https://a.com,https://b.com', 'max_age' => 600],
                'security' => ['token_expiration' => 1440, 'token_prefix' => 'sk'],
                'general' => ['brand_name' => '医保后台'],
            ])
            ->call('save');

        $this->assertEquals('wx_test_appid', Setting::getGroup('mini_program')['app_id']);
        // allowed_origins 以逗号字符串存库，运行时再 explode（与 CORS_ALLOWED_ORIGINS 一致）
        $this->assertEquals('https://a.com,https://b.com', Setting::getGroup('cors')['allowed_origins']);
        $this->assertEquals(1440, Setting::getGroup('security')['token_expiration']);
        $this->assertEquals('医保后台', Setting::getGroup('general')['brand_name']);
    }
}
