{{--
    The public navigation (card 8.1).

    Design notes worth keeping:
      * the wordmark is 48px tall and links home;
      * links are 14.5px / 600, ink-800, hovering to brand green;
      * the right-hand group is pushed over with `ms-auto` and pairs a plain
        "Log in" link with a solid Register button.

    Two deviations from the prototype, both deliberate:

      1. The prototype's nav uses same-page anchors (`#about`, `#faq`). Those
         are real routes here — the handoff itself flags the anchors as having
         no destination and says to wire them up.
      2. "Representatives" points at the public roster, not at registration.
         That is what it means on the live site and what R1.3 describes, and
         the Register button already carries the conversion path. Owner
         confirmed 2026-08-19.

    The mobile drawer is ours: the handoff says mobile was not explicitly
    designed and assumes a hamburger. It used Flowbite's `data-collapse-toggle`
    until 2026-08-21 and is inline Alpine now — still no bespoke JavaScript,
    and Livewire brings the Alpine.

    The drawer is a SEPARATE element from the desktop links, which is what makes
    `x-show` safe here: it writes an inline `display: none`, and an inline style
    would beat a `lg:` variant if the two shared one element. They do not — the
    desktop row is `hidden lg:flex` and the drawer is `lg:hidden`.
--}}
@php
    $links = [
        ['label' => __('About'), 'route' => 'site.about'],
        ['label' => __('Representatives'), 'route' => 'site.representatives'],
        ['label' => __('Last year'), 'route' => 'site.last-year'],
        ['label' => __('Sponsors'), 'route' => 'site.sponsors'],
        ['label' => __('FAQ'), 'route' => 'site.faq'],
        ['label' => __('Contact'), 'route' => 'site.contact'],
    ];
@endphp

<nav x-data="{ menu: false }" x-on:keydown.escape.window="menu = false"
     class="border-b border-line bg-white">
    <div class="mx-auto flex max-w-site items-center gap-7 px-6 py-3.5">
        <a href="{{ route('site.home') }}" class="flex items-center" aria-label="{{ config('app.name') }}">
            <img src="{{ asset('images/wordmark.jpg') }}"
                 alt="{{ config('app.name') }}"
                 class="block h-12 w-auto">
        </a>

        {{-- Desktop links. --}}
        <div class="hidden items-center gap-7 lg:flex">
            @foreach ($links as $link)
                <a href="{{ route($link['route']) }}"
                   @class([
                       'text-[14.5px] font-semibold transition-colors hover:text-brand-600',
                       'text-brand-600' => request()->routeIs($link['route']),
                       'text-ink-800' => ! request()->routeIs($link['route']),
                   ])
                   @if (request()->routeIs($link['route'])) aria-current="page" @endif>
                    {{ $link['label'] }}
                </a>
            @endforeach
        </div>

        <div class="ms-auto flex items-center gap-3.5">
            <a href="{{ route('core.login') }}"
               class="hidden whitespace-nowrap text-[14.5px] font-semibold text-ink-800 transition-colors hover:text-brand-600 sm:inline">
                {{ __('Log in') }}
            </a>

            <a href="{{ route('register') }}"
               class="whitespace-nowrap rounded-md bg-brand-600 px-[22px] py-3 font-display text-[13.5px] font-bold uppercase tracking-[0.04em] text-white transition-colors hover:bg-brand-700">
                {{ __('Register') }}
            </a>

            <button type="button"
                    x-on:click="menu = ! menu"
                    x-bind:aria-expanded="menu"
                    aria-controls="site-nav"
                    class="inline-flex items-center rounded-md p-2 text-ink-600 hover:bg-brand-50 hover:text-brand-600 lg:hidden">
                <span class="sr-only">{{ __('Open the menu') }}</span>
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>
    </div>

    {{-- Mobile drawer. `x-cloak` keeps it closed on first paint, before
         Alpine has initialised; the rule for it is in resources/css/app.css. --}}
    <div id="site-nav" x-show="menu" x-cloak class="border-t border-line lg:hidden">
        <div class="mx-auto max-w-site space-y-1 px-6 py-3">
            @foreach ($links as $link)
                <a href="{{ route($link['route']) }}"
                   @class([
                       'block rounded-md px-2 py-2.5 text-[15px] font-semibold transition-colors hover:bg-brand-50',
                       'text-brand-600' => request()->routeIs($link['route']),
                       'text-ink-800' => ! request()->routeIs($link['route']),
                   ])>
                    {{ $link['label'] }}
                </a>
            @endforeach

            <a href="{{ route('core.login') }}"
               class="block rounded-md px-2 py-2.5 text-[15px] font-semibold text-ink-800 transition-colors hover:bg-brand-50 sm:hidden">
                {{ __('Log in') }}
            </a>
        </div>
    </div>
</nav>
