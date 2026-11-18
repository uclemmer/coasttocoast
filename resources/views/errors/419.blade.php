{{--
    Page expired (docs/31): the CSRF token on a form ran out, almost always
    because the page sat open in a tab for hours. The likeliest page to see it
    is a representative's registration form, so the copy says what happened
    and that nothing is lost by reloading. Written here, not in the handoff.
--}}
<x-errors.page :code="419"
               :script="__('That took a while')"
               :heading="__('Page expired')">
    {{ __('This page was open for a long time, so for your security the form expired. Go back, reload the page and send it again.') }}

    <x-slot:actions>
        <a class="btn btn-solid" href="{{ url()->previous(url('/')) }}">{{ __('Go back') }}</a>
        <a class="btn btn-ghost" href="{{ url('/') }}">{{ __('Back to home') }}</a>
    </x-slot:actions>
</x-errors.page>
