<?php

namespace App\Livewire\Staff\Registrations;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use App\Livewire\Staff\Concerns\ActsForStaff;
use App\Models\Payment;
use App\Models\Registration;
use App\Services\Payments\CheckPaymentService;
use App\Services\Payments\PaymentGateway;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * One registration, and the money on it (docs/13) — replaces the admin panel's
 * ViewRegistration and EditRegistration pages and their two header actions.
 *
 * WHAT IS EDITABLE HERE IS DELIBERATELY SMALL: the roster toggle, internal
 * notes and the fair contact. Status, price, school and fair are not, because
 * each is the outcome of a decision that has to go through a service so
 * receipts are sent and the price snapshot stays honest.
 *
 * BOTH MONEY DIALOGS TAKE DOLLARS AND STORE CENTS, through `Money`, and the
 * check amount defaults to what is owed rather than to empty — the common case
 * is a check for the right amount, and making somebody retype it invites a typo
 * into the one number that matters.
 */
#[Layout('components.layouts.staff', ['title' => 'Registration'])]
class Show extends Component
{
    use ActsForStaff;

    public Registration $registration;

    // --- the editable half -------------------------------------------------

    public bool $show_on_roster = false;

    public string $notes = '';

    public string $rep_name = '';

    public string $rep_email = '';

    public string $rep_phone = '';

    // --- the check dialog --------------------------------------------------

    public string $checkNumber = '';

    public string $receivedOn = '';

    public string $checkAmountDollars = '';

    // --- the refund dialog -------------------------------------------------

    public string $refundAmountDollars = '';

    /**
     * A check that came in short, kept until dismissed.
     *
     * Filament raised a `->persistent()` notification for this; a toast
     * auto-dismisses, and "the check is short" is exactly the message that must
     * still be on screen when somebody works out what to do about it.
     */
    public string $shortfall = '';

    public function mount(Registration $registration): void
    {
        $this->abortUnlessStaff();
        $this->authorize('view', $registration);

        $this->registration = $registration;
        $this->fillFromRecord();
    }

    protected function fillFromRecord(): void
    {
        $record = $this->record;

        $this->show_on_roster = (bool) $record->show_on_roster;
        $this->notes = (string) $record->notes;
        $this->rep_name = (string) $record->rep_name;
        $this->rep_email = (string) $record->rep_email;
        $this->rep_phone = (string) $record->rep_phone;

        $this->checkAmountDollars = number_format(Money::toDollars($record->price_cents), 2, '.', '');
        $this->receivedOn = now()->format('Y-m-d');
        $this->refundAmountDollars = $this->settledPayment() === null
            ? ''
            : number_format(Money::toDollars($record->price_cents), 2, '.', '');
    }

    /** Re-read: every action here changes what the page says. */
    #[Computed]
    public function record(): Registration
    {
        return Registration::query()
            ->with(['organization', 'event', 'grant', 'payments'])
            ->findOrFail($this->registration->getKey());
    }

    /** The settled card payment a refund would go against, if there is one. */
    public function settledPayment(): ?Payment
    {
        return $this->record->payments
            ->first(fn (Payment $payment): bool => $payment->method === PaymentMethod::Stripe
                && $payment->status === PaymentStatus::Succeeded);
    }

    public function canRecordPayment(): bool
    {
        return $this->currentUser()->can('recordPayment', $this->record);
    }

    public function canMarkCheckReceived(): bool
    {
        $record = $this->record;

        return $record->status === RegistrationStatus::PendingPayment
            && $record->payment_method === PaymentMethod::Check
            && $this->canRecordPayment();
    }

    public function canRefund(): bool
    {
        return $this->settledPayment() !== null && $this->canRecordPayment();
    }

    public function saveDetails(): void
    {
        $record = $this->record;

        $this->authorize('update', $record);

        $validated = $this->validate([
            'rep_name' => ['required', 'string', 'max:255'],
            'rep_email' => ['required', 'email', 'max:255'],
            'rep_phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
            'show_on_roster' => ['boolean'],
        ]);

        $record->forceFill([
            'rep_name' => $validated['rep_name'],
            'rep_email' => $validated['rep_email'],
            'rep_phone' => $this->rep_phone === '' ? null : $this->rep_phone,
            'notes' => $this->notes === '' ? null : $this->notes,
            'show_on_roster' => $this->show_on_roster,
        ])->save();

        unset($this->record);

        $this->toast(__('Registration updated.'));
    }

    public function confirmCheck(): void
    {
        $this->dispatch('ui-modal-open', id: 'record-check');
    }

    /**
     * Record a mailed check.
     *
     * A short check is **surfaced, not blocked**. The alternative is noticing
     * in April.
     */
    public function markCheckReceived(CheckPaymentService $service): void
    {
        $record = $this->record;

        $this->authorize('recordPayment', $record);

        $this->validate([
            'checkNumber' => ['nullable', 'string', 'max:255'],
            'receivedOn' => ['required', 'date'],
            'checkAmountDollars' => ['required', 'numeric', 'min:0'],
        ]);

        $amountCents = Money::toCents($this->checkAmountDollars);

        try {
            $service->markReceived(
                registration: $record,
                coordinator: $this->currentUser(),
                checkNumber: $this->checkNumber === '' ? null : $this->checkNumber,
                receivedOn: Carbon::parse($this->receivedOn),
                amountCents: $amountCents,
            );
        } catch (Throwable $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $owed = $record->price_cents;

        unset($this->record);
        $this->dispatch('ui-modal-close', id: 'record-check');

        if ($amountCents < $owed) {
            $this->shortfall = __(':paid received against :owed owed.', [
                'paid' => Money::format($amountCents),
                'owed' => Money::format($owed),
            ]);

            return;
        }

        $this->shortfall = '';
        $this->toast(__('Check recorded, and the receipt is queued.'));
    }

    public function dismissShortfall(): void
    {
        $this->shortfall = '';
    }

    public function confirmRefund(): void
    {
        $this->dispatch('ui-modal-open', id: 'refund-payment');
    }

    public function refund(PaymentGateway $gateway): void
    {
        $record = $this->record;

        $this->authorize('recordPayment', $record);

        $payment = $this->settledPayment();

        if ($payment === null) {
            $this->toast(__('There is no settled card payment to refund.'), 'danger');

            return;
        }

        $this->validate(['refundAmountDollars' => ['required', 'numeric', 'min:0']]);

        try {
            $gateway->refund($payment, Money::toCents($this->refundAmountDollars));
        } catch (Throwable $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        unset($this->record);

        $this->dispatch('ui-modal-close', id: 'refund-payment');

        /*
         * Deliberately does not say "refunded". Stripe sends `charge.refunded`
         * and that one handler owns the transition, so an admin-initiated
         * refund and one issued from the Stripe dashboard leave the database in
         * the same state.
         */
        $this->toast(__('Refund sent to Stripe. The payment updates when Stripe confirms it.'));
    }

    public function render(): View
    {
        return view('livewire.staff.registrations.show');
    }
}
