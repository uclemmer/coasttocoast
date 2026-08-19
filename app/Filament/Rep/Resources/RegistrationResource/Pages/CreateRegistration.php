<?php

namespace App\Filament\Rep\Resources\RegistrationResource\Pages;

use App\Enums\PaymentMethod;
use App\Filament\Rep\Concerns\ActsForAnOrganization;
use App\Filament\Rep\Resources\RegistrationResource;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Services\RegistrationService;
use App\Support\Money;
use App\Support\Phone;
use Closure;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The registration wizard (card 3.2).
 *
 * Three steps: which fair, who is staffing the table, and how you are paying.
 *
 * The price shown in step three is `Event::priceFor()` — the same call that
 * produces the snapshot, and the same one Stripe is handed. It is displayed,
 * never accepted: there is no price field in this form and no argument for one
 * in the service, which is what makes "the client set the price" a thing that
 * cannot be expressed rather than a thing we check for (N1).
 *
 * A registration a grant makes free skips the payment question entirely and
 * confirms on the spot.
 */
class CreateRegistration extends CreateRecord
{
    use ActsForAnOrganization;

    protected static string $resource = RegistrationResource::class;

    public function getTitle(): string
    {
        return __('Register for a fair');
    }

    public function mount(): void
    {
        $this->abortUnlessActingForOrganization();

        parent::mount();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make(__('Which fair'))
                        ->schema([
                            Select::make('event_id')
                                ->label(__('Fair'))
                                ->required()
                                ->live()
                                ->options(fn (): array => $this->openFairs())
                                ->helperText(__('Only fairs that are open for registration are listed.'))
                                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    $this->refuseIfAlreadyRegistered($value, $fail);
                                }),
                        ]),

                    Step::make(__('Who is staffing the table'))
                        ->description(__('Not necessarily you — this is who we contact about this fair.'))
                        ->schema([
                            TextInput::make('rep_name')->label(__('Name'))->required()->maxLength(255),
                            TextInput::make('rep_email')->label(__('Email'))->email()->required()->maxLength(255),
                            TextInput::make('rep_phone')
                                ->label(__('Phone'))
                                ->tel()
                                ->maxLength(20)
                                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    if (! Phone::isValid(is_string($value) ? $value : null)) {
                                        $fail(__('Enter a phone number we can actually dial, e.g. (423) 757-2845.'));
                                    }
                                }),
                        ])
                        ->afterStateHydrated(fn (Set $set): mixed => $this->prefillContact($set))
                        ->columns(2),

                    Step::make(__('Payment'))
                        ->schema([
                            Text::make(fn (Get $get): string => $this->priceSummary($get('event_id')))
                                ->key('price_summary'),

                            Radio::make('payment_method')
                                ->label(__('How would you like to pay?'))
                                ->options([
                                    PaymentMethod::Stripe->value => __('Card, now'),
                                    PaymentMethod::Check->value => __('Check by mail'),
                                ])
                                ->descriptions([
                                    PaymentMethod::Stripe->value => __('You will be sent to our payment provider. We never see your card details.'),
                                    PaymentMethod::Check->value => __('We will email you a printable form and the address. Your place is held from now; it is confirmed when the check arrives.'),
                                ])
                                // Hidden and unnecessary when a grant covers
                                // the whole fee: there is nothing to pay.
                                ->visible(fn (Get $get): bool => $this->priceFor($get('event_id')) > 0)
                                ->required(fn (Get $get): bool => $this->priceFor($get('event_id')) > 0),
                        ]),
                ])
                    ->submitAction($this->getSubmitFormAction()),
            ])
            ->statePath('data');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $event = Event::query()->findOrFail($data['event_id']);

        try {
            return app(RegistrationService::class)->create(
                event: $event,
                organization: $this->currentOrganization(),
                rep: $this->currentUser(),
                method: filled($data['payment_method'] ?? null)
                    ? PaymentMethod::from($data['payment_method'])
                    : null,
                contact: [
                    'rep_name' => $data['rep_name'],
                    'rep_email' => $data['rep_email'],
                    'rep_phone' => Phone::normalize($data['rep_phone'] ?? null),
                ],
            );
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            throw ValidationException::withMessages(['data.event_id' => $e->getMessage()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        /** @var Registration $record */
        $record = $this->getRecord();

        return static::getResource()::getUrl('view', ['record' => $record]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        /** @var Registration $record */
        $record = $this->getRecord();

        return $record->isFree()
            ? Notification::make()
                ->title(__('You are registered.'))
                ->body(__('Your grant covers the fee in full, so there is nothing to pay. A confirmation is on its way.'))
                ->success()
            : Notification::make()
                ->title(__('Registration started.'))
                ->body(__('Your place is held. Follow the payment instructions to confirm it.'))
                ->success();
    }

    /**
     * Fairs this school can actually register for right now: open, and not
     * already held.
     *
     * @return array<int, string>
     */
    protected function openFairs(): array
    {
        $organization = $this->currentOrganization();

        return Event::query()
            ->published()
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (Event $event): bool => $event->isRegistrationOpen())
            ->mapWithKeys(fn (Event $event): array => [
                $event->getKey() => $event->name.' — '.Money::format($event->priceFor($organization)),
            ])
            ->all();
    }

    protected function priceFor(mixed $eventId): int
    {
        if (blank($eventId)) {
            return 0;
        }

        $event = Event::query()->find($eventId);

        return $event instanceof Event ? $event->priceFor($this->currentOrganization()) : 0;
    }

    /**
     * The sentence in step three. Says what the school pays and, when a grant
     * applies, why it differs from the list price — a discount nobody explains
     * is a discount somebody queries.
     */
    protected function priceSummary(mixed $eventId): string
    {
        $event = blank($eventId) ? null : Event::query()->find($eventId);

        if (! $event instanceof Event) {
            return __('Choose a fair first.');
        }

        $organization = $this->currentOrganization();
        $price = $event->priceFor($organization);
        $grant = $organization ? $event->approvedGrantFor($organization) : null;

        if ($price === 0) {
            return __('Your grant covers this fair in full. There is nothing to pay — press finish and you are registered.');
        }

        if ($grant !== null) {
            return __('Registration for :event is :list. Your grant (:benefit) brings that to :price.', [
                'event' => $event->name,
                'list' => Money::format($event->price_cents),
                'benefit' => (string) $grant->benefitSummary(),
                'price' => Money::format($price),
            ]);
        }

        return __('Registration for :event is :price.', [
            'event' => $event->name,
            'price' => Money::format($price),
        ]);
    }

    protected function prefillContact(Set $set): void
    {
        /** @var User $user */
        $user = $this->currentUser();

        $set('rep_name', $user->name);
        $set('rep_email', $user->email);
        $set('rep_phone', $user->phone);
    }

    protected function refuseIfAlreadyRegistered(mixed $eventId, Closure $fail): void
    {
        if (blank($eventId)) {
            return;
        }

        $event = Event::query()->find($eventId);
        $organization = $this->currentOrganization();

        if ($event instanceof Event
            && $organization !== null
            && app(RegistrationService::class)->alreadyRegistered($event, $organization)) {
            // Caught here as well as in the service so the rep is told at the
            // first step rather than after filling in the whole wizard.
            $fail(__('Your school is already registered for this fair.'));
        }
    }
}
