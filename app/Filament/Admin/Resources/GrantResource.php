<?php

namespace App\Filament\Admin\Resources;

use App\Enums\GrantBenefit;
use App\Enums\GrantStatus;
use App\Filament\Admin\Resources\GrantResource\Pages\ListGrants;
use App\Filament\Admin\Resources\GrantResource\Pages\ViewGrant;
use App\Models\Event;
use App\Models\Grant;
use App\Services\GrantService;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;

/**
 * The grant review queue (R3.3b).
 *
 * There is no create or edit page, and that is the design. A grant is
 * *applied for* by a school through the portal and *decided* by the
 * coordinator through the three actions here. An edit form would let someone
 * set `status = approved` without choosing a benefit — which
 * `Event::priceFor()` reads as "no discount", so the school would be told it
 * had a grant and then charged in full. Routing every change through
 * `GrantService` makes that unrepresentable.
 */
class GrantResource extends Resource
{
    protected static ?string $model = Grant::class;

    protected static ?int $navigationSort = 40;

    public static function getNavigationLabel(): string
    {
        return __('Grants');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('College Fair');
    }

    /**
     * How many applications are waiting on a decision. Somebody is on the
     * other end of each of these, so the count belongs in the navigation.
     */
    public static function getNavigationBadge(): ?string
    {
        $pending = Grant::query()->where('status', GrantStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        // Deliberately empty: every change goes through an action. See the
        // class docblock.
        return $schema->components([]);
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

                TextColumn::make('benefit_type')
                    ->label(__('Benefit'))
                    ->state(fn (Grant $record): ?string => $record->benefitSummary())
                    ->placeholder('—'),

                IconColumn::make('used')
                    ->label(__('Used'))
                    ->boolean()
                    ->state(fn (Grant $record): bool => $record->isUsed())
                    // A used grant can no longer be revoked, so this is the
                    // column that explains why the action has disappeared.
                    ->tooltip(fn (Grant $record): ?string => $record->isUsed()
                        ? __('A live registration is priced under this grant, so it can no longer be revoked.')
                        : null),

                TextColumn::make('requester.name')
                    ->label(__('Applied by'))
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('Applied'))
                    ->date()
                    ->sortable(),
            ])
            // Pending first: this is a queue, not an archive.
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('event_id')
                    ->label(__('Fair'))
                    ->options(fn (): array => Event::query()->orderByDesc('starts_at')->pluck('name', 'id')->all()),

                SelectFilter::make('status')
                    ->options(collect(GrantStatus::cases())
                        ->mapWithKeys(fn (GrantStatus $case): array => [$case->value => $case->getLabel()])
                        ->all())
                    ->default(GrantStatus::Pending->value),
            ])
            ->recordActions([
                ViewAction::make(),
                static::approveAction(),
                static::denyAction(),
                static::revokeAction(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('organization.name')->label(__('School')),
            TextEntry::make('event.name')->label(__('Fair')),
            TextEntry::make('status')->badge(),
            TextEntry::make('benefit')
                ->label(__('Benefit'))
                ->state(fn (Grant $record): ?string => $record->benefitSummary())
                ->placeholder(__('Not decided')),
            TextEntry::make('justification')->columnSpanFull(),
            TextEntry::make('requester.name')->label(__('Applied by')),
            TextEntry::make('created_at')->label(__('Applied'))->dateTime(),
            TextEntry::make('decider.name')->label(__('Decided by'))->placeholder('—'),
            TextEntry::make('decided_at')->label(__('Decided'))->dateTime()->placeholder('—'),
            TextEntry::make('denial_reason')
                ->label(__('Reason given'))
                ->placeholder('—')
                ->columnSpanFull(),
        ]);
    }

    protected static function approveAction(): Action
    {
        return Action::make('approve')
            ->label(__('Approve'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Grant $record): bool => $record->status === GrantStatus::Pending
                && auth()->user()?->can('update', $record) === true)
            ->schema([
                Radio::make('benefit_type')
                    ->label(__('What is this grant worth?'))
                    ->options(collect(GrantBenefit::cases())
                        ->mapWithKeys(fn (GrantBenefit $case): array => [$case->value => $case->getLabel()])
                        ->all())
                    ->required()
                    ->live(),

                TextInput::make('custom_price_dollars')
                    ->label(__('Price this school pays'))
                    ->prefix('$')
                    ->numeric()
                    ->minValue(0)
                    ->step('0.01')
                    ->required()
                    ->visible(fn (Get $get): bool => $get('benefit_type') === GrantBenefit::CustomPrice->value),

                TextInput::make('percent_off')
                    ->label(__('Percentage off'))
                    ->suffix('%')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100)
                    ->required()
                    ->visible(fn (Get $get): bool => $get('benefit_type') === GrantBenefit::PercentOff->value),
            ])
            ->modalHeading(__('Approve this grant'))
            ->modalSubmitActionLabel(__('Approve'))
            ->action(function (Grant $record, array $data): void {
                $benefit = GrantBenefit::from($data['benefit_type']);

                static::run(fn () => app(GrantService::class)->approve(
                    grant: $record,
                    coordinator: auth()->user(),
                    benefit: $benefit,
                    customPriceCents: $benefit === GrantBenefit::CustomPrice
                        ? Money::toCents($data['custom_price_dollars'] ?? null)
                        : null,
                    percentOff: $benefit === GrantBenefit::PercentOff
                        ? (int) $data['percent_off']
                        : null,
                ), __('Approved.'));
            });
    }

    protected static function denyAction(): Action
    {
        return Action::make('deny')
            ->label(__('Deny'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Grant $record): bool => $record->status === GrantStatus::Pending
                && auth()->user()?->can('update', $record) === true)
            ->schema([
                Textarea::make('reason')
                    ->label(__('Reason'))
                    ->rows(3)
                    ->required()
                    // Required because "denied", with nothing else, is how you
                    // lose a school for good.
                    ->helperText(__('Included in the email the school receives.')),
            ])
            ->modalHeading(__('Deny this application'))
            ->modalSubmitActionLabel(__('Deny'))
            ->action(function (Grant $record, array $data): void {
                static::run(
                    fn () => app(GrantService::class)->deny($record, auth()->user(), $data['reason']),
                    __('Denied.'),
                );
            });
    }

    protected static function revokeAction(): Action
    {
        return Action::make('revoke')
            ->label(__('Revoke'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('danger')
            // Hidden once used, because the service refuses it anyway and an
            // action that always fails is worse than no action.
            ->visible(fn (Grant $record): bool => $record->status === GrantStatus::Approved
                && ! $record->isUsed()
                && auth()->user()?->can('update', $record) === true)
            ->schema([
                Textarea::make('reason')->label(__('Reason'))->rows(3),
            ])
            ->requiresConfirmation()
            ->modalHeading(__('Revoke this grant?'))
            ->modalDescription(__('The school pays list price from now on. Only possible because nothing has used it yet.'))
            ->action(function (Grant $record, array $data): void {
                static::run(
                    fn () => app(GrantService::class)->revoke($record, auth()->user(), $data['reason'] ?? null),
                    __('Revoked.'),
                );
            });
    }

    /**
     * Run a service call, turning its refusal into a notification.
     *
     * The service's exception messages are written as user-facing copy, so
     * showing them directly is right — and it means the UI cannot drift from
     * the rule, because there is no second copy of the wording.
     */
    protected static function run(callable $operation, string $success): void
    {
        try {
            $operation();

            Notification::make()->title($success)->success()->send();
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListGrants::route('/'),
            'view' => ViewGrant::route('/{record}'),
        ];
    }
}
