{{--
    One legal document: the version in force, or a past one by URL (docs/31).

    The body comes from LegalRenderer, which renders Markdown with raw HTML
    escaped and never through Blade, so it is echoed raw here. The drafts open
    each section with a "> **In plain words:**" blockquote; `.legal-body`
    turns every blockquote into the highlighted summary box, and hides the
    body's own H1 because the page header already carries the title.
--}}
<x-layouts.app :title="$document->title"
               :description="__(':title of the Coast to Coast College Fair.', ['title' => $document->title])">

    <x-site.page-header
        :title="$document->title"
        :eyebrow="__('Version :version · in effect from :date', [
            'version' => $version->version,
            'date' => $version->effective_at?->format('F j, Y'),
        ])"
        :crumbs="[__('Home') => url('/'), __('Legal') => route('legal.index'), $document->title => null]" />

    <x-site.container class="py-14">
        @if ($historic ?? false)
            {{-- Said plainly: somebody reading an old version by URL must not
                 mistake it for the one that binds them today. --}}
            <x-ui.alert variant="warning" class="mb-8 max-w-[72ch]" role="note">
                <strong>{{ __('This is an earlier version, kept for reference.') }}</strong>
                <a class="underline" href="{{ route('legal.show', ['slug' => $document->slug]) }}">{{ __('Read the current version') }}</a>
            </x-ui.alert>
        @endif

        <x-ui.prose :html="$version->render()" class="legal-body max-w-[72ch]
            [&_h1]:hidden
            [&_blockquote]:my-6 [&_blockquote]:rounded-lg [&_blockquote]:border-l-4 [&_blockquote]:border-brand-600
            [&_blockquote]:bg-brand-50 [&_blockquote]:px-5 [&_blockquote]:py-4 [&_blockquote]:text-[16px]
            [&_blockquote_strong]:text-brand-700" />

        <p class="mt-12 text-[15px]">
            <a class="font-semibold text-brand-600 underline underline-offset-2 hover:text-brand-700"
               href="{{ route('legal.history', ['slug' => $document->slug]) }}">{{ __('Version history') }}</a>
        </p>
    </x-site.container>
</x-layouts.app>
