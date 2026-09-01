<?php

namespace App\Livewire\Portal;

use App\Enums\PaymentMethod;
use App\Enums\RegistrationStatus;
use App\Livewire\Portal\Concerns\ActsForAnOrganization;
use App\Models\Registration;
use App\Services\Payments\CheckPaymentForm;
use App\Services\Payments\PaymentGateway;
use App\Services\ReceiptPdf;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * One registration, from the organization's side — the Livewire replacement for the
 * rep panel's ViewRegistration page (docs/12).
 *
 * Readable by any rep of the owning organization, including pending and retired ones:
 * their organization's history is theirs to look at. The three actions are gated on
 * the registration's own state rather than on membership, because paying an
 * outstanding invoice is not something to lock a retired rep out of mid-flow.
 *
 * **Scoping happens in `mount()`, not in the route.** Route-model binding would
 * happily hand over any registration by id; the check that it belongs to the
 * viewer's organization is what stops one organization reading another's contact details
 * and fee.
 *
 * It re-resolves through a scoped query rather than checking the bound model's
 * `organization_id`, and the difference is not cosmetic: a scoped `findOrFail`
 * says the record does not exist, where a check-then-refuse says "it exists,
 * you cannot have it". The second answer is an oracle — it confirms a
 * registration with that id is real. Carried over from the Filament resource,
 * whose query scope did the same thing.
 */
#[Layout('components.layouts.portal', ['title' => 'Registration'])]
class ShowRegistration extends Component
{
    use ActsForAnOrganization;

    public Registration $registration;

    public function mount(Registration $registration): void
    {
        $this->registration = Registration::query()
            ->with(['event', 'organization'])
            ->where('organization_id', $this->currentUser()->organization_id)
            ->findOrFail($registration->getKey());
    }

    /**
     * Open (or reopen) Stripe Checkout.
     *
     * **The retry path matters more than the happy one.** A rep who closed the
     * tab, whose card was declined, or who hit a Stripe outage mid-signup still
     * has their place held, and this is how they finish.
     */
    public function pay(): mixed
    {
        if (! $this->canPay()) {
            return null;
        }

        try {
            return redirect()->away(app(PaymentGateway::class)->createSession($this->registration)->url);
        } catch (Throwable $e) {
            report($e);

            $this->toast(__('We could not open the payment page. Please try again shortly.'), 'danger');

            return null;
        }
    }

    /**
     * The printable form to post with a check.
     *
     * Downloadable as well as emailed, because the person who registers is
     * often not the person who writes the checks, and forwarding an email
     * attachment is a step where things get lost.
     */
    public function checkForm(): ?StreamedResponse
    {
        if (! $this->needsCheckForm()) {
            return null;
        }

        $pdf = app(CheckPaymentForm::class);

        return response()->streamDownload(
            fn () => print $pdf->render($this->registration),
            $pdf->filenameFor($this->registration),
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Download the receipt.
     *
     * Confirmed registrations only. A receipt for money that has not arrived is
     * exactly the document a finance office would file and forget about, and
     * then everyone is surprised in April.
     */
    public function receipt(): ?StreamedResponse
    {
        $pdf = app(ReceiptPdf::class);

        // The service owns when a receipt exists; asking it beats repeating
        // the rule here and letting the two drift.
        if (! $pdf->isAvailableFor($this->registration)) {
            return null;
        }

        return response()->streamDownload(
            fn () => print $pdf->render($this->registration),
            $pdf->filenameFor($this->registration),
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function canPay(): bool
    {
        return $this->registration->status === RegistrationStatus::PendingPayment
            && $this->registration->payment_method === PaymentMethod::Stripe
            && ! $this->registration->isFree();
    }

    public function needsCheckForm(): bool
    {
        return $this->registration->status === RegistrationStatus::PendingPayment
            && $this->registration->payment_method === PaymentMethod::Check;
    }

    public function hasReceipt(): bool
    {
        return app(ReceiptPdf::class)->isAvailableFor($this->registration);
    }

    public function render(): View
    {
        return view('livewire.portal.show-registration');
    }
}
