<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SponsorResource\Pages\CreateSponsor;
use App\Filament\Admin\Resources\SponsorResource\Pages\EditSponsor;
use App\Filament\Admin\Resources\SponsorResource\Pages\ListSponsors;
use App\Filament\Admin\Resources\SponsorResource\RelationManagers\StaffRelationManager;
use App\Models\Sponsor;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The four sponsoring schools and their college counseling staff (R3.5).
 *
 * Ordering is by hand, not alphabetical: sponsors pay for billing position.
 * The table is reorderable, so the coordinator drags rather than typing
 * numbers.
 */
class SponsorResource extends Resource
{
    protected static ?string $model = Sponsor::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 60;

    public static function getNavigationGroup(): ?string
    {
        return __('Site content');
    }

    /**
     * @return array<int, class-string>
     */
    public static function getRelations(): array
    {
        return [
            StaffRelationManager::class,
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('website')->url()->maxLength(255),
            FileUpload::make('logo_path')
                ->label(__('Logo'))
                ->image()
                ->disk('public')
                ->directory('sponsor-logos')
                ->maxSize(2048),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo_path')->label(__('Logo'))->disk('public')->toggleable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('website')->placeholder('—')->toggleable(),
                TextColumn::make('staff_count')->label(__('Staff listed'))->counts('staff'),
            ])
            // Drag to reorder — the coordinator should never have to work out
            // what integer puts a school second.
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListSponsors::route('/'),
            'create' => CreateSponsor::route('/create'),
            'edit' => EditSponsor::route('/{record}/edit'),
        ];
    }
}
