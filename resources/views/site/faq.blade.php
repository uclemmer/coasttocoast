{{--
    The FAQ (card 8.2).

    Flowbite's accordion, so the open/close behaviour is `data-*` attributes
    rather than bespoke JavaScript. Single-open with the first row expanded,
    matching the design; clicking the open row collapses it.

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
            <div id="faq-accordion"
                 data-accordion="collapse"
                 data-active-classes="text-ink-900"
                 data-inactive-classes="text-ink-900"
                 class="max-w-[70ch] overflow-hidden rounded-[10px] border border-line">
                @foreach ($items as $index => $item)
                    <h2 id="faq-heading-{{ $item->getKey() }}"
                        @class(['border-b border-line-soft' => ! $loop->last])>
                        <button type="button"
                                data-accordion-target="#faq-body-{{ $item->getKey() }}"
                                aria-expanded="{{ $index === 0 ? 'true' : 'false' }}"
                                aria-controls="faq-body-{{ $item->getKey() }}"
                                class="flex w-full items-center justify-between gap-4 px-[18px] py-4 text-start font-display text-[16px] font-bold text-ink-900 transition-colors hover:bg-brand-50">
                            <span>{{ $item->question }}</span>
                            <svg data-accordion-icon class="h-5 w-5 shrink-0 rotate-180 text-brand-600 transition-transform"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </h2>

                    <div id="faq-body-{{ $item->getKey() }}"
                         class="{{ $index === 0 ? '' : 'hidden' }}"
                         aria-labelledby="faq-heading-{{ $item->getKey() }}">
                        <div @class(['px-[18px] pb-[18px]', 'border-b border-line-soft' => ! $loop->last])>
                            <x-ui.prose :html="Str::markdown($item->answer)" class="text-[16px] leading-[1.7]" />
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-site.container>
</x-layouts.app>
