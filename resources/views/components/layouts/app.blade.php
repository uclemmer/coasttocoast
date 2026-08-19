{{--
    The public site's layout.

    ============================================================================
    PLACEHOLDER. This is plumbing, not design.
    ============================================================================

    It exists so the Vite pipeline and Livewire's page layout resolve today
    (config/livewire.php points `component_layout` here). There is deliberately
    no navigation, no header, no footer and no colour: the design handoff from
    Claude Design replaces this file wholesale, and inventing a look now would
    only be something to unpick.

    What must survive whatever replaces it:

      * `@vite` for both entrypoints — `app.css` carries the Flowbite plugin,
        `app.js` carries Flowbite's behaviour;
      * `{{ $slot }}`, which is what makes this usable both as
        `<x-layouts.app>` from a plain Blade page and as Livewire's page layout;
      * `$title`, so a page can set its own without a second layout.

    Livewire injects its own script and Alpine with it, so neither is included
    here.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    {{ $slot }}
</body>
</html>
