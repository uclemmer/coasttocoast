<?php

namespace App\Services;

use App\Models\Registration;
use App\Support\Money;

/**
 * The registrations CSV: one set of columns for the staff export and the
 * monthly report email (docs/29), so the two can never disagree about what a
 * row says.
 */
class RegistrationCsv
{
    /**
     * @return list<string>
     */
    public function header(): array
    {
        return [
            'Organization', 'Fair', 'Status', 'Payment method', 'Price', 'Grant',
            'Contact name', 'Contact email', 'Contact phone',
            'On roster', 'Registered', 'Confirmed',
        ];
    }

    /**
     * @return list<string|null>
     */
    public function row(Registration $registration): array
    {
        return [
            $registration->organization?->name,
            $registration->event?->name,
            $registration->status->getLabel(),
            $registration->payment_method?->getLabel() ?? 'Free',
            Money::format($registration->price_cents),
            $registration->grant?->benefitSummary() ?? '',
            $registration->rep_name,
            $registration->rep_email,
            $registration->rep_phone ?? '',
            $registration->show_on_roster ? 'yes' : 'no',
            $registration->created_at?->toDateString(),
            $registration->confirmed_at?->toDateString() ?? '',
        ];
    }

    /**
     * Write the header and every row to an open stream.
     *
     * @param  resource  $handle
     * @param  iterable<Registration>  $registrations
     */
    public function write($handle, iterable $registrations): void
    {
        fputcsv($handle, $this->header());

        foreach ($registrations as $registration) {
            fputcsv($handle, $this->row($registration));
        }
    }

    /**
     * The whole CSV as a string, for an email attachment.
     *
     * @param  iterable<Registration>  $registrations
     */
    public function toString(iterable $registrations): string
    {
        $handle = fopen('php://temp', 'r+b');
        $this->write($handle, $registrations);
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
