<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create([
            'email' => 'api@example.com',
            'password' => bcrypt('secret'),
        ]);
    }

    /**
     * 已发布公告应出现在公开列表中。
     */
    public function test_published_announcements_listed_publicly(): void
    {
        Announcement::factory()->count(3)->create(['status' => Announcement::STATUS_PUBLISHED]);
        Announcement::factory()->draft()->create();

        $this->getJson('/api/announcements')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonCount(3, 'data');
    }

    /**
     * 草稿公告不应被公开接口返回。
     */
    public function test_draft_announcement_hidden(): void
    {
        $draft = Announcement::factory()->draft()->create();

        $this->getJson('/api/announcements/' . $draft->id)
            ->assertStatus(404)
            ->assertJsonPath('code', 40400);
    }

    /**
     * 已发布公告详情可访问。
     */
    public function test_published_announcement_detail(): void
    {
        $a = Announcement::factory()->create(['status' => Announcement::STATUS_PUBLISHED]);

        $this->getJson('/api/announcements/' . $a->id)
            ->assertOk()
            ->assertJsonPath('data.id', $a->id);
    }

    /**
     * 未登录提交反馈应返回 401。
     */
    public function test_submit_feedback_requires_auth(): void
    {
        $this->postJson('/api/feedback', ['type' => 'bug', 'content' => 'xx'])
            ->assertStatus(401);
    }

    /**
     * 登录用户可提交反馈，且缺字段返回 422。
     */
    public function test_submit_feedback_requires_content(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->postJson('/api/feedback', ['type' => 'bug'])
            ->assertStatus(422);
    }

    /**
     * 登录用户提交反馈成功，落库且默认待处理。
     */
    public function test_submit_feedback_success(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->postJson('/api/feedback', [
                'type' => Feedback::TYPE_SUGGESTION,
                'content' => '希望增加夜间模式',
                'contact' => 'wx_123',
            ])
            ->assertOk()
            ->assertJsonPath('code', 0);

        $this->assertDatabaseHas('feedback', [
            'user_id' => $user->id,
            'type' => Feedback::TYPE_SUGGESTION,
            'status' => Feedback::STATUS_PENDING,
        ]);
    }

    /**
     * 反馈新增应写入审计日志。
     */
    public function test_feedback_creation_logged(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->postJson('/api/feedback', [
                'type' => Feedback::TYPE_BUG,
                'content' => '闪退',
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'feedback',
            'type' => 'create',
        ]);
    }
}
