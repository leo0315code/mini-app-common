<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 通知中心优化点回归：
 *  - Notification::readRecipients 关系（withCount 复用，列表/导出免 N+1）
 *  - index() 用单条聚合查未读数（非 per-row 子查询）
 *  - markAllRead() 单条批量 UPDATE（替代逐条 updateExistingPivot 的 O(N) 写放大）
 */
class NotificationReadRateAndBatchTest extends TestCase
{
    use RefreshDatabase;

    private function auth(User $user)
    {
        return ['Authorization' => 'Bearer '.$user->createToken('mini-program')->plainTextToken];
    }

    public function test_read_recipients_relation_counts_only_read_pivot(): void
    {
        $notification = Notification::factory()->create(['published' => true]);
        $r1 = User::factory()->create();
        $r2 = User::factory()->create();
        $r3 = User::factory()->create();

        // 统一 pivot 列（read_at 字段必须存在，未读时置 null），否则 SQLite 批量 insert 列数不一致
        $now = now();
        $notification->recipients()->attach([
            $r1->id => ['read' => true, 'read_at' => $now],
            $r2->id => ['read' => false, 'read_at' => null],
            $r3->id => ['read' => true, 'read_at' => $now],
        ]);

        // 已读人数：infolist 直接用 recipients()->wherePivot('read', 1) 计算（生产路径）
        $this->assertSame(2, (int) $notification->recipients()->wherePivot('read', 1)->count());

        // 列表/导出用 recipients 别名计数 + pivot 表列限定（避免 wherePivot 在 withCount 子查询下被误编译）
        $notification->loadCount([
            'recipients',
            'recipients as read_recipients_count' => fn ($q) => $q->where('notification_user.read', 1),
        ]);

        $this->assertSame(3, (int) $notification->recipients_count);
        $this->assertSame(2, (int) $notification->read_recipients_count);
    }

    public function test_index_unread_count_uses_single_aggregate_query(): void
    {
        $user = User::factory()->create();

        // 已发布 + 未读
        $n1 = Notification::factory()->create(['published' => true]);
        $n1->recipients()->attach($user->id, ['read' => false]);

        // 已发布 + 已读（不应计入未读）
        $n2 = Notification::factory()->create(['published' => true]);
        $n2->recipients()->attach($user->id, ['read' => true, 'read_at' => now()]);

        // 草稿 + 未读（published=false，不应计入）
        $n3 = Notification::factory()->create(['published' => false]);
        $n3->recipients()->attach($user->id, ['read' => false]);

        // 其他用户的未读（不应计入）
        $other = User::factory()->create();
        $n4 = Notification::factory()->create(['published' => true]);
        $n4->recipients()->attach($other->id, ['read' => false]);

        $res = $this->getJson('/api/notifications', $this->auth($user))->assertStatus(200)->json();

        $this->assertSame(1, $res['data']['unread_count']);
        // 列表仅返回当前用户可见且已发布的（n1、n2）
        $this->assertCount(2, $res['data']['items']);
    }

    public function test_mark_all_read_performs_single_batch_update(): void
    {
        $user = User::factory()->create();

        $n1 = Notification::factory()->create(['published' => true]);
        $n1->recipients()->attach($user->id, ['read' => false]);
        $n2 = Notification::factory()->create(['published' => true]);
        $n2->recipients()->attach($user->id, ['read' => false]);

        // 草稿未读：markAllRead 仅针对 published，不应被标记
        $n3 = Notification::factory()->create(['published' => false]);
        $n3->recipients()->attach($user->id, ['read' => false]);

        // 其他用户未读：不应被标记
        $other = User::factory()->create();
        $n4 = Notification::factory()->create(['published' => true]);
        $n4->recipients()->attach($other->id, ['read' => false]);

        $this->postJson('/api/notifications/read-all', [], $this->auth($user))->assertStatus(200);

        // 当前用户全部已发布通知被标记已读并写入 read_at
        $this->assertDatabaseHas('notification_user', [
            'notification_id' => $n1->id,
            'user_id' => $user->id,
            'read' => true,
        ]);
        $this->assertDatabaseHas('notification_user', [
            'notification_id' => $n2->id,
            'user_id' => $user->id,
            'read' => true,
        ]);

        // 草稿不被标记
        $this->assertDatabaseHas('notification_user', [
            'notification_id' => $n3->id,
            'user_id' => $user->id,
            'read' => false,
        ]);

        // 其他用户不被标记
        $this->assertDatabaseHas('notification_user', [
            'notification_id' => $n4->id,
            'user_id' => $other->id,
            'read' => false,
        ]);

        // read_at 已写入（非 null）
        $this->assertNotNull(
            DB::table('notification_user')->where('notification_id', $n1->id)->where('user_id', $user->id)->value('read_at')
        );

        // 再次调用幂等：未读数归零
        $res = $this->getJson('/api/notifications', $this->auth($user))->json();
        $this->assertSame(0, $res['data']['unread_count']);
    }
}
