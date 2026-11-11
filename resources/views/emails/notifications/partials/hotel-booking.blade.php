{{--
    The hotel's group-rate booking link, when the fair has one (docs/29).

    Shared by the payment receipt and the pay-by-check instructions, so a rep
    gets the link whichever way they pay. It is a column on the fair, not a
    URL in this file, because the hotel issues a new link every year.
--}}
@if (filled($event?->hotel_booking_url))
    {{-- Says "our group rate", not "at the venue": the hotel and the venue
         happen to be one building in 2027, and need not be next year. --}}
    <p>{{ __('Staying overnight? Book your room at the fair’s group rate.') }}</p>

    <x-emails::button :url="$event->hotel_booking_url">
        {{ __('Book your hotel room') }}
    </x-emails::button>
@endif
