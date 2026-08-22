<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 菜单表与菜单-角色中间表。
     * 支持层级菜单和权限标识。
     */
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menus')->cascadeOnDelete()->comment('父级菜单 ID');
            $table->string('name')->comment('菜单显示名');
            $table->string('slug')->unique()->comment('菜单标识，如 admin.dashboard');
            $table->string('icon')->nullable()->comment('图标，如 heroicon-o-home');
            $table->string('route')->nullable()->comment('路由，如 filament.pages.dashboard');
            $table->string('permission')->nullable()->comment('权限标识，如 menu.view');
            $table->integer('sort_order')->default(0)->comment('排序，数字越小越靠前');
            $table->boolean('is_visible')->default(true)->comment('是否在侧边栏显示');
            $table->boolean('is_active')->default(true)->comment('是否启用');
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
        });

        Schema::create('menu_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['menu_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_role');
        Schema::dropIfExists('menus');
    }
};
