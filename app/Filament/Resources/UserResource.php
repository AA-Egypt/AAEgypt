<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\Group;
use App\Models\User;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('User Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Select::make('role')
                            ->options([
                                'web_admin' => 'Web Admin',
                                'gsr' => 'GSR',
                                'delegate' => 'Delegate',
                                'service_position' => 'Service Position',
                            ])
                            ->required()
                            ->default('gsr')
                            ->live(),
                        Select::make('group_id')
                            ->relationship('group', 'name_en')
                            ->getOptionLabelFromRecordUsing(fn (Group $record): string => $record->name_en.($record->name_ar ? " ({$record->name_ar})" : ''))
                            ->searchable(['name_en', 'name_ar'])
                            ->preload()
                            ->visible(fn ($get): bool => in_array($get('role'), ['gsr', 'delegate', 'service_position'], true)),
                    ])
                    ->columns(2),

                Section::make('Security & Status')
                    ->schema([
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->helperText(fn (string $operation): string => $operation === 'edit' ? 'Leave blank to keep the current password.' : ''),
                        Toggle::make('must_change_password')
                            ->label('Must change password on next login')
                            ->default(true),
                        Toggle::make('is_suspended')
                            ->label('Suspended')
                            ->helperText('Suspended users cannot access the system.')
                            ->default(false),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'web_admin' => 'danger',
                        'gsr' => 'info',
                        'delegate' => 'warning',
                        'service_position' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'web_admin' => 'Web Admin',
                        'gsr' => 'GSR',
                        'delegate' => 'Delegate',
                        'service_position' => 'Service Position',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('group.name_en')
                    ->label('Group')
                    ->placeholder('—')
                    ->sortable(),
                IconColumn::make('totp_secret')
                    ->label('2FA')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->state(fn (User $record): bool => ! empty($record->totp_secret)),
                IconColumn::make('is_suspended')
                    ->label('Suspended')
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('gray'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        'web_admin' => 'Web Admin',
                        'gsr' => 'GSR',
                        'delegate' => 'Delegate',
                        'service_position' => 'Service Position',
                    ]),
                TernaryFilter::make('is_suspended')
                    ->label('Suspended'),
                Filter::make('mfa_enabled')
                    ->label('2FA Enrolled')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('totp_secret')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_suspension')
                    ->label(fn (User $record): string => $record->is_suspended ? 'Unsuspend' : 'Suspend')
                    ->icon(fn (User $record): string => $record->is_suspended ? 'heroicon-o-check-circle' : 'heroicon-o-no-symbol')
                    ->color(fn (User $record): string => $record->is_suspended ? 'success' : 'danger')
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $record->update(['is_suspended' => ! $record->is_suspended]);

                        Notification::make()
                            ->title($record->is_suspended ? 'User suspended' : 'User unsuspended')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('reset_2fa')
                    ->label('Reset 2FA')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Reset Two-Factor Authentication')
                    ->modalDescription('Are you sure you want to clear 2FA for this user? They will need to set up 2FA again on their next login.')
                    ->visible(fn (User $record): bool => ! empty($record->totp_secret))
                    ->action(function (User $record): void {
                        $record->update(['totp_secret' => null]);

                        Notification::make()
                            ->title('2FA has been reset')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->role === 'web_admin';
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->role === 'web_admin';
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->role === 'web_admin';
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->role === 'web_admin';
    }
}
