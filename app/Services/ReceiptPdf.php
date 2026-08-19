<?php

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Models\Registration;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * The printable receipt a school gives its finance office (card 3.3).
 *
 * Renders from the registration's own snapshot — `price_cents`, `rep_name`,
 * the grant that was applied — rather than recomputing anything. A receipt
 * that recalculated the price would quietly disagree with the invoice the
 * moment the fair's price changed, which is the one thing a receipt must never
 * do.
 *
 * Only confirmed registrations get one. A receipt for money that has not
 * arrived is exactly the document a school would file and forget about.
 */
class ReceiptPdf
{
    public function isAvailableFor(Registration $registration): bool
    {
        return $registration->status === RegistrationStatus::Confirmed;
    }

    public function filenameFor(Registration $registration): string
    {
        return 'receipt-'.$registration->event?->slug.'-'.$registration->getKey().'.pdf';
    }

    /**
     * The raw PDF bytes, for a download response or a mail attachment.
     */
    public function render(Registration $registration): string
    {
        $registration->loadMissing(['event', 'organization', 'grant', 'payments']);

        return Pdf::loadView('pdf.receipt', [
            'registration' => $registration,
            'payment' => $registration->successfulPayment(),
            'fair' => config('fair.contact'),
        ])->output();
    }
}
