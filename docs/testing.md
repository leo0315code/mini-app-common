# 测试指南

本项目使用 **PHPUnit 12** 进行单元测试与集成测试，测试文件位于 `tests/` 目录。

---

## 1. 目录结构

```
tests/
├── TestCase.php              # 基础测试类（继承 Laravel 默认 TestCase）
├── Feature/                  # 功能测试（HTTP 请求级别）
│   └── ExampleTest.php       # 示例：首页返回 200
└── Unit/                     # 单元测试（类/方法级别）
    └── ExampleTest.php       # 示例：true === true
```

---

## 2. 运行测试

```bash
# 运行全部测试
php artisan test

# 运行指定测试文件
php artisan test tests/Feature/AuthTest.php

# 运行指定测试方法
php artisan test --filter=test_login_success

# 使用 PHPUnit 原生命令（更灵活）
vendor/bin/phpunit

# 带覆盖率报告（需 xdebug）
php artisan test --coverage
```

---

## 3. 测试分类

### 3.1 单元测试（Unit）

测试单个类或方法的逻辑，不依赖 HTTP 请求。

```php
// tests/Unit/WechatServiceTest.php
namespace Tests\Unit;

use App\Services\WechatService;
use Tests\TestCase;

class WechatServiceTest extends TestCase
{
    public function test_code2session_returns_openid(): void
    {
        $service = $this->mock(WechatService::class, function ($mock) {
            $mock->shouldReceive('code2Session')
                ->with('test_code')
                ->andReturn(['openid' => 'oABC', 'unionid' => null, 'session_key' => 'sk']);
        });

        $result = $service->code2Session('test_code');
        $this->assertEquals('oABC', $result['openid']);
    }
}
```

### 3.2 功能测试（Feature）

模拟 HTTP 请求，测试完整的请求 → 响应链路。

```php
// tests/Feature/AuthTest.php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_requires_code(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertStatus(422);
    }

    public function test_login_returns_token(): void
    {
        // 模拟微信接口返回
        Http::fake([
            'api.weixin.qq.com/*' => Http::response([
                'openid' => 'oTEST123',
                'session_key' => 'sk_test',
            ]),
        ]);

        $this->postJson('/api/auth/login', ['code' => 'test_code'])
            ->assertStatus(200)
            ->assertJsonStructure(['token', 'token_type', 'user']);
    }

    public function test_get_user_requires_auth(): void
    {
        $this->getJson('/api/user')
            ->assertStatus(401);
    }

    public function test_get_user_returns_current_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/user')
            ->assertStatus(200)
            ->assertJsonPath('id', $user->id);
    }
}
```

---

## 4. 常用测试工具

| 工具 | 用途 |
| --- | --- |
| `RefreshDatabase` | 每次测试前迁移数据库，测试后回滚 |
| `Http::fake()` | 拦截 HTTP 请求（如微信 API），返回模拟响应 |
| `actingAs($user)` | 以指定用户身份发起请求 |
| `assertJsonStructure()` | 验证 JSON 响应结构 |
| `assertJsonPath()` | 验证 JSON 响应中某个字段的值 |
| `postJson()` / `getJson()` | 发送 JSON 请求 |

---

## 5. 测试数据库

- 单元测试和功能测试默认使用 **内存 SQLite**（`:memory:`），速度最快。
- 在 `phpunit.xml` 中配置：

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

---

## 6. 测试命名规范

- 测试方法名：`test_<场景描述>`，使用 `snake_case`
- 示例：`test_login_returns_token`、`test_logout_invalidates_token`
- 一个方法只测一个场景，保持测试独立

---

## 7. 持续集成

建议在 CI（如 GitHub Actions）中自动运行测试：

```yaml
# .github/workflows/tests.yml
name: Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      - run: composer install --no-interaction
      - run: cp .env.example .env && php artisan key:generate
      - run: php artisan test
```
