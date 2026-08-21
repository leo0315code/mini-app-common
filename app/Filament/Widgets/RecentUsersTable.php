<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Dashboard;
use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;

class RecentUsersTable extends BaseWidget
{
    public ?array $pageFilters = null;

    protected static ?string $heading = '最近注册用户';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $query = User::query()->whereNotNull('openid');

        $dates = Dashboard::rangeDates($this->pageFilters['range'] ?? null);
        if ($dates['start'] && $dates['end']) {
            $query->whereBetween('created_at', [$dates['start'], $dates['end']]);
        }

        return $table
            ->query($query->latest()->limit(10))
            ->columns([
                ImageColumn::make('avatar')
                    ->label('头像')
                    ->circular()
                    ->size(32)
                    ->defaultImageUrl('https://ui-avatars.com/api/?name=User&background=random'),
                TextColumn::make('nickname')
                    ->label('昵称')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('手机号'),
                TextColumn::make('gender')
                    ->label('性别')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        '0' => '未知',
                        '1' => '男',
                        '2' => '女',
                        default => $state,
                    }),
                TextColumn::make('created_at')
                    ->label('注册时间')
                    ->dateTime('Y-m-d H:i'),
            ])
            ->paginated(false);
    }
}
