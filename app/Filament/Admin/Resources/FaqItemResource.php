<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\FaqItemResource\Pages\CreateFaqItem;
use App\Filament\Admin\Resources\FaqItemResource\Pages\EditFaqItem;
use App\Filament\Admin\Resources\FaqItemResource\Pages\ListFaqItems;
use App\Models\FaqItem;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * The public FAQ (R3.5).
 *
 * A table rather than a content block because the coordinator reorders and
 * unpublishes individual questions, which one block of copy cannot express.
 * Several rows seed with a `TODO-OWNER` marker where doc 00 recorded that a
 * section exists but not what it says — those are meant to be found and
 * finished here.
 */
class FaqItemResource extends Resource
{
    protected static ?string $model = FaqItem::class;

    protected static ?string $recordTitleAttribute = 'question';

    protected static ?int $navigationSort = 70;

    public static function getNavigationLabel(): string
    {
        return __('FAQ');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Site content');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('question')->required()->maxLength(255)->columnSpanFull(),
            MarkdownEditor::make('answer')->required()->columnSpanFull(),
            Toggle::make('is_published')
                ->label(__('Published'))
                ->default(true)
                ->helperText(__('Unpublished questions stay here but disappear from the public page.')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question')->searchable()->wrap(),
                IconColumn::make('is_published')->label(__('Published'))->boolean(),
                TextColumn::make('needs_owner')
                    ->label(__('Needs copy'))
                    ->badge()
                    ->color('warning')
                    // Surfaces the seeded placeholders so they are found before
                    // launch rather than by a representative looking for parking.
                    ->state(fn (FaqItem $record): ?string => str_contains($record->answer, 'TODO-OWNER')
                        ? __('TODO-OWNER')
                        : null)
                    ->placeholder(''),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->filters([
                TernaryFilter::make('is_published')->label(__('Published')),
            ])
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
            'index' => ListFaqItems::route('/'),
            'create' => CreateFaqItem::route('/create'),
            'edit' => EditFaqItem::route('/{record}/edit'),
        ];
    }
}
