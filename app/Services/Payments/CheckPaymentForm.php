<?php

namespace App\Services\Payments;

use App\Models\Registration;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * The printable form a school posts with its check (card 4.2).
 *
 * Carries the grant-aware amount from the registration's snapshot, not the
 * fair's list price: a school with a 50% grant that mails a full-price check
 * has to be refunded, and everyone involved would rather that never happened.
 *
 * Also carries the registration number, because a check with nothing but a
 * school name on it is how a payment ends up unmatched in a drawer.
 */
class CheckPaymentForm
{
    public function filenameFor(Registration $registration): string
    {
        return 'registration-form-'.$registration->event?->slug.'-'.$registration->getKey().'.pdf';
    }

    public function render(Registration $registration): string
    {
        $registration->loadMissing(['event', 'organization', 'grant']);

        return Pdf::loadView('pdf.check-form', [
            'registration' => $registration,
            'fair' => config('fair.contact'),
        ])->output();
    }
}
