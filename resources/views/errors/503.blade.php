{{--
    The maintenance page (design handoff, "Maintenance Page.dc.html").

    Laravel serves this view for `php artisan down` AND for a genuine 503, and
    the handoff draws two different pages for those two cases — "Down for
    maintenance" here, "Service unavailable" in "Error Pages.dc.html". This app
    keeps the maintenance design, which is the handoff's own default and what
    docs/08's runbook prerenders. The alternative it offers — a custom `down`
    template so the error design can own 503 — buys a distinction nobody on the
    outside can act on differently. See docs/16.

    It shares its shell with the four error views; passing no `code` is what
    selects the maintenance proportions. Read <x-errors.page> for why that
    shell touches no assets, no routes and no database — the reasons are
    sharper here than anywhere, because `artisan down --render=errors::503`
    freezes this page to a flat file at the exact moment public/build is being
    replaced.
--}}
<x-errors.page :script="__('We\'ll be right back')"
               :heading="__('Down for maintenance')">
    {{ __("We're making a few improvements to the site. Check back shortly — the fair itself is right on schedule.") }}

    <x-slot:actions>
        <a class="btn btn-solid" href="mailto:{{ config('fair.coordinator.email') }}">
            {{ __('Email us in the meantime') }}
        </a>
    </x-slot:actions>
</x-errors.page>
