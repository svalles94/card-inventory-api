<?php

namespace App\Filament\Store\Resources;

use App\Filament\Store\Resources\StoreUserResource\Pages;
use App\Models\User;
use App\Models\StoreUserRole;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class StoreUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    
    protected static ?string $navigationLabel = 'Store Users';
    
    protected static ?string $navigationGroup = 'Settings';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        $currentStore = Auth::user()->currentStore();
        
        if (!$currentStore) {
            return $form->schema([]);
        }
        
        return $form
            ->schema([
                Forms\Components\Section::make('User Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->required(fn (string $context): bool => $context === 'create')
                            ->dehydrated(fn ($state) => filled($state))
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->maxLength(255),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Store Role')
                    ->schema([
                        Forms\Components\Select::make('role')
                            ->label('Role in Store')
                            ->options([
                                'owner' => 'Owner - Full control',
                                'admin' => 'Admin - Manage inventory, locations, integrations',
                                'base' => 'Base - View and adjust inventory only',
                            ])
                            ->required()
                            ->default('base')
                            ->helperText('Select the role for this user in your store'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $currentStore = Auth::user()->currentStore();
        
        if (!$currentStore) {
            return $table;
        }
        
        return $table
            ->modifyQueryUsing(function (Builder $query) use ($currentStore) {
                // Only show users who belong to the current store
                return $query->whereHas('storeRoles', function ($q) use ($currentStore) {
                    $q->where('store_id', $currentStore->id);
                });
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->size('lg')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('role')
                    ->label('Store Role')
                    ->badge()
                    ->getStateUsing(function (User $record) use ($currentStore) {
                        $role = $record->storeRoles()
                            ->where('store_id', $currentStore->id)
                            ->first();
                        return $role ? ucfirst($role->role) : 'N/A';
                    })
                    ->color(function (User $record) use ($currentStore) {
                        $role = $record->storeRoles()
                            ->where('store_id', $currentStore->id)
                            ->first();
                        return match($role?->role) {
                            'owner' => 'danger',
                            'admin' => 'warning',
                            'base' => 'info',
                            default => 'gray',
                        };
                    }),
                Tables\Columns\IconColumn::make('is_platform_admin')
                    ->label('Platform Admin')
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-shield-exclamation')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Store Role')
                    ->options([
                        'owner' => 'Owner',
                        'admin' => 'Admin',
                        'base' => 'Base',
                    ])
                    ->query(function (Builder $query, array $data) use ($currentStore) {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $value): Builder => $query->whereHas('storeRoles', function ($q) use ($currentStore, $value) {
                                $q->where('store_id', $currentStore->id)
                                  ->where('role', $value);
                            })
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('change_role')
                    ->label('Change Role')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->visible(function (User $record) use ($currentStore) {
                        // Only owners can change roles, and can't change their own role
                        $user = Auth::user();
                        return $user->hasRole($currentStore, 'owner') && $record->id !== $user->id;
                    })
                    ->form([
                        Forms\Components\Select::make('role')
                            ->label('New Role')
                            ->options([
                                'owner' => 'Owner - Full control',
                                'admin' => 'Admin - Manage inventory, locations, integrations',
                                'base' => 'Base - View and adjust inventory only',
                            ])
                            ->required()
                            ->default(function (User $record) use ($currentStore) {
                                $role = $record->storeRoles()
                                    ->where('store_id', $currentStore->id)
                                    ->first();
                                return $role?->role ?? 'base';
                            }),
                    ])
                    ->action(function (User $record, array $data) use ($currentStore) {
                        // Update or create role
                        StoreUserRole::updateOrCreate(
                            [
                                'user_id' => $record->id,
                                'store_id' => $currentStore->id,
                            ],
                            [
                                'role' => $data['role'],
                            ]
                        );
                    })
                    ->successNotificationTitle('Role updated!'),
                    
                Tables\Actions\Action::make('remove_from_store')
                    ->label('Remove from Store')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Remove User from Store')
                    ->modalDescription('Are you sure you want to remove this user from your store? They will lose access immediately.')
                    ->visible(function (User $record) use ($currentStore) {
                        // Only owners can remove users, and can't remove themselves
                        $user = Auth::user();
                        return $user->hasRole($currentStore, 'owner') && $record->id !== $user->id;
                    })
                    ->action(function (User $record) use ($currentStore) {
                        StoreUserRole::where('user_id', $record->id)
                            ->where('store_id', $currentStore->id)
                            ->delete();
                    })
                    ->successNotificationTitle('User removed from store'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkActionGroup::make([
                        // No bulk actions for now
                    ]),
                ]),
            ])
            ->emptyStateHeading('No users yet')
            ->emptyStateDescription('Add users to your store to help manage inventory.')
            ->emptyStateIcon('heroicon-o-users');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStoreUsers::route('/'),
            'create' => Pages\CreateStoreUser::route('/create'),
        ];
    }
}

