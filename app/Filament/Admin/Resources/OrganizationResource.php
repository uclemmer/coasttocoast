<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OrganizationResource\Pages\CreateOrganization;
use App\Filament\Admin\Resources\OrganizationResource\Pages\EditOrganization;
use App\Filament\Admin\Resources\OrganizationResource\Pages\ListOrganizations;
use App\Filament\Admin\Resources\OrganizationResource\Pages\ViewOrganization;
use App\Filament\Admin\Resources\OrganizationResource\RelationManagers\RepresentativesRelationManager;
use App\Models\Organization;
use App\Services\OrganizationService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The school directory (R3.3a).
 *
 * The merge action is the interesting one. Duplicate schools are inevitable —
 * two reps sign up a year apart and one of them types "The" — and the fix has
 * to preserve both schools' registration history, which a delete never can
 * because the foreign keys cascade. `OrganizationService::merge()` repoints
 * everything first and reports back any fair where the merge has left two live
 * registrations, because choosing which one a school keeps is a decision about
 * money rather than a data-cleanup step.
 */
class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('Schools');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('College Fair');
    }

    /**
     * @return array<int, class-string>
     */
    public static function getRelations(): array
    {
        return [
            RepresentativesRelationManager::class,
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('School'))
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull()
                        // Surfaced, not blocked (R2.7). "Boston University" and
                        // "Boston College" normalize differently on purpose, so
                        // a match here is worth a second look rather than a veto.
                        ->helperText(fn (?Organization $record): ?string => static::duplicateWarning($record)),

                    TextInput::make('website')->url()->maxLength(255),

                    FileUpload::make('logo_path')
                        ->label(__('Logo'))
                        ->image()
                        ->disk('public')
                        ->directory('organization-logos')
                        ->maxSize(2048)
                        ->helperText(__('Shown on the public roster. Rosters fall back to an initial when there is none.')),
                ])
                ->columns(2),

            Section::make(__('Admissions contact'))
                ->description(__('Used when the school has no active representative — the campaign fallback.'))
                ->schema([
                    TextInput::make('admissions_office')->maxLength(255),
                    TextInput::make('admissions_email')->email()->maxLength(255),
                    TextInput::make('admissions_phone')->tel()->maxLength(20),
                ])
                ->columns(3),

            Section::make(__('Address'))
                ->schema([
                    TextInput::make('address_line1')->label(__('Address'))->maxLength(255),
                    TextInput::make('address_line2')->label(__('Address line 2'))->maxLength(255),
                    TextInput::make('city')->maxLength(255),
                    TextInput::make('state')->maxLength(255),
                    TextInput::make('postal_code')->label(__('ZIP'))->maxLength(20),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo_path')
                    ->label(__('Logo'))
                    ->disk('public')
                    ->circular()
                    ->toggleable(),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Organization $record): ?string => $record->website),

                TextColumn::make('active_reps_count')
                    ->label(__('Active reps'))
                    ->counts('activeReps')
                    // Zero is the interesting number: it means campaigns fall
                    // back to admissions_email, or drop the school entirely.
                    ->color(fn (int $state): string => $state === 0 ? 'warning' : 'gray'),

                TextColumn::make('registrations_count')
                    ->label(__('Registrations'))
                    ->counts('registrations'),

                TextColumn::make('admissions_email')
                    ->label(__('Admissions email'))
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('name')
            ->filters([
                Filter::make('needs_a_rep')
                    ->label(__('No active representative'))
                    ->query(fn (Builder $query): Builder => $query->whereDoesntHave('activeReps')),

                Filter::make('possible_duplicates')
                    ->label(__('Possible duplicates'))
                    ->query(fn (Builder $query): Builder => $query->whereIn(
                        'normalized_name',
                        Organization::query()
                            ->select('normalized_name')
                            ->groupBy('normalized_name')
                            ->havingRaw('count(*) > 1'),
                    )),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                static::mergeAction(),
                DeleteAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name'),
            TextEntry::make('website')->placeholder('—'),
            TextEntry::make('admissions_office')->placeholder('—'),
            TextEntry::make('admissions_email')->placeholder('—'),
            TextEntry::make('admissions_phone')->placeholder('—'),
            TextEntry::make('formatted_address')
                ->label(__('Address'))
                ->state(fn (Organization $record): ?string => $record->formattedAddress())
                ->placeholder('—')
                ->columnSpanFull(),
            TextEntry::make('normalized_name')
                ->label(__('Matched as'))
                ->helperText(__('The form used for duplicate detection.')),
        ]);
    }

    /**
     * Fold this school into another one.
     *
     * Destructive in the sense that a row disappears, so it confirms; but
     * nothing it touches is lost, which is the reason it exists rather than a
     * delete.
     */
    protected static function mergeAction(): Action
    {
        return Action::make('merge')
            ->label(__('Merge into…'))
            ->icon('heroicon-o-arrows-pointing-in')
            ->color('warning')
            ->visible(fn (Organization $record): bool => auth()->user()?->can('merge', $record) === true)
            ->schema([
                Select::make('keep_id')
                    ->label(__('Keep this school'))
                    ->required()
                    ->searchable()
                    ->options(fn (Organization $record): array => Organization::query()
                        ->whereKeyNot($record->getKey())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->helperText(__('Representatives, registrations and grants move to the school you keep. This one is then deleted.')),
            ])
            ->requiresConfirmation()
            ->modalHeading(__('Merge this school into another?'))
            ->modalDescription(__('Nothing is lost: everything is repointed first. The empty record is removed afterwards.'))
            ->action(function (Organization $record, array $data): void {
                $keep = Organization::query()->findOrFail($data['keep_id']);

                $collisions = app(OrganizationService::class)->merge($record, $keep);

                if ($collisions === []) {
                    Notification::make()->title(__('Merged.'))->success()->send();

                    return;
                }

                // Deliberately not resolved automatically — which of two paid
                // registrations a school keeps is a decision about money.
                Notification::make()
                    ->title(__('Merged, but :count registrations now clash.', ['count' => count($collisions)]))
                    ->body(__('The same school now holds two live registrations for the same fair. Cancel whichever is wrong.'))
                    ->warning()
                    ->persistent()
                    ->send();
            });
    }

    protected static function duplicateWarning(?Organization $record): ?string
    {
        if (! $record instanceof Organization) {
            return null;
        }

        $matches = $record->possibleDuplicates()->pluck('name');

        return $matches->isEmpty()
            ? null
            : __('Looks like a duplicate of: :names', ['names' => $matches->join(', ')]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListOrganizations::route('/'),
            'create' => CreateOrganization::route('/create'),
            'view' => ViewOrganization::route('/{record}'),
            'edit' => EditOrganization::route('/{record}/edit'),
        ];
    }
}
