<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Models\Role;
use Filament\Forms;
use Filament\Resources\Resource;
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
use Illuminate\Database\Eloquent\Builder;
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
