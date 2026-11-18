{{--
    Every other 5xx (docs/31): 500 has its own view and 503 is the maintenance
    page; this covers 502, 504 and the rest with 500's copy and the real code.
    Like 500, it renders while something is failing, so it reads only config.
--}}
<x-errors.page :code="$exception->getStatusCode()"
               :script="__('That\'s on us')"
               :heading="__('Something went wrong')">
    {{ __("An unexpected error occurred on our end. We're on it — try again in a moment, or let us know what happened.") }}

    <x-slot:actions>
        <a class="btn btn-solid" href="{{ url('/') }}">{{ __('Back to home') }}</a>
        <a class="btn btn-ghost" href="mailto:{{ config('fair.contact.email') }}">{{ __('Contact us') }}</a>
    </x-slot:actions>
</x-errors.page>
