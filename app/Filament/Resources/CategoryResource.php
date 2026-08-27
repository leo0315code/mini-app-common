<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CategoryResource extends Resource
{

    protected static ?string $model = Category::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = '内容运营';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = '内容分类';

    protected static ?string $modelLabel = '分类';

    protected static ?string $pluralModelLabel = '分类列表';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('基础信息')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('名称')
                            ->required()
                            ->maxLength(60),
                        Forms\Components\TextInput::make('slug')
                            ->label('标识')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->helperText('URL 友好标识，字母数字与短横线'),
                        Forms\Components\Textarea::make('description')
                            ->label('说明')
                            ->rows(2)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('sort')
                            ->label('排序')
                            ->numeric()
                            ->default(0),
                        Forms\Components\Toggle::make('is_active')
                            ->label('启用')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('基础信息')->schema([
                TextEntry::make('id')->label('ID'),
                TextEntry::make('name')->label('名称'),
                TextEntry::make('slug')->label('标识'),
                TextEntry::make('description')->label('说明')->placeholder('—')->columnSpanFull(),
                TextEntry::make('sort')->label('排序')->numeric(),
                TextEntry::make('is_active')->label('启用')->formatStateUsing(fn ($s): string => $s ? '启用' : '停用')->badge()->color(fn ($s): string => $s ? 'success' : 'gray'),
                TextEntry::make('created_at')->label('创建时间')->dateTime('Y-m-d H:i:s'),
                TextEntry::make('updated_at')->label('更新时间')->dateTime('Y-m-d H:i:s'),
            ])->columns(2),
            Section::make('关联')->schema([
                TextEntry::make('articles_count')->label('文章数')->numeric()
                    ->state(fn (Category $record): int => $record->articles()->count())
                    ->placeholder('0'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable()->toggleable(),
                TextColumn::make('name')->label('名称')->searchable(),
                TextColumn::make('slug')->label('标识')->searchable(),
                TextColumn::make('sort')->label('排序')->sortable(),
                IconColumn::make('is_active')->label('启用')->boolean(),
                TextColumn::make('articles_count')
                    ->label('文章数')
                    ->counts('articles')
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('created_at')->label('创建时间')
                    ->dateTime('Y-m-d H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('启用'),
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
            ->defaultSort('sort');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),

            'view' => Pages\ViewCategory::route('/{record}'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
