<?php

namespace App\Livewire\Portal;

use App\Enums\PaymentMethod;
use App\Livewire\Portal\Concerns\ActsForAnOrganization;
use App\Models\Event;
use App\Models\Registration;
use App\Services\Payments\PaymentGateway;
use App\Services\RegistrationService;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * Registering for a fair (card 3.2) — the Livewire replacement for the rep
 * panel's CreateRegistration wizard (docs/12).
 *
 * **One page with three sections, not a wizard** (owner decision, 2026-08-21).
 * The Filament version was a three-step wizard; laravel-ui has no stepper, and
 * the content here is short enough that a single page shows the whole
 * commitment at once — which is the better shape for a form that ends in a
 * payment. A wizard component is noted on the package's roadmap for when
 * something genuinely needs one.
 *
 * **THE PRICE IS DISPLAYED, NEVER ACCEPTED (N1).** There is no price field on
 * this form and no price argument in `RegistrationService::create()`. The
 * number shown is `Event::priceFor($organization)` — the same call that
 * produces the stored snapshot and the same one Stripe is handed. "The client
 * set the price" is not something guarded against here; it is something that
 * cannot be expressed.
 *
 * A registration a grant makes free skips the payment question entirely and
 * confirms on the spot.
 */
#[Layout('components.layouts.portal', ['title' => 'Register for a fair', 'heading' => 'Register for a fair'])]
class CreateRegistration extends Component
{
    use ActsForAnOrganization;

    public ?int $event_id = null;

    public string $rep_name = '';

    public string $rep_email = '';

    public string $rep_phone = '';

    public ?string $payment_method = null;

    public function mount(?Event $event = null): void
    {
        $this->abortUnlessActingForOrganization();

        // Arriving from the dashboard's "register for X" button.
        if ($event?->exists) {
            $this->event_id = $event->getKey();
        }

        // Whoever staffs the table is usually the person signing up, so the
        // contact starts as them and can be changed.
        $user = $this->currentUser();
        $this->rep_name = $user->name;
        $this->rep_email = $user->email;
        $this->rep_phone = (string) $user->phone;
    }

    /**
     * Fairs this organization can actually register for right now: open, and not
     * already held.
     *
     * @return Collection<int, Event>
     */
    #[Computed]
    public function openFairs(): Collection
    {
        $organization = $this->currentOrganization();

        return Event::query()
            ->published()
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (Event $event): bool => $event->isRegistrationOpen())
            ->values();
    }

    #[Computed]
    public function chosenEvent(): ?Event
    {
        return $this->event_id === null ? null : Event::query()->find($this->event_id);
    }

    /** What this organization pays, in cents. Always from the server. */
    #[Computed]
    public function price(): int
    {
        $event = $this->chosenEvent;

        return $event instanceof Event ? $event->priceFor($this->currentOrganization()) : 0;
    }

    /** Whether there is anything to pay, and so whether to ask how. */
    #[Computed]
    public function payable(): bool
    {
        return $this->chosenEvent !== null && $this->price > 0;
    }

    /**
     * The sentence above the payment choice. Says what the organization pays and,
     * when a grant applies, why it differs from the list price — a discount
     * nobody explains is a discount somebody queries.
     */
    #[Computed]
    public function priceSummary(): string
    {
        $event = $this->chosenEvent;

        if (! $event instanceof Event) {
            return __('Choose a fair first.');
        }

        $organization = $this->currentOrganization();
        $price = $this->price;
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

    /** A fair's name with its price, for the chooser. */
    public function fairLabel(Event $event): string
    {
        return $event->name.' — '.Money::format($event->priceFor($this->currentOrganization()));
    }

    public function submit(RegistrationService $service): mixed
    {
        $this->abortUnlessActingForOrganization();

        $validated = $this->validate([
            'event_id' => [
                'required',
                'integer',
                'exists:events,id',
                function (string $attribute, mixed $value, callable $fail) use ($service): void {
                    $event = Event::query()->find($value);
                    $organization = $this->currentOrganization();

                    if ($event instanceof Event
                        && $organization !== null
                        && $service->alreadyRegistered($event, $organization)) {
                        // Checked here as well as in the service so the rep is
                        // told against the field rather than by a thrown error.
                        $fail(__('Your organization is already registered for this fair.'));
                    }
                },
            ],
            'rep_name' => ['required', 'string', 'max:255'],
            'rep_email' => ['required', 'email', 'max:255'],
            'rep_phone' => [
                'nullable',
                'string',
                'max:20',
                function (string $attribute, mixed $value, callable $fail): void {
                    if (! Phone::isValid(is_string($value) ? $value : null)) {
                        $fail(__('Enter a phone number we can actually dial, e.g. (423) 757-2845.'));
                    }
                },
            ],
            // Only asked, and only required, when there is something to pay.
            'payment_method' => [$this->payable ? 'required' : 'nullable', 'in:'.PaymentMethod::Stripe->value.','.PaymentMethod::Check->value],
        ]);

        try {
            $registration = $service->create(
                event: Event::query()->findOrFail($validated['event_id']),
                organization: $this->currentOrganization(),
                rep: $this->currentUser(),
                method: filled($validated['payment_method'] ?? null)
                    ? PaymentMethod::from($validated['payment_method'])
                    : null,
                contact: [
                    'rep_name' => $validated['rep_name'],
                    'rep_email' => $validated['rep_email'],
                    'rep_phone' => Phone::normalize($this->rep_phone ?: null),
                ],
            );
        } catch (Throwable $e) {
            $this->addError('event_id', $e->getMessage());

            return null;
        }

        return $this->afterCreated($registration);
    }

    /**
     * Card payers go to Stripe; everyone else lands on their registration.
     *
     * The gateway is called here rather than inside `RegistrationService`
     * because the registration must exist and be saved first — the session
     * carries its id, and **a Stripe outage must leave a recoverable
     * `pending_payment` row rather than losing the whole registration.** If the
     * session cannot be opened the rep still has their place, and the detail
     * page's retry button is what they use next.
     */
    protected function afterCreated(Registration $registration): mixed
    {
        $detail = route('portal.registrations.show', $registration);

        if ($registration->isFree()) {
            session()->flash('status', __('You are registered. Your grant covers the fee in full, so there is nothing to pay. A confirmation is on its way.'));

            return redirect()->to($detail);
        }

        if ($registration->payment_method !== PaymentMethod::Stripe) {
            session()->flash('status', __('Registration started. Your place is held. Follow the payment instructions to confirm it.'));

            return redirect()->to($detail);
        }

        try {
            return redirect()->away(app(PaymentGateway::class)->createSession($registration)->url);
        } catch (Throwable $e) {
            report($e);

            session()->flash('status', __('We could not open the payment page. Your place is held — use the payment button below to try again.'));

            return redirect()->to($detail);
        }
    }

    /**
     * The select is labelled "Fair". Without this its failures say "the event
     * id field is required" and name a column the rep never sees — including
     * the already-registered refusal, which is the one they actually hit.
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'event_id' => __('fair'),
        ];
    }

    public function render(): View
    {
        return view('livewire.portal.create-registration');
    }
}
