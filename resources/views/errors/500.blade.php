{{--
    Something went wrong (design handoff, "Error Pages.dc.html").

    The one view in this folder that has to render while the application is
    already failing, which is why the shell touches no assets and no routes —
    see the header of <x-errors.page>.

    The contact address is the public one from config/fair.php, the same
    address the contact page and the footer publish. `config()` is safe here:
    it is bound long before anything this view could be reporting on.
--}}
<x-errors.page :code="500"
               :script="__('That\'s on us')"
               :heading="__('Something went wrong')">
    {{ __("An unexpected error occurred on our end. We're on it — try again in a moment, or let us know what happened.") }}

    <x-slot:actions>
        <a class="btn btn-solid" href="{{ url('/') }}">{{ __('Back to home') }}</a>
        <a class="btn btn-ghost" href="mailto:{{ config('fair.contact.email') }}">{{ __('Contact us') }}</a>
    </x-slot:actions>
</x-errors.page>
