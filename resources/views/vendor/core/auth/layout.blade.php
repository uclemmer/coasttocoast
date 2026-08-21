{{--
    laravel-core's auth shell, PUBLISHED AND OWNED (docs/12).

    Deliberately thin. The chrome lives in `components/layouts/auth.blade.php`,
    which the Livewire sign-up page uses too — one layout, so the log-in and
    sign-up screens cannot drift apart. This file exists only to bridge the
    package's `@extends`/`@section` views onto that component layout.

    The package ships these views unstyled and dependency-free precisely so a
    host can do this; the copy stops receiving package updates, which is the
    trade and is recorded in docs/12.
--}}
<x-layouts.auth :title="trim($__env->yieldContent('title'))">
    @yield('content')
</x-layouts.auth>
