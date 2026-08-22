<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Media;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ObserverCoveragesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Notification 新建、修改、删除都落审计日志（仅看 module=notification 记录）
     */
    public function test_notification_crud_is_audited(): void
    {
        $initialNotifLogs = AuditLog::where('module', 'notification')->count();

        $notification = Notification::factory()->create([
            'title' => '系统消息',
            'published' => false,
        ]);

        $notifLogsAfterCreate = AuditLog::where('module', 'notification')->count();
        $this->assertSame($initialNotifLogs + 1, $notifLogsAfterCreate);
        $firstLog = AuditLog::where('module', 'notification')->latest('id')->first();
        $this->assertSame('create', $firstLog->type);

        $notification->update(['title' => '系统消息-更新']);
        $notifLogsAfterUpdate = AuditLog::where('module', 'notification')->count();
        $this->assertSame($initialNotifLogs + 2, $notifLogsAfterUpdate);
        $updateLog = AuditLog::where('module', 'notification')->latest('id')->first();
        $this->assertSame('update', $updateLog->type);

        $notification->delete();
        $notifLogsAfterDelete = AuditLog::where('module', 'notification')->count();
        $this->assertSame($initialNotifLogs + 3, $notifLogsAfterDelete);
        $deleteLog = AuditLog::where('module', 'notification')->latest('id')->first();
        $this->assertSame('delete', $deleteLog->type);
    }

    /**
     * Media 新建、删除都落审计日志
     */
    public function test_media_crud_is_audited(): void
    {
        Storage::fake('public');

        $initialMediaLogs = AuditLog::where('module', 'media')->count();
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('sample.jpg');
        $path = $file->store('test', 'public');
        $url = Storage::disk('public')->url($path);

        $media = Media::create([
            'user_id' => $user->id,
            'collection' => 'images',
            'file_name' => 'sample.jpg',
            'path' => $path,
            'disk' => 'public',
            'mime_type' => 'image/jpeg',
            'url' => $url,
            'size' => 1024,
        ]);

        $mediaLogsAfterCreate = AuditLog::where('module', 'media')->count();
        $this->assertSame($initialMediaLogs + 1, $mediaLogsAfterCreate);
        $log = AuditLog::where('module', 'media')->latest('id')->first();
        $this->assertSame('create', $log->type);

        $media->delete();
        $mediaLogsAfterDelete = AuditLog::where('module', 'media')->count();
        $this->assertSame($initialMediaLogs + 2, $mediaLogsAfterDelete);
        $deleteLog = AuditLog::where('module', 'media')->latest('id')->first();
        $this->assertSame('delete', $deleteLog->type);
    }
}