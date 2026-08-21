<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TokenResource\Pages;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Sanctum\PersonalAccessToken;
use UnitEnum;

class TokenResource extends Resource
{
    protected static ?string $model = PersonalAccessToken::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|UnitEnum|null $navigationGroup = '系统管理';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Token 管理';

    protected static ?string $modelLabel = 'Token';

    protected static ?string $pluralModelLabel = 'Token 列表';

    public static function getNavigationBadge(): ?string
    {
        return PersonalAccessToken::count() ?: null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('tokenable.name')
                    ->label('用户')
                    ->searchable()
                    ->formatStateUsing(fn ($state, $record) => $record->tokenable->name ?? $record->tokenable->nickname ?? '用户#' . $record->tokenable_id),
                TextColumn::make('name')
                    ->label('Token 名称')
                    ->searchable(),
                TextColumn::make('last_used_at')
                    ->label('最后使用')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state ?? '从未使用'),
                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('user')
                    ->label('用户')
                    ->options(User::pluck('nickname', 'id')->filter()->toArray())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn ($q, $v) => $q->where('tokenable_id', $v))),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('撤销')
                    ->successNotification(
                        Notification::make()
                            ->title('Token 已撤销')
                            ->success(),
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('批量撤销')
                        ->successNotification(
                            Notification::make()
                                ->title('选中的 Token 已撤销')
                                ->success(),
                        ),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTokens::route('/'),
        ];
    }
}
