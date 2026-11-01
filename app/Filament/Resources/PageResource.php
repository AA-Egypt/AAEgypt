<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages;
use App\Models\Page;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Content')
                    ->tabs([
                        Tab::make('Arabic (العربية)')
                            ->schema([
                                TextInput::make('title_ar')
                                    ->label('العنوان')
                                    ->required()
                                    ->maxLength(255)
                                    ->extraInputAttributes(['dir' => 'rtl'])
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (string $operation, ?string $state, Set $set, Get $get) {
                                        if ($operation === 'create' && empty($get('slug')) && empty($get('title_en')) && filled($state)) {
                                            $set('slug', Str::slug($state));
                                        }
                                    }),
                                RichEditor::make('body_ar')
                                    ->label('المحتوى')
                                    ->required()
                                    ->extraAttributes(['dir' => 'rtl'])
                                    ->extraInputAttributes(['dir' => 'rtl']),
                                Textarea::make('meta_description_ar')
                                    ->label('الوصف التعريفي للبحث (SEO)')
                                    ->rows(3)
                                    ->maxLength(500)
                                    ->extraInputAttributes(['dir' => 'rtl']),
                            ]),
                        Tab::make('English')
                            ->schema([
                                TextInput::make('title_en')
                                    ->label('Title')
                                    ->required()
                                    ->maxLength(255)
                                    ->extraInputAttributes(['dir' => 'ltr'])
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (string $operation, ?string $state, Set $set, Get $get) {
                                        if ($operation === 'create' && filled($state)) {
                                            $set('slug', Str::slug($state));
                                        }
                                    }),
                                RichEditor::make('body_en')
                                    ->label('Body')
                                    ->required()
                                    ->extraAttributes(['dir' => 'ltr'])
                                    ->extraInputAttributes(['dir' => 'ltr']),
                                Textarea::make('meta_description_en')
                                    ->label('Meta Description (SEO)')
                                    ->rows(3)
                                    ->maxLength(500)
                                    ->extraInputAttributes(['dir' => 'ltr']),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Page Settings')
                    ->schema([
                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->unique(Page::class, 'slug', ignoreRecord: true)
                            ->maxLength(255),
                        Toggle::make('is_published')
                            ->label('Published')
                            ->default(false),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title_en')
                    ->label('Title (EN)')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title_ar')
                    ->label('Title (AR)')
                    ->searchable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('Publication Status'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'web_admin';
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

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
