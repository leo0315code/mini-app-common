<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // CI / 全新 checkout 没有 public/build 产物（gitignore），
        // Filament 布局的 @vite 会因 manifest 缺失抛 ViewException（500）。
        // 测试不依赖前端资源，统一禁用 Vite 解析。
        $this->withoutVite();
    }
}
