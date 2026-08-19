<?php

namespace App\Filament\Admin\Resources;

use App\Enums\PaymentMethod;
use App\Enums\RegistrationStatus;
use App\Filament\Admin\Resources\RegistrationResource\Pages\CreateRegistration;
use App\Filament\Admin\Resources\RegistrationResource\Pages\EditRegistration;
use App\Filament\Admin\Resources\RegistrationResource\Pages\ListRegistrations;
use App\Filament\Admin\Resources\RegistrationResource\Pages\ViewRegistration;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Registration;
use App\Services\RegistrationService;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Registrations (R3.3).
 *
 * The edit form is deliberately narrow: roster visibility and notes, nothing
 * else. Status, price, school and fair are not editable fields, because every
 * one of them is the outcome of a decision that has to go through
 * `RegistrationService` — editing `status` by hand would skip the events that
 * send receipts, and editing `price_cents` would break the snapshot that
 * proves what a school agreed to pay (N1).
 *
 * Cancelling is an action, not a delete. Registrations are never removed once
 * payment exists (doc 03, data lifecycle).
 */
class RegistrationResource extends Resource
{
    protected static ?string $model = Registration::class;

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('Registrations');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('College Fair');
    }

    public static function getRecordTitle(?Model $record): ?string
    {
        return $record instanceof Registration
            ? $record->organization?->name.' — '.$record->event?->name
            : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // Manual entry (R3.4): a phone call, a form that arrived in the
            // post. Create-only, because school and fair are immutable once the
            // registration exists — moving one to another fair would carry a
            // price snapshot that was never agreed for it.
            Section::make(__('Manual entry'))
                ->description(__('Goes through the same rules as the portal, minus the membership and window checks. Duplicates are still refused and the price is still read from the fair and any approved grant.'))
                ->visibleOn('create')
                ->schema([
                    Select::make('event_id')
                        ->label(__('Fair'))
                        ->required()
                        ->searchable()
                        ->options(fn (): array => Event::query()
                            ->orderByDesc('starts_at')
                            ->pluck('name', 'id')
                            ->all()),

                    Select::make('organization_id')
                        ->label(__('School'))
                        ->required()
                        ->searchable()
                        ->options(fn (): array => Organization::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all()),

                    Select::make('payment_method')
                        ->label(__('Payment method'))
                        ->options(collect(PaymentMethod::cases())
                            ->mapWithKeys(fn (PaymentMethod $case): array => [$case->value => $case->getLabel()])
                            ->all())
                        ->helperText(__('Leave blank only if an approved grant makes this registration free.')),
                ])
                ->columns(3),

            Section::make(__('Coordinator controls'))
                ->hiddenOn('create')
                ->description(__('Status, price, school and fair are not editable here. Each is the outcome of a decision that has to go through the registration service, so that receipts are sent and the price snapshot stays honest.'))
                ->schema([
                    Toggle::make('show_on_roster')
                        ->label(__('Show on the public roster'))
                        ->helperText(__('Confirmed registrations appear on the Representatives page unless this is off.')),

                    Textarea::make('notes')
                        ->label(__('Internal notes'))
                        ->rows(4)
                        ->columnSpanFull(),
                ]),

            Section::make(__('Fair contact'))
                ->description(__('Who is staffing the table. Not necessarily the account holder.'))
                ->schema([
                    TextInput::make('rep_name')->required()->maxLength(255),
                    TextInput::make('rep_email')->email()->required()->maxLength(255),
                    TextInput::make('rep_phone')->tel()->maxLength(20),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('organization.name')
                    ->label(__('School'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('event.name')
                    ->label(__('Fair'))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('status')->badge()->sortable(),

                TextColumn::make('payment_method')
                    ->label(__('Method'))
                    ->badge()
                    // Null is meaningful, not missing: a free registration has
                    // no method because nothing was ever charged.
                    ->placeholder(__('Free')),

                TextColumn::make('price_cents')
                    ->label(__('Price'))
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->description(fn (Registration $record): ?string => $record->grant?->benefitSummary())
                    ->sortable(),

                TextColumn::make('rep_name')
                    ->label(__('Contact'))
                    ->description(fn (Registration $record): string => $record->rep_email)
                    ->searchable(['rep_name', 'rep_email'])
                    ->toggleable(),

                IconColumn::make('show_on_roster')
                    ->label(__('On roster'))
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('Registered'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('event_id')
                    ->label(__('Fair'))
                    ->options(fn (): array => Event::query()->orderByDesc('starts_at')->pluck('name', 'id')->all()),

                SelectFilter::make('status')
                    ->options(collect(RegistrationStatus::cases())
                        ->mapWithKeys(fn (RegistrationStatus $case): array => [$case->value => $case->getLabel()])
                        ->all()),

                SelectFilter::make('payment_method')
                    ->label(__('Method'))
                    ->options(collect(PaymentMethod::cases())
                        ->mapWithKeys(fn (PaymentMethod $case): array => [$case->value => $case->getLabel()])
                        ->all()),

                TernaryFilter::make('show_on_roster')->label(__('On the roster')),

                TernaryFilter::make('grant_id')
                    ->label(__('Has a grant'))
                    ->nullable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                static::cancelAction(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('organization.name')->label(__('School')),
            TextEntry::make('event.name')->label(__('Fair')),
            TextEntry::make('status')->badge(),
            TextEntry::make('payment_method')->label(__('Method'))->badge()->placeholder(__('Free')),
            TextEntry::make('price_cents')
                ->label(__('Price charged'))
                ->formatStateUsing(fn (int $state): string => Money::format($state))
                // The snapshot, spelled out: this is what the school agreed to,
                // not what the fair costs today.
                ->helperText(__('Snapshotted when the registration was created.')),
            TextEntry::make('grant.benefit_type')
                ->label(__('Grant applied'))
                ->state(fn (Registration $record): ?string => $record->grant?->benefitSummary())
                ->placeholder(__('None')),
            TextEntry::make('rep_name')->label(__('Contact')),
            TextEntry::make('rep_email')->label(__('Contact email'))->copyable(),
            TextEntry::make('rep_phone')->label(__('Contact phone'))->placeholder('—'),
            TextEntry::make('user.name')
                ->label(__('Registered by'))
                ->placeholder(__('Entered by the coordinator')),
            TextEntry::make('created_at')->label(__('Registered'))->dateTime(),
            TextEntry::make('confirmed_at')->label(__('Confirmed'))->dateTime()->placeholder('—'),
            TextEntry::make('cancelled_at')->label(__('Cancelled'))->dateTime()->placeholder('—'),
            TextEntry::make('notes')->label(__('Internal notes'))->placeholder('—')->columnSpanFull(),
        ]);
    }

    protected static function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label(__('Cancel'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Registration $record): bool => auth()->user()?->can('cancel', $record) === true)
            ->schema([
                Textarea::make('reason')
                    ->label(__('Reason (added to the internal notes)'))
                    ->rows(3),
            ])
            ->requiresConfirmation()
            ->modalHeading(__('Cancel this registration?'))
            ->modalDescription(__('The record is kept. The seat and any grant are released, and the school drops off the public roster.'))
            ->action(function (Registration $record, array $data): void {
                try {
                    app(RegistrationService::class)->cancel($record, $data['reason'] ?? null);

                    Notification::make()->title(__('Cancelled.'))->success()->send();
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListRegistrations::route('/'),
            'create' => CreateRegistration::route('/create'),
            'view' => ViewRegistration::route('/{record}'),
            'edit' => EditRegistration::route('/{record}/edit'),
        ];
    }
}
