<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscribe_message_failures', function (Blueprint $table) {
            $table->id();
            $table->string('job_uuid')->nullable()->index();
            $table->string('scene')->index()->comment('场景：feedback_handled/announcement_published/notification_published/direct');
            $table->nullableMorphs('subject');
            $table->string('openid')->index();
            $table->string('template_id');
            $table->json('payload')->nullable();
            $table->string('page')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->integer('last_errcode')->nullable();
            $table->text('last_errmsg')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('resolved_at')->nullable()->comment('人工处理时间');
            $table->text('resolved_note')->nullable();
            $table->timestamps();

            $table->index(['scene', 'created_at']);
            $table->index(['openid', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscribe_message_failures');
    }
};
