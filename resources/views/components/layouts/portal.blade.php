{{--
    The representative portal shell, replacing Filament's rep panel chrome
    (docs/12).

    A sidebar on desktop, an off-canvas drawer below `sm`, driven by inline
    Alpine — the same shape laravel-ui's published admin layout uses, written
    out here rather than published because the navigation, the brand and the
    membership banner are all this app's.

    `sm:translate-x-0` pins the sidebar open above the breakpoint, and because
    that is a breakpoint variant it outranks the base translate Alpine toggles.
    So the state below only decides what happens on a phone.

    `@fonts` BEFORE `@vite` — see .ai/rules/layouts.md.

    `@livewireScripts` is explicit. Every portal page happens to be a full-page
    Livewire component, which would inject assets anyway, but relying on that
    means the first plain Blade page added here silently loses Alpine and every
    interactive component on it goes inert. See docs/12.
--}}
@props(['title' => null, 'heading' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title . ' — ' . config('app.name') : config('app.name') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">

    @fonts
    @livewireScripts

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>

<body x-data="{ sidebar: false }" x-on:keydown.escape.window="sidebar = false"
    class="min-h-full bg-brand-100 font-sans text-ink-800 antialiased">

    <header class="fixed top-0 z-30 w-full border-b border-default bg-neutral-primary">
        <div class="flex items-center justify-between gap-3 px-4 py-3">
            <div class="flex items-center gap-3">
                <button type="button" x-on:click="sidebar = ! sidebar" x-bind:aria-expanded="sidebar"
                    aria-controls="portal-sidebar"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-base text-body hover:bg-neutral-secondary-soft hover:text-heading focus:outline-none focus:ring-2 focus:ring-neutral-tertiary sm:hidden">
                    <span class="sr-only">{{ __('Open menu') }}</span>
                    <svg class="h-6 w-6" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-width="2"
                            d="M5 7h14M5 12h14M5 17h14" />
                    </svg>
                </button>

                <a href="{{ route('portal.dashboard') }}"
                    class="font-display text-lg font-semibold tracking-tight text-brand-700">
                    {{ config('app.name') }}
                </a>
            </div>

            <x-ui::dropdown id="portal-account" :label="auth()->user()->name">
                <x-ui::dropdown.item href="{{ route('portal.profile') }}">{{ __('Your details') }}</x-ui::dropdown.item>
                <x-ui::dropdown.item href="{{ url('/') }}">{{ __('Back to the site') }}</x-ui::dropdown.item>
            </x-ui::dropdown>
        </div>
    </header>

    {{-- Backdrop, below `sm` only: above it the sidebar is a static column. --}}
    <div x-show="sidebar" x-cloak x-transition.opacity x-on:click="sidebar = false"
        class="fixed inset-0 z-30 bg-dark-backdrop/50 sm:hidden" aria-hidden="true"></div>

    <aside id="portal-sidebar" aria-label="{{ __('Portal') }}"
        x-bind:class="sidebar ? 'translate-x-0' : '-translate-x-full'"
        class="fixed left-0 top-0 z-40 h-screen w-64 -translate-x-full border-e border-default bg-neutral-primary pt-16 transition-transform sm:translate-x-0">
        <nav class="h-full overflow-y-auto px-3 py-5">
            <ul class="space-y-1 font-medium">
                @foreach ([
        ['portal.dashboard', __('Overview')],
        ['portal.registrations', __('Registrations')],
        ['portal.grants', __('Fee assistance')],
        ['portal.organization', __('Your organization')],
        ['portal.profile', __('Your details')],
    ] as [$route, $label])
                    @php $current = request()->routeIs($route); @endphp
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

    {{-- The live region every portal action raises its feedback through. --}}
    <x-ui::toast />
</body>

</html>
