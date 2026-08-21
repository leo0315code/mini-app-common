<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('suggestion')->comment('类型：suggestion 建议 / bug 缺陷 / complaint 投诉 / other 其他');
            $table->text('content')->comment('反馈内容');
            $table->string('contact')->nullable()->comment('联系方式');
            $table->string('status')->default('pending')->comment('状态：pending 待处理 / processing 处理中 / resolved 已解决 / rejected 已驳回');
            $table->text('handle_note')->nullable()->comment('处理备注/回复内容');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable()->comment('处理时间');
            $table->timestamps();

            $table->index('status');
            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
