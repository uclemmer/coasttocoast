{{--
    laravel-legal's layout, overridden (docs/31) so its pages sit inside the
    site: header, footer, fonts and all. The package ships bare HTML on
    purpose, for the host to wrap.

    The document page, its history and the index have their own overrides
    beside this file. This shell is what the package's re-consent screen
    renders in; it is wired but off (`legal.reconsent.enabled`).
--}}
@props(['title' => null])

<x-layouts.app :title="$title ?? __('Legal')">
    <x-site.container class="py-14">
        <div class="max-w-[72ch]">
            {{ $slot }}
        </div>
    </x-site.container>
</x-layouts.app>
