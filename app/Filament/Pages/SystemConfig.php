<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\MenuPermissionManager;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Auth;
use UnitEnum;
use BackedEnum;

class SystemConfig extends Page
{
    protected static ?string $navigationLabel = '系统配置';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|UnitEnum|null $navigationGroup = '系统管理';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = '系统配置';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $manager = app(MenuPermissionManager::class);

        return $manager->hasAnyPermission($user, ['settings.view', 'settings.manage']);
    }

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->loadData();
        $this->form->fill($this->data);
    }

    /**
     * 从 settings 表（缺失时回退 env）读取各分组配置。
     */
    protected function loadData(): void
    {
        $this->data = [
            'mini_program' => Setting::getGroup('mini_program', [
                'app_id' => config('services.mini_program.app_id', ''),
                'secret' => config('services.mini_program.secret', ''),
            ]),
            'cors' => Setting::getGroup('cors', [
                'allowed_origins' => config('cors.allowed_origins')[0] ?? '*',
                'max_age' => config('cors.max_age', 0),
            ]),
            'security' => Setting::getGroup('security', [
                'token_expiration' => config('sanctum.expiration') ?? '',
                'token_prefix' => config('sanctum.token_prefix', ''),
            ]),
            'general' => Setting::getGroup('general', [
                'brand_name' => config('filament.panels.admin.brand_name', '宏图爱'),
            ]),
        ];
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('微信小程序')
                    ->description('小程序 app_id / secret，用于 code2session 与手机号解密。')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('mini_program.app_id')
                                ->label('AppID')
                                ->required(),
                            TextInput::make('mini_program.secret')
                                ->label('AppSecret')
                                ->password()
                                ->required(),
                        ]),
                    ]),

                Section::make('跨域（CORS）')
                    ->description('允许的请求来源，多个用逗号分隔；留空或 * 表示不限制。')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('cors.allowed_origins')
                                ->label('允许的来源')
                                ->placeholder('* 或 https://a.com,https://b.com'),
                            TextInput::make('cors.max_age')
                                ->label('预检缓存秒数')
                                ->numeric()
                                ->default(0),
                        ]),
                    ]),

                Section::make('安全')
                    ->description('API Token 有效期与前缀。有效期填 0 或留空表示永久有效。')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('security.token_expiration')
                                ->label('Token 有效期（分钟）')
                                ->numeric()
                                ->default(0),
                            TextInput::make('security.token_prefix')
                                ->label('Token 前缀')
                                ->placeholder('留空表示不设前缀'),
                        ]),
                    ]),

                Section::make('站点')
                    ->schema([
                        TextInput::make('general.brand_name')
                            ->label('后台品牌名')
                            ->required(),
                    ]),
            ]);
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return Action::make('save')
            ->label('保存配置')
            ->submit('save')
            ->keyBindings(['mod+s']);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach (['mini_program', 'cors', 'security', 'general'] as $group) {
            Setting::setGroup($group, $data[$group] ?? []);
        }

        $this->applyToRuntime($data);

        Notification::make()
            ->success()
            ->title('配置已保存')
            ->send();
    }

    /**
     * 将保存的配置同步到本次请求运行时 config，便于即时生效。
     *
     * @param  array<string, mixed>  $data
     */
    protected function applyToRuntime(array $data): void
    {
        config([
            'services.mini_program.app_id' => $data['mini_program']['app_id'] ?? null,
            'services.mini_program.secret' => $data['mini_program']['secret'] ?? null,

        ]);

        $origins = trim((string) ($data['cors']['allowed_origins'] ?? ''));
        config([
            'cors.allowed_origins' => $origins === '' ? ['*'] : explode(',', $origins),
            'cors.max_age' => (int) ($data['cors']['max_age'] ?? 0),
        ]);

        $expiration = (int) ($data['security']['token_expiration'] ?? 0);
        config([
            'sanctum.expiration' => $expiration > 0 ? $expiration : null,
            'sanctum.token_prefix' => $data['security']['token_prefix'] ?? '',
        ]);

        config(['filament.panels.admin.brand_name' => $data['general']['brand_name'] ?? '宏图爱']);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        \Filament\Schemas\Components\Actions::make($this->getFormActions())
                            ->key('form-actions'),
                    ]),
                Callout::make('修改后配置即时写入数据库，并同步到当前请求的运行时；CORS 与 Token 配置会在下次部署/重启后按数据库值生效。')
                    ->icon('heroicon-o-information-circle')
                    ->info(),
            ]);
    }
}
