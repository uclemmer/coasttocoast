{{--
    The landing page (card 8.2), from the Claude Design handoff.

    Its job is one thing: convert a college representative to register. Every
    section below is in the design's order, and the copy is the design's, which
    the handoff marks final.

    Full-bleed bands (hero, sponsors) sit outside `<x-site.container>` rather
    than using the prototype's `margin: 0 calc(50% - 50vw)` trick — the handoff
    itself recommends that in Tailwind.
--}}
<x-layouts.app
    :description="__('More than one hundred colleges and universities meet Chattanooga-area sophomores, juniors and their parents in a single evening. Registration is open to college representatives.')">

    {{-- ── Hero ────────────────────────────────────────────────────────────
         Deliberately NO dark overlay: the client wants the painted cityscape
         colours to stay vivid, so legibility comes entirely from the layered
         text shadows in `text-over-photo`. Removing them is not a cosmetic
         change. --}}
    <section class="relative grid min-h-[min(78vh,640px)] content-center justify-items-center overflow-hidden px-[clamp(20px,5vw,64px)] py-[clamp(48px,7vw,88px)] text-center">
        <img src="{{ asset('images/cityscape.jpg') }}"
             alt="{{ __('Aerial view of Chattanooga\'s riverfront and bridges') }}"
             fetchpriority="high"
             class="absolute inset-0 h-full w-full object-cover object-[center_40%] saturate-[1.15]">

        <div class="relative max-w-[820px]">
            @if ($fair)
                <x-ui.eyebrow tone="hero" class="text-[clamp(26px,3vw,36px)]">
                    {{ $fair->starts_at->format('l, F j, Y') }}
                    &middot; {{ $fair->starts_at->format('g:i') }}&ndash;{{ $fair->ends_at->format('g:i a') }}
                    &middot; {{ $fair->venue_name }}
                </x-ui.eyebrow>
            @endif

            <h1 class="mx-auto mt-3 max-w-[20ch] font-display text-[clamp(40px,5.4vw,68px)] font-extrabold uppercase leading-[1.1] tracking-[-0.01em] text-white text-over-photo">
                {{ __('Bring your college to Chattanooga') }}
            </h1>

            {{-- Editable copy, not a hard-coded string. The block is seeded
                 with the design's words, but the coordinator can fix a typo
                 without a deploy — which is the whole point of laravel-core's
                 Content module (doc 10, D-5.2-a). --}}
            @if ($heroBody)
                <div class="mx-auto mt-6 max-w-[62ch] text-[18px] leading-[1.65] text-white text-over-photo-sm [&_p]:mb-3 [&_p:last-child]:mb-0">
                    {!! $heroBody !!}
                </div>
            @endif

            <div class="mt-8 flex flex-wrap justify-center gap-3.5">
                <x-ui.button variant="on-photo-solid" href="#register">
                    {{ __('Register your college') }}
                </x-ui.button>

                <x-ui.button variant="on-photo" href="#venue">
                    {{ __('Venue & map') }}
                </x-ui.button>
            </div>
        </div>
    </section>

    {{-- ── Countdown ──────────────────────────────────────────────────────── --}}
    @if ($fair)
        <x-site.container>
            <livewire:event-countdown :event="$fair" />
        </x-site.container>
    @endif

    <x-site.container>
        {{-- ── Registration ───────────────────────────────────────────────── --}}
        <section id="register"
                 class="mb-20 grid gap-12 rounded-[14px] bg-brand-600 p-[clamp(36px,5vw,64px)] text-white lg:grid-cols-[minmax(0,6fr)_minmax(0,5fr)] lg:items-start lg:gap-x-[clamp(32px,6vw,90px)]">
            <div>
                <x-ui.eyebrow tone="light" class="mb-2.5 text-[28px]">
                    {{ __('For college representatives') }}
                </x-ui.eyebrow>

                <x-ui.section-heading tone="light">
                    {{ __('What registration includes') }}
                </x-ui.section-heading>

                @if ($registrationIntro)
                    <div class="mt-4.5 max-w-[52ch] text-[16px] leading-[1.7] text-brand-400 [&_p]:mb-3 [&_p:last-child]:mb-0">
                        {!! $registrationIntro !!}
                    </div>
                @endif

                @php
                    /*
                     * Four states, not two. A site with no published fair at
                     * all is a real one — between years, or on a fresh
                     * install — and it must not build a URL for an event that
                     * does not exist.
                     */
                    $isOpen = $fair?->isRegistrationOpen() ?? false;
                    $notYet = $fair?->registrationNotYetOpen() ?? false;

                    $cta = match (true) {
                        $isOpen => [route('register'), __('Begin registration')],
                        $fair !== null => [route('site.event', $fair), __('Join the mailing list')],
                        default => [route('site.contact'), __('Ask us about next year')],
                    };
                @endphp

                @if ($fair)
                    {{-- The fee.
                         Not in the design, added deliberately: doc 00 lists
                         "pricing, deadlines, and what the fee includes are
                         scattered or missing entirely" as a weakness of the
                         current site, and this panel is where a representative
                         decides. Additive — it displaces none of the design's
                         copy. --}}
                    <p class="mt-4 text-[16px] leading-[1.7] text-white">
                        <span class="font-display text-[22px] font-bold">{{ $fair->feeSummary() }}</span>
                        <span class="text-brand-400">{{ __('per institution') }}</span>
                    </p>
                @endif

                {{-- Date-driven, not hard-coded: the fair's own window decides
                     which sentence a visitor reads. --}}
                <p class="mt-3.5 text-[15px] leading-[1.6] text-brand-300">
                    @if ($isOpen)
                        {{ __('Registration for the :year fair is open now', ['year' => $fair->starts_at->year]) }}@if ($fair->registration_closes_at){{ __(' and closes :date.', ['date' => $fair->registration_closes_at->format('l, F j, Y')]) }}@else{{ '.' }}@endif
                    @elseif ($notYet)
                        {{ __('Registration for the :year fair opens on :date.', [
                            'year' => $fair->starts_at->year,
                            'date' => $fair->registration_opens_at->format('l, F j, Y'),
                        ]) }}
                    @elseif ($fair)
                        {{ __('Registration for this year\'s fair has closed. Join the mailing list to be notified when next spring\'s registration opens.') }}
                    @else
                        {{ __('Next spring\'s fair has not been announced yet. Write to us and we will let you know as soon as it is.') }}
                    @endif
                </p>

                <x-ui.button variant="on-green" class="mt-6" :href="$cta[0]">
                    {{ $cta[1] }}
                </x-ui.button>
            </div>

            {{-- The Convention Center's parking and Carter Street drop-off lines
                 left with the move to The Westin (owner, 2026-11-10; docs/29).
                 They come back when the Westin's arrangements are known. --}}
            @php
                $included = [
                    [__('Exhibit table on the fair floor'), __('6:30–8:00 p.m., alongside 100+ peer institutions.')],
                    [__('Pre-fair dinner reception'), __('5:00–6:00 p.m. in downtown Chattanooga, with local high school counselors.')],
                ];
            @endphp
            <div class="grid">
                @foreach ($included as $index => [$heading, $body])
                    <div @class([
                        'py-4',
                        'border-b border-white/25' => $index < count($included) - 1,
                    ])>
                        <h3 class="font-display text-[17px] font-bold">{{ $heading }}</h3>
                        <p class="mt-1.5 text-[14.5px] leading-[1.6] text-brand-400">{{ $body }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ── Venue ──────────────────────────────────────────────────────── --}}
        @php
            // Fallbacks for a site with no published fair yet: the 2027 venue.
            $venueName = $fair?->venue_name ?? __('The Westin Chattanooga');
            $venueAddress = $fair?->venue_address ?? "801 Pine Street\nChattanooga, TN 37402";
            $venueQuery = $venueName.', '.preg_replace('/\s*\R\s*/', ', ', trim($venueAddress));
            $mapEmbed = 'https://www.google.com/maps?q='.urlencode($venueQuery).'&output=embed';
            $mapLink = 'https://www.google.com/maps/search/?api=1&query='.urlencode($venueQuery);
        @endphp
        <section id="venue"
                 class="mb-20 grid gap-10 lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)] lg:items-center lg:gap-x-[clamp(32px,6vw,90px)]">
            <div>
                <x-ui.eyebrow class="mb-2.5 text-[28px]">{{ __('Venue') }}</x-ui.eyebrow>

                <x-ui.section-heading class="!text-[clamp(26px,3vw,34px)]">
                    {{ $venueName }}
                </x-ui.section-heading>

                <div class="mt-6 grid gap-4">
                    @foreach ([
                        [__('Address'), $venueAddress],
                    ] as [$label, $value])
                        <div>
                            <p class="mb-1 text-[12.5px] font-semibold uppercase tracking-[0.12em] text-ink-400">{{ $label }}</p>
                            <p class="m-0 whitespace-pre-line text-[16px] leading-[1.6]">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="grid gap-3">
                {{-- Loads with the page, pinned at the venue's own address
                     (owner, 2026-11-10: "we do not need to hide it for
                     privacy"). This reverses doc 10 D-9-f, which held the
                     iframe back until a click. The `?q=…&output=embed` form
                     needs no API key and follows the venue fields in /staff,
                     so a new venue moves the pin without a deploy. --}}
                <div class="relative aspect-[4/3] w-full overflow-hidden rounded-xl bg-brand-50 shadow-map">
                    <iframe src="{{ $mapEmbed }}"
                            title="{{ __('Map — :venue', ['venue' => $venueName]) }}"
                            loading="lazy"
                            allowfullscreen
                            referrerpolicy="no-referrer-when-downgrade"
                            class="block h-full w-full border-0"></iframe>
                </div>

                <a href="{{ $mapLink }}" target="_blank" rel="noopener noreferrer"
                   class="justify-self-start text-[14px] font-semibold text-brand-600 underline underline-offset-2 hover:text-brand-700">
                    {{ __('Open :venue in Google Maps', ['venue' => $venueName]) }}
                </a>
            </div>
        </section>
    </x-site.container>

    {{-- ── Sponsors ───────────────────────────────────────────────────────── --}}
    @if ($sponsors->isNotEmpty())
        <section id="sponsors" aria-label="{{ __('Sponsors') }}" class="mb-20 bg-brand-600 py-12">
            <x-site.container>
                <x-ui.eyebrow tone="light" class="text-center text-[28px]">
                    {{ __('Sponsored by') }}
                </x-ui.eyebrow>

                <div class="mt-7 flex flex-wrap items-center justify-center gap-[clamp(28px,5vw,72px)]">
                    @foreach ($sponsors as $sponsor)
                        <figure class="m-0 grid justify-items-center gap-2.5">
                            @if ($sponsor->logo_path)
                                <img src="{{ Storage::disk('public')->url($sponsor->logo_path) }}"
                                     alt="{{ $sponsor->name }}"
                                     loading="lazy"
                                     class="h-20 w-[150px] rounded-lg bg-white object-contain p-2">
                                <figcaption class="font-display text-[12.5px] font-semibold uppercase tracking-[0.08em] text-brand-400">
                                    {{ $sponsor->name }}
                                </figcaption>
                            @else
                                {{-- TODO-OWNER: the four school logos are not
                                     supplied yet (handoff, "Assets"). The tile
                                     holds its place with the school's name, and
                                     carries the caption itself -- printing the
                                     name twice, once as the mark and once
                                     beneath it, reads as a mistake. --}}
                                <figcaption class="flex h-20 w-[150px] items-center justify-center rounded-lg bg-white px-3 text-center font-display text-[12.5px] font-bold uppercase leading-tight text-brand-600">
                                    {{ $sponsor->name }}
                                </figcaption>
                            @endif
                        </figure>
                    @endforeach
                </div>
            </x-site.container>
        </section>
    @endif

    {{-- ── Contact ────────────────────────────────────────────────────────── --}}
    <x-site.container>
        <section id="contact" class="mb-20 grid gap-12 md:grid-cols-2 md:gap-x-[clamp(32px,6vw,100px)]">
            <div>
                <x-ui.eyebrow class="mb-2.5 text-[28px]">{{ __('Contact') }}</x-ui.eyebrow>

                <x-ui.section-heading class="!text-[clamp(26px,3vw,34px)]">
                    {{ __('Write to the fair') }}
                </x-ui.section-heading>

                <p class="mt-4.5 max-w-[48ch] text-[16px] leading-[1.7] text-ink-600">
                    {{ __('Questions about registration, fees, or the evening itself — use the form and we will reply directly.') }}
                </p>

                <p class="mt-4.5 text-[16px] leading-[1.7] text-ink-600">
                    {{ __('Checks by post:') }}<br>
                    {{ config('app.name') }}<br>
                    @if (config('fair.contact.name'))
                        {{ __('ATTN: :name', ['name' => config('fair.contact.name')]) }}<br>
                    @endif
                    {{ config('fair.contact.address_line1') }}<br>
                    @if (config('fair.contact.address_line2'))
                        {{ config('fair.contact.address_line2') }}<br>
                    @endif
                    {{ config('fair.contact.city') }}, {{ config('fair.contact.state') }} {{ config('fair.contact.postal_code') }}
                </p>
            </div>

            <livewire:contact-form />
        </section>
    </x-site.container>
</x-layouts.app>
