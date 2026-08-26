<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * P4 工程化门禁：校验数据库迁移与代码是否同步。
 *
 * 用于本地开发自检、部署前预检、CI 门禁。若存在未执行的迁移文件则返回非零退出码，
 * 防止「测试用 SQLite 全过、上线 MySQL 缺列 500」这类 schema 脱节事故。
 */
class DbCheck extends Command
{
    protected $signature = 'db:check';

    protected $description = '校验数据库迁移与代码是否同步（存在未执行迁移则非零退出）';

    public function handle(): int
    {
        // 1. 文件系统侧的迁移文件
        $migrationFiles = collect(File::glob(database_path('migrations/*.php')))
            ->map(fn ($path) => basename($path, '.php'))
            ->sort()
            ->values();

        // 2. 数据库已执行的迁移
        $ran = collect(
            \Illuminate\Support\Facades\DB::table('migrations')->pluck('migration')->toArray()
        )->sort()->values();

        $pending = $migrationFiles->diff($ran);

        if ($pending->isEmpty()) {
            $this->info('✓ 数据库迁移与代码同步，无待执行迁移（共 '.$migrationFiles->count().' 个）。');

            return self::SUCCESS;
        }

        $this->error('✗ 存在 '.count($pending).' 个未执行的迁移，数据库与代码 schema 脱节：');
        foreach ($pending as $name) {
            $this->line('  - '.$name);
        }
        $this->newLine();
        $this->warn('请先执行迁移：');
        $this->line('  php artisan migrate --force');
        $this->newLine();
        $this->comment('（如需预览将执行的 SQL：php artisan migrate --pretend）');

        return self::FAILURE;
    }
}
