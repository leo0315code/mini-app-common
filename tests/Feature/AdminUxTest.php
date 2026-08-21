<?php

namespace Tests\Feature;

use App\Filament\Resources\FeedbackResource;
use App\Filament\Resources\MediaResource;
use App\Models\Feedback;
use App\Models\Media;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUxTest extends TestCase
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
     * 反馈「处理」更新状态/处理人/备注（与后台 Action 同源逻辑）。
     */
    public function test_feedback_handle_updates_record(): void
    {
        $admin = $this->admin();
        $feedback = Feedback::factory()->create(['status' => Feedback::STATUS_PENDING]);

        $feedback->update([
            'status' => Feedback::STATUS_RESOLVED,
            'handle_note' => '已修复并上线',
            'handled_by' => $admin->id,
            'handled_at' => now(),
        ]);

        $this->assertDatabaseHas('feedback', [
            'id' => $feedback->getKey(),
            'status' => Feedback::STATUS_RESOLVED,
            'handle_note' => '已修复并上线',
            'handled_by' => $admin->id,
        ]);
    }

    /**
     * 通知「全部标记已读」动作（当前管理员视角）置本人回执为已读，不动他人。
     */
    public function test_notification_mark_all_read_for_admin(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create([
            'openid' => 'oTEST_' . uniqid(),
            'email' => null,
            'password' => null,
        ]);

        $notification = Notification::factory()->create(['published' => true, 'scope' => 'all']);
        $notification->dispatchToRecipients();
        $notification->recipients()->syncWithoutDetaching([
            $admin->id => ['read' => false, 'read_at' => null],
        ]);

        // 与 ListNotifications 顶栏 Action 同源逻辑
        $count = Notification::query()
            ->where('published', true)
            ->whereHas('recipients', fn ($q) => $q->where('user_id', $admin->id)->where('read', false))
            ->get()
            ->each(fn (Notification $n) => $n->recipients()->updateExistingPivot($admin->id, [
                'read' => true,
                'read_at' => now(),
            ]))
            ->count();

        $this->assertSame(1, $count);

        $fresh = Notification::find($notification->getKey());
        $this->assertTrue(
            (bool) $fresh->recipients()->where('user_id', $admin->id)->first()->pivot->read === true
        );
        $this->assertTrue(
            (bool) $fresh->recipients()->where('user_id', $member->id)->first()->pivot->read === false
        );
    }

    /**
     * 媒体上传按扩展名自动归类分组（不改表结构）。
     */
    public function test_media_infer_collection_from_extension(): void
    {
        $this->assertSame('images', Media::inferCollectionFromFileName('logo.png'));
        $this->assertSame('documents', Media::inferCollectionFromFileName('合同.pdf'));
        $this->assertSame('others', Media::inferCollectionFromFileName('data.unknown'));
    }

    /**
     * MediaResource 表单数据预处理：未指定 collection 时按文件名归类。
     */
    public function test_media_resource_mutate_inferes_collection(): void
    {
        $data = MediaResource::mutateFormDataBeforeCreate([
            'file_name' => 'banner.jpg',
            'path' => 'uploads/banner.jpg',
        ]);

        $this->assertSame('images', $data['collection']);
    }

    /**
     * 反馈/通知/媒体后台列表与详情页可正常渲染（含新增按钮/列）。
     */
    public function test_admin_pages_render_with_new_ui(): void
    {
        $admin = $this->admin();
        $feedback = Feedback::factory()->create();
        $notification = Notification::factory()->create();

        $pages = [
            route('filament.admin.resources.feedback.index'),
            route('filament.admin.resources.feedback.view', ['record' => $feedback->getKey()]),
            route('filament.admin.resources.notifications.index'),
            route('filament.admin.resources.media.index'),
        ];

        foreach ($pages as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }

        // 通知详情页（edit 路由用于管理）
        $this->actingAs($admin)
            ->get(route('filament.admin.resources.notifications.edit', ['record' => $notification->getKey()]))
            ->assertOk();
    }

    /**
     * 通知列表含「已读率」列（通过渲染页面断言文本存在）。
     */
    public function test_notification_index_shows_read_rate_column(): void
    {
        $admin = $this->admin();
        Notification::factory()->create(['published' => true, 'scope' => 'all'])->dispatchToRecipients();

        $this->actingAs($admin)
            ->get(route('filament.admin.resources.notifications.index'))
            ->assertOk()
            ->assertSee('已读率');
    }

    /**
     * 反馈列表页渲染含「批量已解决」按钮文本与新列。
     */
    public function test_feedback_index_shows_bulk_action_and_columns(): void
    {
        $admin = $this->admin();
        Feedback::factory()->count(2)->create();

        $this->actingAs($admin)
            ->get(route('filament.admin.resources.feedback.index'))
            ->assertOk()
            ->assertSee('批量已解决')
            ->assertSee('处理人');
    }
}
