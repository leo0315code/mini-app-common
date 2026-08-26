<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class EditPassword extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = '修改密码';

    protected static ?string $title = '修改密码';

    protected static ?int $navigationSort = 99;

    public ?string $current_password = '';

    public ?string $password = '';

    public ?string $password_confirmation = '';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('修改登录密码')
                    ->description('修改后下次登录需使用新密码')
                    ->schema([
                        TextInput::make('current_password')
                            ->label('当前密码')
                            ->password()
                            ->required(),
                        TextInput::make('password')
                            ->label('新密码')
                            ->password()
                            ->minLength(8)
                            ->required()
                            ->confirmed(),
                        TextInput::make('password_confirmation')
                            ->label('确认新密码')
                            ->password()
                            ->required(),
                    ])
                    ->columns(1),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $this->form($schema);
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('保存')
                ->action('submit'),
        ];
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            Notification::make()
                ->title('当前密码不正确')
                ->danger()
                ->send();

            return;
        }

        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        $this->form->fill([
            'current_password' => '',
            'password' => '',
            'password_confirmation' => '',
        ]);

        Notification::make()
            ->title('密码已更新')
            ->success()
            ->send();
    }
}
