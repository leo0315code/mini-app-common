<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Models\Menu;
use App\Models\Role;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
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
                                    ->options(fn () => static::getMenuOptions())
                                    ->columns(2)
                                    ->searchable()
                                    ->bulkToggleable()
                                    ->live()
                                    ->afterStateUpdated(fn (Forms\Components\CheckboxList $component, $state, $old) => static::cascadeMenuSelection($component, $state, $old))
                                    ->helperText('勾选父级菜单后自动勾选其全部子级；支持搜索过滤菜单'),
                            ]),
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
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'asc');
    }

    /**
     * 生成带层级缩进的菜单选项（父级在前，子级缩进）
     */
    public static function getMenuOptions(): array
    {
        $menus = Menu::query()->active()->orderBy('sort_order')->get();

        $children = [];
        foreach ($menus as $menu) {
            $children[$menu->parent_id][] = $menu;
        }

        $options = [];
        $walk = function ($parentId, int $level) use (&$walk, $children, &$options): void {
            foreach ($children[$parentId] ?? [] as $menu) {
                $options[$menu->id] = $level === 0
                    ? $menu->name
                    : str_repeat('　', $level).'└ '.$menu->name;
                $walk($menu->id, $level + 1);
            }
        };
        $walk(null, 0);

        return $options;
    }

    /**
     * 服务端级联：
     * - 勾选父级菜单 → 自动勾选其全部子孙菜单
     * - 取消父级菜单 → 自动取消其全部子孙菜单
     */
    public static function cascadeMenuSelection(Forms\Components\CheckboxList $component, mixed $state, mixed $old = null): void
    {
        $new = collect((array) ($state ?? []))->map(fn ($id) => (int) $id)->values();
        $prev = collect((array) ($old ?? []))->map(fn ($id) => (int) $id)->values();

        if ($new->isEmpty() && $prev->isEmpty()) {
            return;
        }

        $childrenMap = [];
        foreach (Menu::query()->active()->whereNotNull('parent_id')->get() as $menu) {
            $childrenMap[$menu->parent_id][] = $menu->id;
        }

        $getDescendants = function (int $id) use (&$getDescendants, $childrenMap): array {
            $result = [];
            foreach ($childrenMap[$id] ?? [] as $childId) {
                $result[] = $childId;
                $result = array_merge($result, $getDescendants($childId));
            }

            return $result;
        };

        // 本次新勾选的菜单 → 联动勾选其子孙
        $added = $new->diff($prev);
        $selected = $new->merge($added->flatMap(fn ($id) => $getDescendants($id)));

        // 本次新取消的菜单 → 联动取消其子孙
        $removed = $prev->diff($new);
        $removedDescendants = collect($removed->flatMap(fn ($id) => $getDescendants($id)))->push(...$removed);

        $selected = $selected
            ->diff($removedDescendants)
            ->unique()
            ->values()
            ->all();

        $component->state($selected);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
