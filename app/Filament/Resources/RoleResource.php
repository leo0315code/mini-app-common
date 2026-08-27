<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Models\Role;
use App\Support\MenuCascadeService;
use App\Support\RolePresetTemplates;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use UnitEnum;

class RoleResource extends Resource
{

    protected static ?string $model = Role::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|UnitEnum|null $navigationGroup = '系统管理';

    protected static ?int $navigationSort = 90;

    protected static ?string $navigationLabel = '角色管理';

    protected static ?string $modelLabel = '角色';

    protected static ?string $pluralModelLabel = '角色列表';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('标签页')
                    ->tabs([
                        Tab::make('基本信息')
                            ->schema([
                                Forms\Components\Select::make('preset_template')
                                    ->label('使用预设模板')
                                    ->placeholder('选择后将自动填充权限（可后续调整）')
                                    ->options(fn () => static::getPresetOptions())
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                                        if (! $state) {
                                            return;
                                        }

                                        $presets = RolePresetTemplates::all();
                                        if (! isset($presets[$state])) {
                                            return;
                                        }

                                        $preset = $presets[$state];
                                        $menuIds = RolePresetTemplates::getMenuIdsForPermissions($preset['permissions']);
                                        $menuIds = app(MenuCascadeService::class)->ensureCascadeConsistency($menuIds);

                                        $set('name', $preset['name']);
                                        $set('slug', $preset['slug'] . '_' . time());
                                        $set('description', $preset['description']);
                                        $set('menus', $menuIds);
                                    })
                                    ->helperText('选择预设模板后可自动填充名称、标识和菜单权限，之后可自由调整'),
                                Forms\Components\TextInput::make('name')
                                    ->label('角色名称')
                                    ->required()
                                    ->maxLength(50),
                                Forms\Components\TextInput::make('slug')
                                    ->label('角色标识')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(50)
                                    ->helperText('英文标识，如 super-admin / admin / editor / viewer，建后勿随意改动'),
                                Forms\Components\Textarea::make('description')
                                    ->label('角色说明')
                                    ->rows(2)
                                    ->maxLength(255),
                            ]),
                        Tab::make('菜单权限')
                            ->schema([
                                Forms\Components\CheckboxList::make('menus')
                                    ->label('分配菜单权限')
                                    ->relationship('menus', 'name')
                                    ->options(fn () => app(MenuCascadeService::class)->getMenuOptions())
                                    ->columns(2)
                                    ->searchable()
                                    ->bulkToggleable()
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Components\CheckboxList $component, mixed $state, mixed $old) {
                                        $result = app(MenuCascadeService::class)->cascadeSelection($state, $old);
                                        $component->state($result);
                                    })
                                    ->helperText('勾选父级自动勾选子级；取消父级自动取消子级。支持搜索和全选/反选。'),
                            ]),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('基础信息')->schema([
                TextEntry::make('id')->label('ID'),
                TextEntry::make('name')->label('角色名称'),
                TextEntry::make('slug')->label('角色标识'),
                TextEntry::make('description')->label('角色说明')->placeholder('—')->columnSpanFull(),
                TextEntry::make('users_count')->label('关联用户数')->numeric()
                    ->state(fn (Role $record): int => $record->users()->count())
                    ->placeholder('0'),
                TextEntry::make('created_at')->label('创建时间')->dateTime('Y-m-d H:i:s'),
                TextEntry::make('updated_at')->label('更新时间')->dateTime('Y-m-d H:i:s'),
            ])->columns(2),
            Section::make('菜单权限')->schema([
                TextEntry::make('menus.name')->label('已分配菜单')->badge()->listWithLineBreaks()->bulleted()->placeholder('未分配'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable()->toggleable(),
                TextColumn::make('name')->label('角色名称')->searchable()->sortable(),
                TextColumn::make('slug')
                    ->label('标识')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'super-admin' => 'danger',
                        'admin' => 'warning',
                        'editor' => 'info',
                        'viewer' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('menus_count')
                    ->label('菜单权限')
                    ->counts('menus')
                    ->sortable(),
                TextColumn::make('users_count')
                    ->label('成员数')
                    ->counts('users')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('slug')
                    ->label('角色标识')
                    ->options([
                        'super-admin' => '超级管理员',
                        'admin' => '管理员',
                        'editor' => '编辑',
                        'viewer' => '访客',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('clone')
                    ->label('克隆')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('克隆角色')
                    ->modalDescription(fn (Role $record) => "将复制角色「{$record->name}」及其全部菜单权限，创建为新角色。")
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('新角色名称')
                            ->required()
                            ->maxLength(50),
                        Forms\Components\TextInput::make('slug')
                            ->label('新角色标识')
                            ->required()
                            ->maxLength(50)
                            ->helperText('英文标识，需唯一'),
                    ])
                    ->action(function (Role $record, array $data): Role {
                        $clone = $record->replicate();
                        $clone->name = $data['name'];
                        $clone->slug = $data['slug'];
                        $clone->save();

                        $clone->menus()->sync($record->menus->pluck('id')->toArray());

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title("角色「{$record->name}」已克隆为「{$clone->name}」")
                            ->send();

                        return $clone;
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulkClone')
                        ->label('批量克隆')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('批量克隆角色')
                        ->modalDescription('将复制所选角色及其全部菜单权限，创建为新角色（名称加「副本」后缀）。')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                $clone = $record->replicate();
                                $clone->name = $record->name.' 副本';
                                $clone->slug = $record->slug.'_copy_'.time();
                                $clone->save();
                                $clone->menus()->sync($record->menus->pluck('id')->toArray());
                                $count++;
                            }

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title("已克隆 {$count} 个角色")
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]))
            ->enhanceListExperience()
            ->defaultSort('id', 'asc');
    }

    /**
     * 获取预设模板选项
     */
    public static function getPresetOptions(): array
    {
        $presets = RolePresetTemplates::all();
        $options = [];
        foreach ($presets as $key => $preset) {
            $options[$key] = $preset['name'] . ' — ' . $preset['description'];
        }

        return $options;
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        if (! isset($data['menus']) || ! is_array($data['menus'])) {
            return $data;
        }

        $data['menus'] = app(MenuCascadeService::class)->ensureCascadeConsistency($data['menus']);

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'view' => Pages\ViewRole::route('/{record}'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
