{{--
    Every other 4xx (docs/31). Laravel falls back to this when there is no
    view for the exact code, so no client error is ever the framework's
    unstyled page. The code comes from the exception. Written here, not in
    the handoff.
--}}
<x-errors.page :code="$exception->getStatusCode()"
               :script="__('Hmm')"
               :heading="__('We couldn\'t do that')">
    {{ __('Something about that request did not work. Check the address, or head back to the fair.') }}

    <x-slot:actions>
        <a class="btn btn-solid" href="{{ url('/') }}">{{ __('Back to home') }}</a>
    </x-slot:actions>
</x-errors.page>
