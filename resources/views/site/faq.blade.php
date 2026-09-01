{{--
    The FAQ (card 8.2).

    `x-ui::accordion` from `uclemmer/laravel-ui`. Single-open with the first row
    expanded, matching the design; clicking the open row collapses it.

    This was Flowbite's accordion until 2026-08-21. The component did not exist
    when this page was built — it had been struck from the package's roadmap for
    want of a second application wanting one — and this FAQ, with kerdoos's, is
    what put it back. app.css said as much at the time: "laravel-ui has no
    accordion to replace the second, so removing Flowbite is its own change".
    This is that change.

    No styling is passed here. The package emits token names and this app owns
    the sheet those tokens come from (resources/css/vendor/ui/theme.css, doc 12),
    repointed at the handoff's green — so the accordion arrives in the site's
    voice with no class strings at the call site.

    Answers are markdown written in the admin panel, rendered through
    `<x-ui.prose>` — Tailwind's preflight strips list and heading styling, so
    rendered markdown needs explicit typography or it lands as a wall of
    identical lines (doc 10, D-8-b).
--}}
<x-layouts.app :title="__('Frequently asked questions')"
               :description="__('When and where the fair is, how a college registers, what it costs, parking, and what to expect on the night.')">

    <x-site.page-header
        :title="__('Frequently asked questions')"
        :eyebrow="__('Good to know')"
        :crumbs="[__('Home') => route('site.home'), __('FAQ') => null]" />

    <x-site.container class="py-14">
        @if ($items->isEmpty())
            <p class="text-[17px] text-ink-500">{{ __('Nothing here yet.') }}</p>
        @else
            {{-- `level="h2"` because the page title above is the h1 and these
                 questions are the page's top-level sections. The component
                 defaults to h3; getting this wrong costs nothing visually and
                 breaks the heading list a screen reader skims the page with. --}}
            <x-ui::accordion class="max-w-[70ch]">
                @foreach ($items as $item)
                    <x-ui::accordion.item level="h2"
                                          :heading="$item->question"
                                          :open="$loop->first">
                        <x-ui.prose :html="Str::markdown($item->answer)" class="text-[16px] leading-[1.7]" />

                        {{-- The attachment, when the coordinator has uploaded
                             one — the signed W-9 is what this exists for. Not
                             a Storage::url(): the download goes through a route
                             so unpublishing the question withdraws the file
                             (doc 10, D-9-c). --}}
                        @if ($item->hasAttachment())
                            <a href="{{ route('site.faq.download', $item) }}"
                               class="mt-4 inline-flex items-center gap-2 rounded-md border border-brand-200 bg-brand-50 px-4 py-2.5 font-display text-[13px] font-bold uppercase tracking-[0.04em] text-brand-600 transition-colors hover:bg-brand-100">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                     stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16" />
                                </svg>
                                {{ __('Download :file', ['file' => $item->attachmentDownloadName()]) }}
                            </a>
                        @endif
                    </x-ui::accordion.item>
                @endforeach
            </x-ui::accordion>
        @endif
    </x-site.container>
</x-layouts.app>
