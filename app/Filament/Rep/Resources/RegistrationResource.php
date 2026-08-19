<?php

namespace App\Filament\Rep\Resources;

use App\Filament\Rep\Resources\RegistrationResource\Pages\CreateRegistration;
use App\Filament\Rep\Resources\RegistrationResource\Pages\ListRegistrations;
use App\Filament\Rep\Resources\RegistrationResource\Pages\ViewRegistration;
use App\Models\Registration;
use App\Models\User;
use App\Support\Money;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * What a school has registered for, in the portal (cards 3.1 and 3.3).
 *
 * Scoped to the rep's **organization**, not to the rep. A new admissions
 * officer inheriting the account should see their school's history rather than
 * an empty page and the impression that nothing was ever done — the school is
 * the unit that registers (D8), and this is where that decision becomes
 * visible.
 *
 * Read-only apart from creating one. Everything that changes a registration
 * after the fact — cancelling, refunding, marking a check received — is the
 * coordinator's, because it is a conversation about money.
 */
class RegistrationResource extends Resource
{
    protected static ?string $model = Registration::class;

    protected static ?int $navigationSort = 10;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    public static function getNavigationLabel(): string
    {
        return __('Your registrations');
    }

    public static function getModelLabel(): string
    {
        return __('registration');
    }

    /**
     * The scope that makes this safe.
     *
     * A rep with no school sees nothing at all rather than everything — the
     * `whereRaw('1 = 0')` is deliberate, because `where('organization_id', null)`
     * would be a very different query.
     */
    public static function getEloquentQuery(): Builder
    {
        $organizationId = auth()->user()?->organization_id;

        return parent::getEloquentQuery()
            ->with(['event', 'grant'])
            ->when(
                $organizationId !== null,
                fn (Builder $query): Builder => $query->where('organization_id', $organizationId),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            );
    }

    /**
     * Portal authorization, answered here rather than by `RegistrationPolicy`.
     *
     * The policy is right to refuse a rep: it answers "may this coordinator
     * administer every registration", and the answer is no. The portal is
     * asking something different — "is this my school's row" — and those are
     * two predicates, not one wearing different hats (see the policy's
     * docblock). Both are enforced; neither is loosened.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->organization_id !== null;
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof Registration
            && $record->organization_id === auth()->user()?->organization_id;
    }

    public static function canCreate(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->actsForOrganization() === true;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        // The create page builds the wizard; there is no edit form.
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event.name')->label(__('Fair'))->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('payment_method')
                    ->label(__('Payment'))
                    ->badge()
                    ->placeholder(__('Covered by a grant')),
                TextColumn::make('price_cents')
                    ->label(__('Amount'))
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->description(fn (Registration $record): ?string => $record->grant?->benefitSummary()),
                TextColumn::make('rep_name')->label(__('Contact'))->toggleable(),
                TextColumn::make('created_at')->label(__('Registered'))->date()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('No registrations yet'))
            ->emptyStateDescription(__('Once your school registers for a fair it will appear here, with its receipt.'))
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('The fair'))
                ->schema([
                    TextEntry::make('event.name')->label(__('Fair')),
                    TextEntry::make('event.starts_at')->label(__('Date'))->dateTime(),
                    TextEntry::make('event.venue_name')->label(__('Venue')),
                    TextEntry::make('event.venue_address')->label(__('Address'))->columnSpanFull(),
                ])
                ->columns(2),

            Section::make(__('Your registration'))
                ->schema([
                    TextEntry::make('status')->badge(),
                    TextEntry::make('payment_method')
                        ->label(__('Payment'))
                        ->badge()
                        ->placeholder(__('Covered by a grant')),
                    TextEntry::make('price_cents')
                        ->label(__('Amount'))
                        ->formatStateUsing(fn (int $state): string => Money::format($state)),
                    TextEntry::make('grant_benefit')
                        ->label(__('Grant applied'))
                        ->state(fn (Registration $record): ?string => $record->grant?->benefitSummary())
                        ->placeholder(__('None')),
                    TextEntry::make('created_at')->label(__('Registered'))->dateTime(),
                    TextEntry::make('confirmed_at')
                        ->label(__('Confirmed'))
                        ->dateTime()
                        ->placeholder(__('Not yet')),
                    TextEntry::make('cancelled_at')->label(__('Cancelled'))->dateTime()->placeholder('—'),
                ])
                ->columns(2),

            Section::make(__('Who is staffing the table'))
                ->schema([
                    TextEntry::make('rep_name')->label(__('Name')),
                    TextEntry::make('rep_email')->label(__('Email')),
                    TextEntry::make('rep_phone')->label(__('Phone'))->placeholder('—'),
                ])
                ->columns(3),
        ]);
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
        ];
    }
}
