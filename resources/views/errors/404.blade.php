{{--
    Page not found (design handoff, "Error Pages.dc.html").

    Copy is the handoff's, which marks it final. The shell — and the reasons it
    is self-contained — is <x-errors.page>.

    `url()` rather than `route()`, here and in the siblings: an error view
    renders after something has already gone wrong, and on the 500 path that
    something may be a provider that booted before routing. A named-route
    lookup that throws inside the error view turns a handled 500 into a blank
    page. The home page's path is not going to move.
--}}
<x-errors.page :code="404"
               :script="__('Well, this is awkward')"
               :heading="__('Page not found')">
    {{ __("The page you're looking for has moved or never existed. Check the address, or head back to the fair.") }}

    <x-slot:actions>
        <a class="btn btn-solid" href="{{ url('/') }}">{{ __('Back to home') }}</a>
    </x-slot:actions>
</x-errors.page>
