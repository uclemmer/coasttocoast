{{--
    Access denied (design handoff, "Error Pages.dc.html").

    The design's second button is a log-in link, and it is the right one here:
    most 403s on this site are a representative reaching a portal or staff page
    while signed out as somebody else. See 404.blade.php for why `url()`.
--}}
<x-errors.page :code="403"
               :script="__('Members only')"
               :heading="__('Access denied')">
    {{ __("You don't have permission to view this page. Log in with a representative account, or head back home.") }}

    <x-slot:actions>
        <a class="btn btn-solid" href="{{ url('/') }}">{{ __('Back to home') }}</a>
        <a class="btn btn-ghost" href="{{ url('/login') }}">{{ __('Log in') }}</a>
    </x-slot:actions>
</x-errors.page>
