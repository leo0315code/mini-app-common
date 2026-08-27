<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BannerResource\Pages;
use App\Models\Article;
use App\Models\Banner;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class BannerResource extends Resource
{

    protected static ?string $model = Banner::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static string|\UnitEnum|null $navigationGroup = '内容运营';

    protected static ?int $navigationSort = 15;

    protected static ?string $navigationLabel = '运营位管理';

    protected static ?string $modelLabel = '运营位';

    protected static ?string $pluralModelLabel = '运营位列表';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('图片素材')
                    ->schema([
                        Forms\Components\FileUpload::make('image')
                            ->label('Banner 图片')
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('banners')
                            ->required()
                            ->maxSize(4096)
                            ->helperText('建议尺寸 750×300 以上，单张不超过 4MB'),
                    ]),
                Section::make('基础信息')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('标题')
                            ->required()
                            ->maxLength(120)
                            ->helperText('仅用于后台辨识，不下发小程序'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('启用')
                            ->default(true)
                            ->inline(false),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('排序')
                            ->numeric()
                            ->default(0)
                            ->helperText('越小越靠前'),
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('生效开始')
                            ->helperText('留空表示立即生效'),
                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label('生效结束')
                            ->helperText('留空表示长期有效'),
                    ])->columns(3),
                Section::make('跳转配置')
                    ->schema([
                        Forms\Components\Select::make('link_type')
                            ->label('跳转类型')
                            ->options([
                                Banner::LINK_NONE => '不跳转',
                                Banner::LINK_ARTICLE => '跳转文章',
                                Banner::LINK_URL => '跳转链接',
                            ])
                            ->default(Banner::LINK_NONE)
                            ->live()
                            ->required(),
                        Forms\Components\Select::make('article_id')
                            ->label('关联文章')
                            ->options(fn () => Article::query()->orderByDesc('id')->limit(200)->pluck('title', 'id'))
                            ->searchable()
                            ->visible(fn (Get $get) => $get('link_type') === Banner::LINK_ARTICLE),
                        Forms\Components\TextInput::make('url')
                            ->label('跳转链接')
                            ->url()
                            ->maxLength(500)
                            ->visible(fn (Get $get) => $get('link_type') === Banner::LINK_URL)
                            ->helperText('https:// 开头的完整链接'),
                    ])->columns(2),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('图片素材')->schema([
                ImageEntry::make('image')->label('Banner 图片')->disk('public')->height(180)->placeholder('—'),
            ]),
            Section::make('基础信息')->schema([
                TextEntry::make('id')->label('ID'),
                TextEntry::make('title')->label('标题'),
                TextEntry::make('is_active')->label('启用')->formatStateUsing(fn ($s): string => $s ? '启用' : '停用')->badge()->color(fn ($s): string => $s ? 'success' : 'gray'),
                TextEntry::make('sort_order')->label('排序')->numeric(),
                TextEntry::make('starts_at')->label('生效开始')->dateTime('Y-m-d H:i:s')->placeholder('—'),
                TextEntry::make('ends_at')->label('生效结束')->dateTime('Y-m-d H:i:s')->placeholder('—'),
                TextEntry::make('created_at')->label('创建时间')->dateTime('Y-m-d H:i:s'),
                TextEntry::make('updated_at')->label('更新时间')->dateTime('Y-m-d H:i:s'),
            ])->columns(2),
            Section::make('跳转配置')->schema([
                TextEntry::make('link_type')->label('跳转类型')->badge()
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        Banner::LINK_NONE => '不跳转',
                        Banner::LINK_ARTICLE => '跳转文章',
                        Banner::LINK_URL => '跳转链接',
                        default => (string) $state,
                    }),
                TextEntry::make('article.title')->label('关联文章')->placeholder('—'),
                TextEntry::make('url')->label('跳转链接')->placeholder('—')->url(fn ($s): ?string => $s)->openUrlInNewTab(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('图片')
                    ->disk('public')
                    ->height(48)
                    ->url(fn ($record) => Storage::disk('public')->url($record->image)),
                TextColumn::make('title')
                    ->label('标题')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('link')
                    ->label('跳转')
                    ->formatStateUsing(fn ($record) => match ($record->link_type) {
                        Banner::LINK_ARTICLE => '文章 #'.($record->article_id ?? '—'),
                        Banner::LINK_URL => '链接',
                        default => '不跳转',
                    })
                    ->badge()
                    ->color(fn ($record) => match ($record->link_type) {
                        Banner::LINK_ARTICLE => 'info',
                        Banner::LINK_URL => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('sort_order')
                    ->label('排序')
                    ->sortable(),
                TextColumn::make('window')
                    ->label('生效时间')
                    ->formatStateUsing(fn ($record) => $record->withinWindow() ? '生效中' : '未到/已过期')
                    ->badge()
                    ->color(fn ($record) => $record->withinWindow() ? 'success' : 'warning'),
                IconColumn::make('is_active')
                    ->label('启用')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('启用状态'),
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
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBanners::route('/'),
            'view' => Pages\ViewBanner::route('/{record}'),
            'create' => Pages\CreateBanner::route('/create'),
            'edit' => Pages\EditBanner::route('/{record}/edit'),
        ];
    }
}
