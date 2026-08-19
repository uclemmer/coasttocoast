{{--
    Overrides laravel-core's own mail layout so that package email — contact
    receipts and organizer alerts — arrives looking like everything else this
    application sends (doc 07 §1, "one theme, two entry points").

    Laravel resolves `resources/views/vendor/core/...` before the package's own
    copy, so this file needs no registration. Do not add content here: it is a
    shim, and the theme it delegates to is the one place worth editing.
--}}
@props(['title' => null])

<x-emails::layout :title="$title">
    {{ $slot }}
</x-emails::layout>
