<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\EventResource\Pages\CreateEvent;
use App\Filament\Admin\Resources\EventResource\Pages\EditEvent;
use App\Filament\Admin\Resources\EventResource\Pages\ListEvents;
use App\Filament\Admin\Resources\EventResource\Pages\ViewEvent;
use App\Models\Event;
use App\Support\Money;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * The fair calendar (R3.2).
 *
 * Two things here are load-bearing rather than cosmetic:
 *
 *  - **Money is entered in dollars and stored in cents.** The form state is
 *    dollars because that is what a coordinator types; the column is an integer
 *    because floating-point money is how you lose a cent per registration.
 *    `Money` owns both directions so no other resource has to reinvent them.
 *  - **The publish toggle is what lets an event take money.** An unpublished
 *    event is never registration-open, whatever its window says. That is why
 *    the placeholder 2027 fair seeds unpublished, and the form says so.
 */
class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return __('Fairs');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('College Fair');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('The fair'))
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        // Only suggest a slug while creating. Changing a slug
                        // after the fact breaks every link anyone has shared.
                        ->afterStateUpdated(function (string $operation, ?string $state, callable $set): void {
                            if ($operation === 'create' && filled($state)) {
                                $set('slug', Str::slug($state));
                            }
                        }),

                    TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->helperText(__('Used in the public URL: /events/{slug}. Changing it breaks existing links.')),

                    TextInput::make('venue_name')
                        ->required()
                        ->maxLength(255),

                    Textarea::make('venue_address')
                        ->required()
                        ->rows(3),
                ])
                ->columns(2),

            Section::make(__('When'))
                ->schema([
                    DateTimePicker::make('starts_at')
                        ->label(__('Fair opens'))
                        ->seconds(false)
                        ->required(),

                    DateTimePicker::make('ends_at')
                        ->label(__('Fair closes'))
                        ->seconds(false)
                        ->required()
                        ->after('starts_at'),

                    DateTimePicker::make('reception_starts_at')
                        ->label(__('Counselor reception starts'))
                        ->seconds(false)
                        ->helperText(__('Optional. Leave blank if there is no reception this year.')),
                ])
                ->columns(2),

            Section::make(__('Registration'))
                ->schema([
                    TextInput::make('price_cents')
                        ->label(__('Registration fee'))
                        ->prefix('$')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->step('0.01')
                        ->helperText(__('The list price. Approved grants reduce what an individual school pays.'))
                        // Dollars in the field, cents in the column. Done here
                        // rather than in the page classes so the conversion
                        // cannot go missing from one of create/edit: a field
                        // marked `dehydrated(false)` never reaches
                        // `mutateFormDataBeforeCreate()` at all, which silently
                        // saved every fair at zero until this was fixed.
                        ->formatStateUsing(fn (?int $state): ?string => $state === null
                            ? null
                            : number_format($state / 100, 2, '.', ''))
                        ->dehydrateStateUsing(fn (float|int|string|null $state): int => Money::toCents($state)),

                    TextInput::make('capacity')
                        ->numeric()
                        ->minValue(1)
                        ->helperText(__('Optional. Counts confirmed AND awaiting-payment registrations, so mailed checks cannot oversell the room.')),

                    DateTimePicker::make('registration_opens_at')
                        ->seconds(false)
                        ->helperText(__('Blank means open as soon as the fair is published.')),

                    DateTimePicker::make('registration_closes_at')
                        ->seconds(false)
                        ->after('registration_opens_at')
                        ->helperText(__('Blank means it never closes on its own.')),

                    Toggle::make('is_published')
                        ->label(__('Published'))
                        ->helperText(__('An unpublished fair is invisible to the public and cannot accept registrations or money, whatever the window above says.'))
                        ->columnSpanFull(),
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
                    ->sortable()
                    ->description(fn (Event $record): string => $record->venue_name),

                TextColumn::make('starts_at')
                    ->label(__('Date'))
                    ->date()
                    ->sortable(),

                TextColumn::make('price_cents')
                    ->label(__('Fee'))
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->sortable(),

                TextColumn::make('registrations_count')
                    ->label(__('Registered'))
                    ->counts('registrations')
                    ->description(fn (Event $record): string => $record->capacity === null
                        ? __('no cap')
                        : __(':left of :capacity left', [
                            'left' => $record->remainingCapacity(),
                            'capacity' => $record->capacity,
                        ])),

                IconColumn::make('is_published')
                    ->label(__('Published'))
                    ->boolean()
                    ->sortable(),

                TextColumn::make('registration_status')
                    ->label(__('Registration'))
                    ->badge()
                    ->state(fn (Event $record): string => static::registrationState($record))
                    ->color(fn (string $state): string => match ($state) {
                        'Open' => 'success',
                        'Not yet open' => 'info',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('starts_at', 'desc')
            ->filters([
                TernaryFilter::make('is_published')->label(__('Published')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name'),
            TextEntry::make('slug'),
            TextEntry::make('starts_at')->dateTime(),
            TextEntry::make('ends_at')->dateTime(),
            TextEntry::make('reception_starts_at')->dateTime()->placeholder('—'),
            TextEntry::make('venue_name'),
            TextEntry::make('venue_address')->columnSpanFull(),
            TextEntry::make('price_cents')
                ->label(__('Registration fee'))
                ->formatStateUsing(fn (int $state): string => Money::format($state)),
            TextEntry::make('capacity')->placeholder(__('No cap')),
            TextEntry::make('registration_opens_at')->dateTime()->placeholder(__('No opening date')),
            TextEntry::make('registration_closes_at')->dateTime()->placeholder(__('No closing date')),
            TextEntry::make('is_published')
                ->label(__('Published'))
                ->badge()
                ->formatStateUsing(fn (bool $state): string => $state ? __('Published') : __('Draft'))
                ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
        ]);
    }

    /**
     * The three states the public event page branches on: open, not yet open,
     * closed. Kept as one expression so the admin table and the public CTA
     * cannot disagree about which one a fair is in.
     */
    protected static function registrationState(Event $event): string
    {
        return match (true) {
            $event->isRegistrationOpen() => 'Open',
            $event->registrationNotYetOpen() => 'Not yet open',
            default => 'Closed',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListEvents::route('/'),
            'create' => CreateEvent::route('/create'),
            'view' => ViewEvent::route('/{record}'),
            'edit' => EditEvent::route('/{record}/edit'),
        ];
    }
}
