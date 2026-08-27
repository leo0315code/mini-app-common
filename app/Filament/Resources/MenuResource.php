<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuResource\Pages;
use App\Models\Menu;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use UnitEnum;

class MenuResource extends Resource
{

    protected static ?string $model = Menu::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-group';

    protected static string|UnitEnum|null $navigationGroup = '系统管理';

    protected static ?int $navigationSort = 91;

    protected static ?string $navigationLabel = '菜单管理';

    protected static ?string $modelLabel = '菜单';

    protected static ?string $pluralModelLabel = '菜单列表';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('parent_id')
                    ->label('父级菜单')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('留空则为顶级菜单'),
                Forms\Components\TextInput::make('name')
                    ->label('菜单名称')
                    ->required()
                    ->maxLength(50),
                Forms\Components\TextInput::make('slug')
                    ->label('菜单标识')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(100)
                    ->helperText('权限标识，如 admin.dashboard'),
                Forms\Components\TextInput::make('icon')
                    ->label('图标')
                    ->maxLength(50)
                    ->helperText('Heroicons 名称，如 heroicon-o-home'),
                Forms\Components\TextInput::make('route')
                    ->label('路由')
                    ->maxLength(200)
                    ->helperText('Filament 路由标识，如 filament.pages.dashboard'),
                Forms\Components\TextInput::make('permission')
                    ->label('权限标识')
                    ->maxLength(100)
                    ->helperText('如 menu.view，用于代码中判断权限'),
                Forms\Components\TextInput::make('sort_order')
                    ->label('排序')
                    ->numeric()
                    ->default(0)
                    ->helperText('数字越小越靠前'),
                Forms\Components\Toggle::make('is_visible')
                    ->label('侧边栏显示')
                    ->default(true),
                Forms\Components\Toggle::make('is_active')
                    ->label('启用')
                    ->default(true),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('基础信息')->schema([
                TextEntry::make('id')->label('ID'),
                TextEntry::make('parent.name')->label('父级菜单')->placeholder('顶级菜单'),
                TextEntry::make('name')->label('菜单名称'),
                TextEntry::make('slug')->label('菜单标识'),
                TextEntry::make('icon')->label('图标')->placeholder('—'),
                TextEntry::make('route')->label('路由')->placeholder('—'),
                TextEntry::make('permission')->label('权限标识')->placeholder('—'),
                TextEntry::make('sort_order')->label('排序')->numeric(),
                TextEntry::make('is_visible')->label('侧边栏显示')->formatStateUsing(fn ($s): string => $s ? '显示' : '隐藏')->badge()->color(fn ($s): string => $s ? 'success' : 'gray'),
                TextEntry::make('is_active')->label('启用')->formatStateUsing(fn ($s): string => $s ? '启用' : '停用')->badge()->color(fn ($s): string => $s ? 'success' : 'gray'),
                TextEntry::make('created_at')->label('创建时间')->dateTime('Y-m-d H:i:s'),
                TextEntry::make('updated_at')->label('更新时间')->dateTime('Y-m-d H:i:s'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable()->toggleable(),
                TextColumn::make('name')
                    ->label('菜单名称')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function (string $state, $record) {
                        $depth = $record->depth;
                        if ($depth === 0) {
                            return $state;
                        }

                        return str_repeat('　', $depth) . '<span class="text-gray-400">└</span> ' . $state;
                    })
                    ->html(),
                TextColumn::make('lineage')
                    ->label('层级路径')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('icon')
                    ->label('图标')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('slug')
                    ->label('标识')
                    ->badge()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('permission')
                    ->label('权限标识')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('sort_order')
                    ->label('排序')
                    ->sortable()
                    ->toggleable(),
                IconColumn::make('is_visible')
                    ->label('显示')
                    ->boolean()
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('启用')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('roles_count')
                    ->label('分配角色')
                    ->counts('roles')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('启用状态'),
                TernaryFilter::make('is_visible')
                    ->label('侧边栏显示'),
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
            ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]))
            ->enhanceListExperience()
            ->defaultSort('sort_order', 'asc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMenus::route('/'),
            'view' => Pages\ViewMenu::route('/{record}'),
            'create' => Pages\CreateMenu::route('/create'),
            'edit' => Pages\EditMenu::route('/{record}/edit'),
        ];
    }
}
