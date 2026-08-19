@php
    use App\Support\Money;

    // Assembled here, not in the tag's attribute — see the note in
    // payment-receipt.blade.php about Blade's component-tag parser.
    $rows = [
        __('Date') => $event->starts_at?->format('l, F j, Y'),
        __('Time') => $event->starts_at?->format('g:i A').' – '.$event->ends_at?->format('g:i A'),
        __('Venue') => trim($event->venue_name."\n".$event->venue_address),
        __('Registration') => Money::format($event->price_cents),
        __('Closes') => $event->registration_closes_at?->format('l, F j, Y'),
    ];
@endphp
{{-- A campaign, not a receipt: the footer carries the CAN-SPAM line. --}}
<x-emails::layout :title="__('Registration is open')"
                  :campaign="true"
                  :preview="__('Registration for :event is now open.', ['event' => $event->name])">

    <p>{{ __('You asked us to let you know when registration opened for :event. It is open now.', [
        'event' => $event->name,
    ]) }}</p>

    <x-emails::panel :heading="__('The fair')" :rows="$rows" />

    <x-emails::button :url="url('/events/'.$event->slug)">
        {{ __('Register your institution') }}
    </x-emails::button>

    <p>{{ __('If the fee is a barrier, you can request assistance from the portal once you have an account.') }}</p>
</x-emails::layout>
