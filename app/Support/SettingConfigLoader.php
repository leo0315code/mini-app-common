<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

/**
 * 把后台「系统配置」页保存的分组配置在应用启动时加载进运行时 config。
 *
 * 背景：SystemConfig 页保存写 settings 表，但此前没有任何地方在请求启动时
 * 把保存值覆盖回 config —— 保存仅当次请求生效，重启后回落 .env 默认值。
 * 本类在 AppServiceProvider::boot 注册，保证每次请求/队列/命令启动都能读到
 * 后台配置（含订阅消息模板 ID、CORS、安全参数等）。
 *
 * 优先级：settings 表保存值 > .env/config 默认值（即后台保存过则以后台为准）。
 */
class SettingConfigLoader
{
    /**
     * group => 对应 config 键前缀
     */
    protected const MAP = [
        'mini_program' => 'services.mini_program',
        'cors' => 'services.cors',
        'security' => 'services.security',
        'general' => 'services.general',
    ];

    public function load(): void
    {
        // 容错：迁移未执行（如测试 boot 阶段、初始化部署）时静默跳过，
        // 避免 boot 阶段查不存在的表抛异常导致应用无法启动
        if (! Schema::hasTable('settings')) {
            return;
        }

        foreach (self::MAP as $group => $configPrefix) {
            $values = Setting::getGroup($group);

            if (empty($values)) {
                continue;
            }

            $merged = array_merge(
                config($configPrefix, []),
                $values,
            );

            config([$configPrefix => $merged]);
        }
    }
}
