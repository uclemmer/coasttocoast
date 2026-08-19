{{--
    The public site's layout (card 8.1).

    Used two ways, which is why it lives at a component path:

      * a static page writes `<x-layouts.app>`;
      * a full-page Livewire component gets it automatically, because
        `config/livewire.php` points `component_layout` here.

    One layout for both, so the chrome cannot drift between them.

    `$slot` is NOT wrapped in a container. The design interleaves full-bleed
    bands (hero, sponsors, interior page header) with contained ones, and the
    prototype achieved that with `margin: 0 calc(50% - 50vw)` inside a
    container. The handoff itself says to prefer moving those sections outside
    the container in Tailwind, so pages wrap their own contained parts in
    `<x-site.container>` and full-bleed sections simply do not.

    Props:
      $title        browser title; the site name is appended
      $description  meta description, for the pages that want one
--}}
@props([
    'title' => null,
    'description' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ? $title.' — '.config('app.name') : config('app.name') }}</title>

    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">

    {{-- @fonts before @vite, so the @font-face rules are parsed before the
         stylesheet that uses them. laravel-vite-plugin downloads the three
         families at build time and writes public/build/fonts-manifest.json;
         nothing links that CSS unless this directive is here, and the failure
         is silent -- the page renders correctly in the fallback stack. --}}
    @fonts

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white font-sans text-ink-800 antialiased">
    {{-- Keyboard and screen-reader users should not have to walk the whole nav
         on every page. --}}
    <a href="#main"
       class="sr-only focus:not-sr-only focus:absolute focus:start-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-brand-600 focus:px-4 focus:py-2 focus:font-display focus:text-sm focus:font-bold focus:uppercase focus:text-white">
        {{ __('Skip to content') }}
    </a>

    <x-site.header />

    <main id="main">
        {{ $slot }}
    </main>

    <x-site.footer />
</body>
</html>
