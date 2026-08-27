<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaResource\Pages;
use App\Models\Media;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class MediaResource extends Resource
{

    protected static ?string $model = Media::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static string|\UnitEnum|null $navigationGroup = '内容运营';

    protected static ?int $navigationSort = 20;

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

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('文件预览')->schema([
                ImageEntry::make('url')->label('预览')->disk('public')->height(200)->placeholder('—'),
            ]),
            Section::make('基础信息')->schema([
                TextEntry::make('id')->label('ID'),
                TextEntry::make('file_name')->label('文件名'),
                TextEntry::make('collection')->label('分组')->badge(),
                TextEntry::make('mime_type')->label('MIME 类型')->placeholder('—'),
                TextEntry::make('size')->label('大小')->formatStateUsing(fn ($s): string => round($s / 1024, 1) . ' KB'),
                TextEntry::make('user.nickname')->label('上传者')->placeholder('—'),
                TextEntry::make('created_at')->label('上传时间')->dateTime('Y-m-d H:i:s'),
                TextEntry::make('updated_at')->label('更新时间')->dateTime('Y-m-d H:i:s'),
            ])->columns(2),
            Section::make('链接')->schema([
                TextEntry::make('url')->label('文件链接')->copyable()->url(fn ($state): ?string => $state, shouldOpenInNewTab: true)->placeholder('—')->columnSpanFull(),
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
                TextColumn::make('url')
                    ->label('链接')
                    ->limit(40)
                    ->url(fn (Media $record): ?string => $record->isImage() ? $record->url : null, shouldOpenInNewTab: true)
                    ->copyable()
                    ->copyMessage('链接已复制')
                    ->tooltip(fn (Media $record): string => $record->url),
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
                SelectFilter::make('kind')
                    ->label('类型')
                    ->options([
                        'image' => '图片',
                        'document' => '文档',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        return $data['value'] === 'image'
                            ? $query->where('mime_type', 'like', 'image/%')
                            : $query->where('mime_type', 'not like', 'image/%');
                    }),
                TrashedFilter::make()->label('回收站'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ])
            ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]))
            ->enhanceListExperience()
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
            'view' => Pages\ViewMedia::route('/{record}'),
            'create' => Pages\CreateMedia::route('/create'),
            'edit' => Pages\EditMedia::route('/{record}/edit'),
        ];
    }

    /**
     * 上传时按扩展名自动归类分组（图片/文档/其他）。
     */
    public static function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['collection'] ?? null)) {
            $data['collection'] = Media::inferCollectionFromFileName($data['file_name'] ?? '');
        }

        return $data;
    }
}
