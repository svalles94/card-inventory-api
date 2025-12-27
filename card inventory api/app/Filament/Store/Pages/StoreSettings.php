<?php

namespace App\Filament\Store\Pages;

use App\Models\Store;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class StoreSettings extends Page
{
    use InteractsWithFormActions;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    
    protected static string $view = 'filament.store.pages.store-settings';
    
    protected static ?string $title = 'Store Settings';
    
    protected static ?string $navigationLabel = 'Settings';
    
    protected static ?string $navigationGroup = 'Settings';
    
    protected static ?int $navigationSort = 10;

    public ?array $data = [];

    public function mount(): void
    {
        $currentStore = Auth::user()->currentStore();
        
        if (!$currentStore) {
            abort(403, 'No store selected');
        }

        // Only owners can access settings
        abort_unless(
            Auth::user()->hasRole($currentStore, 'owner'),
            403,
            'Only store owners can access store settings.'
        );

        $this->form->fill($currentStore->toArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Store Information')
                    ->description('Basic information about your store')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Store Name')
                            ->required()
                            ->maxLength(255)
                            ->unique(Store::class, 'name', ignoreRecord: true)
                            ->helperText('The name of your store'),
                        Forms\Components\TextInput::make('email')
                            ->label('Store Email')
                            ->email()
                            ->maxLength(255)
                            ->helperText('Contact email for your store'),
                        Forms\Components\TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->maxLength(255)
                            ->helperText('Contact phone number'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Address')
                    ->description('Physical address of your store')
                    ->schema([
                        Forms\Components\Textarea::make('address')
                            ->label('Street Address')
                            ->rows(3)
                            ->maxLength(500)
                            ->helperText('Full street address'),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $currentStore = Auth::user()->currentStore();
        
        if (!$currentStore) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('No store selected')
                ->send();
            return;
        }

        // Only owners can save settings
        if (!Auth::user()->hasRole($currentStore, 'owner')) {
            Notification::make()
                ->danger()
                ->title('Access Denied')
                ->body('Only store owners can update store settings')
                ->send();
            return;
        }

        $data = $this->form->getState();
        
        $currentStore->update($data);

        Notification::make()
            ->success()
            ->title('Settings Saved')
            ->body('Your store settings have been updated successfully.')
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->action('save')
                ->keyBindings(['mod+s'])
                ->color('primary'),
        ];
    }
}

