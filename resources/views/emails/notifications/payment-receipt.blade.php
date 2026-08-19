@php
    use App\Support\Money;

    /*
     * Built here rather than inline in the component's `:rows` attribute.
     * Blade parses component tags with a regex over the raw attribute text, so
     * a double quote inside a PHP expression — `$a."\n".$b` — closes the
     * attribute early and the whole tag is left uncompiled and printed into
     * the email as source. Assembling the array first sidesteps the parser
     * entirely, and reads better besides.
     */
    $rows = [
        __('Fair') => $registration->event?->name,
        __('Date') => $registration->event?->starts_at?->format('l, F j, Y'),
        __('Venue') => trim($registration->event?->venue_name."\n".$registration->event?->venue_address),
        __('Amount paid') => Money::format($registration->price_cents),
        __('Fee assistance') => $registration->grant?->benefitSummary(),
        __('Receipt number') => str_pad((string) $registration->getKey(), 6, '0', STR_PAD_LEFT),
    ];
@endphp
<x-emails::layout :title="__('Registration confirmed')"
                  :preview="__('Your place at :event is confirmed.', ['event' => $registration->event?->name])">

    <p>{{ __('Hello :name,', ['name' => $registration->rep_name]) }}</p>

    <p>{{ __(':organization is confirmed for :event. Your receipt is attached.', [
        'organization' => $registration->organization?->name,
        'event' => $registration->event?->name,
    ]) }}</p>

    <x-emails::panel :heading="__('Your registration')" :rows="$rows" />

    <p>{{ __('We will be in touch closer to the date with parking, check-in and shipping details.') }}</p>
</x-emails::layout>
