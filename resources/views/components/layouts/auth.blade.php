{{--
    The shell for every unauthenticated account screen — log in, sign up, forgot
    password, reset password.

    ONE LAYOUT, TWO CALLERS. Livewire full-page components reach it through
    `#[Layout('components.layouts.auth')]`; laravel-core's published Blade views
    reach it through `core::auth.layout`, which is a thin wrapper around this
    file. Keeping the chrome in one place is the point — a sign-up page that
    does not match the log-in page beside it looks like a different site.

    It deliberately does NOT extend the public layout. An auth screen wants no
    navigation, no footer links and nothing else to click; the page has one job.
    What it does share is the site's wordmark, typography and green, because a
    login page that looks like a different product is how a phishing page looks.

    `@fonts` BEFORE `@vite` — see .ai/rules/layouts.md. Without `@fonts` nothing
    reaches the page and every screen silently renders in the fallback system
    stack: the build succeeds, the tests pass. It shipped that way once.

    `@livewireScripts` is explicit because two of the callers are plain Blade.
    Livewire only injects its assets on pages where it renders a component, so
    without this Alpine is absent on those and any interactive `<x-ui::*>`
    renders inert, with no error. See docs/12.
--}}
@props(['title' => null, 'width' => 'md'])

@php
    $widths = [
        'md' => 'max-w-md',   // log in, forgot password — a handful of fields
        'xl' => 'max-w-xl',   // sign up — two sections and an organization picker
    ];
@endphp

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

<body class="min-h-full bg-brand-100 font-sans text-ink-800 antialiased">
    <main class="mx-auto flex min-h-screen w-full flex-col justify-center px-4 py-12 {{ $widths[$width] ?? $widths['md'] }}">
        <a href="{{ url('/') }}"
            class="mb-8 text-center font-display text-2xl font-bold tracking-tight text-brand-700">
            {{ config('app.name') }}
        </a>

        <div class="rounded-base border border-default bg-neutral-primary-soft p-6 shadow-card sm:p-8">
            @if ($title)
                <h1 class="mb-6 font-display text-xl font-semibold text-heading">{{ $title }}</h1>
            @endif

            @if (session('status'))
                <x-ui::alert variant="success">{{ session('status') }}</x-ui::alert>
            @endif

            {{--
                The error summary. Fields render their own messages inline too,
                and this repeats them deliberately: a summary at the top is what
                a screen reader reaches first, and it is the only thing a user
                sees when the failing field is below the fold.
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

            {{ $slot }}
        </div>

        <p class="mt-6 text-center text-sm text-ink-500">
            <a href="{{ url('/') }}" class="hover:text-brand-650">{{ __('Back to the site') }}</a>
        </p>
    </main>
</body>

</html>
