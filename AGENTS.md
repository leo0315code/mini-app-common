# AGENTS.md

## What this is
Laravel 13 / PHP 8.3 backend for "one admin backend, plug in any WeChat Mini Program". WeChat login via `code2session`, Sanctum bearer-token auth, unified API JSON format, and a Filament v5 admin panel at `/admin`.

Authoritative (Chinese) docs live in `docs/` — read `docs/auth.md`, `docs/config.md`, `docs/structure.md`, `docs/testing.md` before touching related code.

## Commands
- Run all tests: `php artisan test` (23 tests, in-memory SQLite via `phpunit.xml`, no DB/WeChat needed). Single file: `php artisan test tests/Feature/UserTest.php`; single test: `php artisan test --filter=test_login_success`.
- `composer test` runs `config:clear` first — do the same after editing config files.
- Dev server: `php artisan serve` (admin at `/admin`). Frontend assets: `npm run build` (Vite; only needed for Filament admin styling).
- No lint step in CI; `vendor/bin/pint` is available if you choose to format.

## Conventions that differ from defaults
- All API responses use `{"code": 0, "message": "...", "data": ...}`; error codes are `<HTTP status><NN>` (40100, 42200, 40400, 42900...). Error rendering is centralized in `bootstrap/app.php` — keep new API errors in this format; never return raw Laravel error JSON.
- API routes live in `routes/api.php` behind `auth:sanctum`; a global `api` throttle (60/min) is defined in `app/Providers/AppServiceProvider.php`.
- User model uses PHP 8 attributes `#[Fillable([...])]` / `#[Hidden([...])]` (Laravel 13 style), **not** `$fillable`/`$hidden` properties. Follow the attribute style for new models.
- Filament admin access: `User::canAccessPanel()` returns true only when `email` + `password` are set. Normal mini-program users have only `openid` (no email/password).
- WeChat credentials always come from `config('services.mini_program')` (env `MINI_PROGRAM_APP_ID` / `MINI_PROGRAM_SECRET`). Never hardcode.
- UI text, comments, commit messages, and docs are in Chinese (zh_CN).

## Testing quirks
- Tests must never hit the real WeChat API. In each test, set `config(['services.mini_program' => ['app_id' => 'x', 'secret' => 'y']])` **and** `Http::fake()` the WeChat endpoints; otherwise `WechatService` throws "未配置".
- Tests use `RefreshDatabase` + `:memory:` SQLite.

## Git
- Conventional Commits, written in Chinese: `feat(auth): 新增...`, `fix(user): 修复...`.
- Branch from `main`; tag releases `v1.x.x` (SemVer). Docs on this: `docs/install.md`.
