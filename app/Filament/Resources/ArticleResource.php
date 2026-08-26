<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleResource\Pages;
use App\Models\Article;
use App\Models\Category;
use App\Support\ManagesRichEditorAttachments;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ArticleResource extends Resource
{

    use ManagesRichEditorAttachments;

    protected static ?string $model = Article::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = '内容运营';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = '文章管理';

    protected static ?string $modelLabel = '文章';

    protected static ?string $pluralModelLabel = '文章列表';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('基础信息')
                    ->schema([
                        Forms\Components\Select::make('category_id')
                            ->label('分类')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')->required(),
                                Forms\Components\TextInput::make('slug')->required(),
                            ]),
                        Forms\Components\TextInput::make('title')
                            ->label('标题')
                            ->required()
                            ->maxLength(160),
                        Forms\Components\TextInput::make('slug')
                            ->label('标识')
                            ->unique(ignoreRecord: true)
                            ->helperText('可空，留空由系统忽略'),
                        Forms\Components\Select::make('status')
                            ->label('状态')
                            ->options([
                                Article::STATUS_DRAFT => '草稿',
                                Article::STATUS_PUBLISHED => '已发布',
                                Article::STATUS_OFFLINE => '已下线',
                            ])
                            ->default(Article::STATUS_DRAFT)
                            ->required(),
                        Forms\Components\Toggle::make('is_top')
                            ->label('置顶')
                            ->default(false),
                        Forms\Components\DateTimePicker::make('published_at')
                            ->label('发布时间')
                            ->helperText('留空表示保存后立即按状态发布'),
                    ])->columns(2),
                Section::make('封面与摘要')
                    ->schema([
                        Forms\Components\FileUpload::make('cover')
                            ->label('封面图')
                            ->image()
                            ->disk('public')
                            ->directory('articles')
                            ->visibility('public')
                            ->imageEditor(),
                        Forms\Components\Textarea::make('summary')
                            ->label('摘要')
                            ->rows(2)
                            ->maxLength(255),
                    ]),
                Section::make('正文')
                    ->schema([
                        Forms\Components\RichEditor::make('content')
                            ->label('正文')
                            ->required()
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('rich-editor/'.now()->format('Ym'))
                            ->saveUploadedFileAttachmentUsing(self::richEditorSaveAttachmentCallback('public'))
                            ->toolbarButtons([
                                'bold', 'italic', 'bulletList', 'orderedList',
                                'link', 'blockquote', 'attachFiles',
                                'undo', 'redo',
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover')
                    ->label('封面')
                    ->disk('public')
                    ->size(44)
                    ->defaultImageUrl('https://ui-avatars.com/api/?name=N&background=random'),
                TextColumn::make('id')->label('ID')->sortable()->toggleable(),
                TextColumn::make('title')->label('标题')->searchable()->limit(40),
                TextColumn::make('category.name')->label('分类')
                    ->formatStateUsing(fn ($state, $record) => $record->category?->name ?? '—'),
                IconColumn::make('is_top')->label('置顶')->boolean(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Article::STATUS_DRAFT => '草稿',
                        Article::STATUS_PUBLISHED => '已发布',
                        Article::STATUS_OFFLINE => '已下线',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'offline' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('views')->label('浏览数')->sortable(),
                TextColumn::make('author.name')
                    ->label('作者')
                    ->formatStateUsing(fn ($state, $record) => $record->author?->name ?? '—'),
                TextColumn::make('published_at')->label('发布时间')
                    ->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('created_at')->label('创建时间')
                    ->dateTime('Y-m-d H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('分类')
                    ->options(fn () => Category::pluck('name', 'id')->all()),
                SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        Article::STATUS_DRAFT => '草稿',
                        Article::STATUS_PUBLISHED => '已发布',
                        Article::STATUS_OFFLINE => '已下线',
                    ]),
                TernaryFilter::make('is_top')->label('置顶'),
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
            'index' => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit' => Pages\EditArticle::route('/{record}/edit'),
        ];
    }

    /**
     * 创建时记录作者；发布且无发布时间则补当前时间。
     */
    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        if (($data['status'] ?? null) === Article::STATUS_PUBLISHED && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }
}
