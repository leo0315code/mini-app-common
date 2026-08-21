<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaResource\Pages;
use App\Models\Media;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class MediaResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static string|\UnitEnum|null $navigationGroup = '系统管理';

    protected static ?int $navigationSort = 80;

    protected static ?string $navigationLabel = '媒体管理';

    protected static ?string $modelLabel = '媒体文件';

    protected static ?string $pluralModelLabel = '媒体列表';

    // 媒体为只读浏览 + 删除，新增通过小程序端上传接口或页面内上传组件
    public static function canCreate(): bool
    {
        return true;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('上传文件')
                    ->schema([
                        Forms\Components\FileUpload::make('upload')
                            ->label('选择文件')
                            ->disk('public')
                            ->directory('uploads')
                            ->visibility('public')
                            ->required()
                            ->helperText('支持图片/文档，保存后生成媒体记录'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('url')
                    ->label('预览')
                    ->disk('public')
                    ->size(48)
                    ->defaultImageUrl('https://ui-avatars.com/api/?name=File&background=random'),
                TextColumn::make('file_name')
                    ->label('文件名')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('collection')
                    ->label('分组')
                    ->badge()
                    ->searchable(),
                IconColumn::make('mime_type')
                    ->label('类型')
                    ->boolean(fn (string $state): bool => str_starts_with($state, 'image/'))
                    ->trueIcon('heroicon-o-photo')
                    ->falseIcon('heroicon-o-document')
                    ->trueColor('success')
                    ->falseColor('gray'),
                TextColumn::make('size')
                    ->label('大小')
                    ->formatStateUsing(fn (int $state): string => round($state / 1024, 1) . ' KB')
                    ->sortable(),
                TextColumn::make('user.nickname')
                    ->label('上传者')
                    ->formatStateUsing(fn ($state, $record) => $record->user?->nickname ?? ($record->user?->name ?? '—')),
                TextColumn::make('created_at')
                    ->label('上传时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('collection')
                    ->label('分组')
                    ->options(fn () => Media::query()->distinct()->pluck('collection', 'collection')->filter()->all()),
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
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMedia::route('/'),
            'create' => Pages\CreateMedia::route('/create'),
            'edit' => Pages\EditMedia::route('/{record}/edit'),
        ];
    }
}
