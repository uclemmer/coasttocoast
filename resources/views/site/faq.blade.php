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
                    </x-ui::accordion.item>
                @endforeach
            </x-ui::accordion>
        @endif
    </x-site.container>
</x-layouts.app>
