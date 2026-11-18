{{--
    Not signed in (docs/31). The handoff draws 404, 403 and 500 only, so this
    copy was written here in the same voice, not approved with the design.
    See 404.blade.php for why `url()` rather than `route()`.
--}}
<x-errors.page :code="401"
               :script="__('Who goes there?')"
               :heading="__('Please log in')">
    {{ __('You need to be logged in to see this page. Log in with your representative account, or head back home.') }}

    <x-slot:actions>
        <a class="btn btn-solid" href="{{ url('/login') }}">{{ __('Log in') }}</a>
        <a class="btn btn-ghost" href="{{ url('/') }}">{{ __('Back to home') }}</a>
    </x-slot:actions>
</x-errors.page>
