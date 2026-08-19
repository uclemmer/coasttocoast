@php
    use App\Support\Money;

    // Assembled here, not in the tag's attribute — see the note in
    // payment-receipt.blade.php about Blade's component-tag parser.
    $number = str_pad((string) $registration->getKey(), 6, '0', STR_PAD_LEFT);

    $whatToSend = [
        __('Amount') => Money::format($registration->price_cents),
        __('Payable to') => 'Coast to Coast College Fair',
        __('Memo line') => __('Registration :number', ['number' => $number]),
        __('Fee assistance') => $registration->grant?->benefitSummary(),
    ];

    /*
     * The address comes from config/fair.php — the same source the public
     * footer, the contact page and the printed form use, so a move updates all
     * of them at once.
     */
    $address = trim(implode("\n", array_filter([
        config('fair.contact.name'),
        config('fair.contact.address_line1'),
        config('fair.contact.address_line2') ?: null,
        trim(implode(' ', array_filter([
            config('fair.contact.city') ? config('fair.contact.city').',' : null,
            config('fair.contact.state'),
            config('fair.contact.postal_code'),
        ]))),
    ])));

    $whereToSend = [__('Address') => $address];
@endphp
<x-emails::layout :title="__('How to pay')"
                  :preview="__('Your place is held. Here is where to send the check.')">

    <p>{{ __('Hello :name,', ['name' => $registration->rep_name]) }}</p>

    <p>{{ __(':organization has a place at :event. It is held for you now, and confirmed once your check arrives.', [
        'organization' => $registration->organization?->name,
        'event' => $registration->event?->name,
    ]) }}</p>

    <x-emails::panel :heading="__('What to send')" :rows="$whatToSend" />

    <x-emails::panel :heading="__('Where to send it')" :rows="$whereToSend" />

    <p>{{ __('The printable registration form is attached — send it with the check so we can match the two.') }}</p>
</x-emails::layout>
