{{--
    The staff shell (docs/13), replacing the fair's half of Filament's /admin
    panel.

    Same shape as the rep portal's layout, and deliberately so: a sidebar on
    desktop, an off-canvas drawer below `sm`, driven by inline Alpine. Two
    shells that behave differently for no reason is worse than a little
    duplication, and the divergence that matters is only the navigation and the
    accent.

    `sm:translate-x-0` pins the sidebar open above the breakpoint, and because
    that is a breakpoint variant it outranks the base translate Alpine toggles.
    So the state below only decides what happens on a phone.

    `@fonts` BEFORE `@vite` — see .ai/rules/layouts.md.

    `@livewireScripts` is explicit. Every staff page is a full-page Livewire
    component today, which would inject the assets anyway, but relying on that
    means the first plain Blade page added here silently loses Alpine and every
    interactive component on it goes inert. That exact hazard was written down
    in docs/12 and still shipped once — see docs/13.

    NOT THE ONLY STAFF SURFACE, for now. laravel-core keeps its Filament panel
    at /admin for users, roles, the email log, content and settings until step 4
    of the workspace Filament removal. The "Users, content, settings" link below
    goes there on purpose; when core goes headless it comes back here.
--}}
@props(['title' => null, 'heading' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title . ' — ' . __('Staff') . ' — ' . config('app.name') : __('Staff') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">

    @fonts
    @livewireScripts

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>

<body x-data="{ sidebar: false }" x-on:keydown.escape.window="sidebar = false"
    class="min-h-full bg-neutral-secondary-soft font-sans text-ink-800 antialiased">

    <header class="fixed top-0 z-30 w-full border-b border-default bg-neutral-primary">
        <div class="flex items-center justify-between gap-3 px-4 py-3">
            <div class="flex items-center gap-3">
                <button type="button" x-on:click="sidebar = ! sidebar" x-bind:aria-expanded="sidebar"
                    aria-controls="staff-sidebar"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-base text-body hover:bg-neutral-secondary-soft hover:text-heading focus:outline-none focus:ring-2 focus:ring-neutral-tertiary sm:hidden">
                    <span class="sr-only">{{ __('Open menu') }}</span>
                    <svg class="h-6 w-6" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-width="2"
                            d="M5 7h14M5 12h14M5 17h14" />
                    </svg>
                </button>

                <a href="{{ route('staff.dashboard') }}"
                    class="font-display text-lg font-semibold tracking-tight text-brand-700">
                    {{ config('app.name') }}
                    <span class="font-sans text-sm font-normal text-body">{{ __('Staff') }}</span>
                </a>
            </div>

            <x-ui::dropdown id="staff-account" :label="auth()->user()->name">
                <x-ui::dropdown.item href="{{ url('/admin') }}">{{ __('Users, content, settings') }}</x-ui::dropdown.item>
                <x-ui::dropdown.item href="{{ url('/') }}">{{ __('Back to the site') }}</x-ui::dropdown.item>
            </x-ui::dropdown>
        </div>
    </header>

    {{-- Backdrop, below `sm` only: above it the sidebar is a static column. --}}
    <div x-show="sidebar" x-cloak x-transition.opacity x-on:click="sidebar = false"
        class="fixed inset-0 z-30 bg-dark-backdrop/50 sm:hidden" aria-hidden="true"></div>

    <aside id="staff-sidebar" aria-label="{{ __('Staff') }}"
        x-bind:class="sidebar ? 'translate-x-0' : '-translate-x-full'"
        class="fixed left-0 top-0 z-40 h-screen w-64 -translate-x-full border-e border-default bg-neutral-primary pt-16 transition-transform sm:translate-x-0">
        <nav class="h-full overflow-y-auto px-3 py-5">
            {{--
                A hardcoded list, as the portal's is. A nav registry would be
                the obvious abstraction and is not worth it for one file that
                changes when a screen is added — which is six more times, and
                then never.

                Links are not permission-filtered yet. Every screen authorises
                itself on mount, so a link somebody cannot use 403s rather than
                misleading them into a broken page; hiding them is a courtesy to
                add once all seven exist and the permission set is settled.
            --}}
            <ul class="space-y-1 font-medium">
                @foreach ([
        ['staff.dashboard', __('Overview')],
        ['staff.events', __('Fairs')],
        ['staff.organizations', __('Schools')],
        ['staff.registrations', __('Registrations')],
        ['staff.grants', __('Fee assistance')],
        ['staff.messages', __('Campaigns')],
        ['staff.faq', __('FAQ')],
        ['staff.sponsors', __('Sponsors')],
    ] as [$route, $label])
                    @php $current = request()->routeIs($route . '*'); @endphp
                    <li>
                        <a href="{{ route($route) }}" @if ($current) aria-current="page" @endif
                            class="flex items-center rounded-base px-3 py-2 text-sm {{ $current ? 'bg-brand-100 font-semibold text-fg-brand' : 'text-body hover:bg-neutral-secondary-soft hover:text-heading' }}">
                            {{ $label }}
                        </a>
                    </li>
                @endforeach
            </ul>

            <form method="POST" action="{{ route('core.logout') }}" class="mt-6 px-3">
                @csrf
                <button type="submit" class="text-sm text-body hover:text-heading">{{ __('Log out') }}</button>
            </form>
        </nav>
    </aside>

    <div class="p-4 pt-20 sm:ml-64">
        @if ($heading)
            <h1 class="mb-6 font-display text-2xl font-bold tracking-tight text-heading">{{ $heading }}</h1>
        @endif

        @if (session('status'))
            <x-ui::alert variant="success">{{ session('status') }}</x-ui::alert>
        @endif

        {{ $slot }}
    </div>

    {{-- The live region every staff action raises its feedback through. --}}
    <x-ui::toast />
</body>

</html>
