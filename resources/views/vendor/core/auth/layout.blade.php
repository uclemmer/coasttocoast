{{--
    The shell for every laravel-core auth screen — login, forgot password,
    reset password, two-factor.

    PUBLISHED AND OWNED (docs/12). The package ships these views deliberately
    unstyled and dependency-free so a host can do exactly this; the copy stops
    receiving package updates, which is the trade.

    It deliberately does NOT extend the public site's layout. An auth screen
    wants no navigation, no footer links and nothing else to click — the page
    has one job. What it does share is the site's chrome: same wordmark, same
    typography, same green, because a login page that looks like a different
    product is how a phishing page looks.

    `@livewireScripts` is here because these are plain Blade pages. Livewire
    only injects its assets on pages where it renders a component, so without
    this line Alpine is absent and any interactive `<x-ui::*>` renders inert,
    silently. See docs/12, "the thing most likely to be skipped".
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name')) — {{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">

    @fonts
    @livewireScripts

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>

<body class="min-h-full bg-brand-100 font-sans text-ink-800 antialiased">
    <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-12">
        <a href="{{ url('/') }}"
            class="mb-8 text-center font-display text-2xl font-bold tracking-tight text-brand-700">
            {{ config('app.name') }}
        </a>

        <div class="rounded-base border border-default bg-neutral-primary-soft p-6 shadow-card sm:p-8">
            <h1 class="mb-6 font-display text-xl font-semibold text-heading">@yield('title')</h1>

            @if (session('status'))
                <x-ui::alert variant="success">{{ session('status') }}</x-ui::alert>
            @endif

            {{--
                The error summary. Every field below also renders its own
                message inline, so this repeats them — deliberately: a summary
                at the top of the form is what a screen reader reaches first,
                and it is the only thing a user sees when the failing field is
                below the fold.
            --}}
            @if ($errors->any())
                <x-ui::alert variant="danger">
                    <p class="font-medium">{{ __('Please check the form below.') }}</p>
                    <ul class="mt-1 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-ui::alert>
            @endif

            @yield('content')
        </div>

        <p class="mt-6 text-center text-sm text-ink-500">
            <a href="{{ url('/') }}" class="hover:text-brand-650">{{ __('Back to the site') }}</a>
        </p>
    </main>
</body>

</html>
