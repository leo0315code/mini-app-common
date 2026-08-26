<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Tables\Table;
use App\Filament\Pages\AdminLogin;
use App\Http\Middleware\InjectAdminStyles;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // 全局表格体验增强：列拖拽/显隐 + 搜索防抖 + 筛选延迟应用
        // 注册为 Table 宏，供各 Resource 在 table() 中 ->enhanceListExperience() 链式调用
        Table::macro('enhanceListExperience', function (): Table {
            /** @var Table $this */
            return $this
                ->reorderableColumns()
                ->searchDebounce('500ms')
                ->deferFilters();
        });

        return $panel
            ->default()
            ->id('admin')
            ->path('console')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(AdminLogin::class)
            ->brandName('宏图爱')
            ->brandLogo(asset('logo-light.svg'))
            ->darkModeBrandLogo(asset('logo-dark.svg'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('favicon.svg'))
            ->darkMode(false)
            ->colors([
                'primary' => Color::hex('#0D9488'),
                'gray' => Color::hex('#475569'),
            ])
            ->font('Instrument Sans')
            ->userMenuItems([
                \Filament\Navigation\MenuItem::make()
                    ->label('修改密码')
                    ->url(fn (): string => \App\Filament\Pages\EditPassword::getUrl())
                    ->icon('heroicon-o-key'),
            ])
            ->maxContentWidth(\Filament\Support\Enums\Width::Full)
            ->navigationGroups([
                '内容运营',
                '系统管理',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                InjectAdminStyles::class,
                \App\Http\Middleware\NoCacheHeaders::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
