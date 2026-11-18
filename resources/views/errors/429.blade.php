{{--
    Too many requests (docs/31). The contact form, the interest form and the
    login are throttled, so a visitor who resubmits quickly lands here. Written
    here, not in the handoff.
--}}
<x-errors.page :code="429"
               :script="__('Slow down a moment')"
               :heading="__('Too many requests')">
    {{ __("You've sent a lot of requests in a short time. Wait a minute, then try again.") }}

    <x-slot:actions>
        <a class="btn btn-solid" href="{{ url('/') }}">{{ __('Back to home') }}</a>
    </x-slot:actions>
</x-errors.page>
