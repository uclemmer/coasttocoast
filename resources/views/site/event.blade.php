{{--
    One fair's public page (card 8.4).

    The call to action is state-aware, and that is the point of the page. The
    current site shows "Registration is currently closed" for most of the year
    with nowhere to go from there (doc 00), which loses every college that finds
    the site out of season. Three states, three destinations:

      open         → register
      not yet open → the date, so they can diarise it
      closed       → the interest form, so we can tell them next time
--}}
<x-layouts.app :title="$fair->name"
               :description="__(':fair — :date at :venue.', [
                   'fair' => $fair->name,
                   'date' => $fair->starts_at->format('l, F j, Y'),
                   'venue' => $fair->venue_name,
               ])">

    <x-site.page-header
        :title="$fair->name"
        :eyebrow="$fair->starts_at->format('l, F j, Y')"
        :crumbs="[__('Home') => route('site.home'), $fair->name => null]" />

    <x-site.container class="py-14">
        <div class="grid gap-12 lg:grid-cols-[minmax(0,6fr)_minmax(0,5fr)] lg:items-start lg:gap-x-[clamp(32px,6vw,90px)]">
            <div>
                <x-ui.section-heading class="!text-[clamp(22px,2.4vw,28px)]">
                    {{ __('The fair') }}
                </x-ui.section-heading>

                <div class="mt-6 grid gap-4">
                    <div>
                        <p class="mb-1 text-[12.5px] font-semibold uppercase tracking-[0.12em] text-ink-400">{{ __('When') }}</p>
                        <p class="m-0 text-[16px] leading-[1.6]">
                            {{ $fair->starts_at->format('l, F j, Y') }}<br>
                            {{ $fair->starts_at->format('g:i a') }}&ndash;{{ $fair->ends_at->format('g:i a') }}
                            @if ($fair->reception_starts_at)
                                <br>
                                <span class="text-ink-500">
                                    {{ __('Counselor reception from :time', ['time' => $fair->reception_starts_at->format('g:i a')]) }}
                                </span>
                            @endif
                        </p>
                    </div>

                    <div>
                        <p class="mb-1 text-[12.5px] font-semibold uppercase tracking-[0.12em] text-ink-400">{{ __('Where') }}</p>
                        <p class="m-0 whitespace-pre-line text-[16px] leading-[1.6]">{{ $fair->venue_name }}
{{ $fair->venue_address }}</p>
                    </div>

                    <div>
                        <p class="mb-1 text-[12.5px] font-semibold uppercase tracking-[0.12em] text-ink-400">{{ __('Registration') }}</p>
                        <p class="m-0 text-[16px] leading-[1.6]">
                            {{ \App\Support\Money::format($fair->price_cents) }}
                            <span class="text-ink-500">{{ __('per institution') }}</span>
                        </p>
                        <p class="mt-1 text-[15px] text-ink-500">{{ __('Free for students and families. No registration needed.') }}</p>
                    </div>
                </div>
            </div>

            <div>
                @if ($fair->isRegistrationOpen())
                    <div class="rounded-xl border border-line bg-white p-6 shadow-card">
                        <h2 class="font-display text-[19px] font-bold uppercase text-ink-900">{{ __('Registration is open') }}</h2>

                        <p class="mt-2 text-[16px] leading-[1.6] text-ink-600">
                            @if ($fair->registration_closes_at)
                                {{ __('Registration closes on :date.', ['date' => $fair->registration_closes_at->format('l, F j, Y')]) }}
                            @else
                                {{ __('There is no closing date yet.') }}
                            @endif
                        </p>

                        <x-ui.button class="mt-5" :href="route('filament.rep.auth.register')">
                            {{ __('Register your institution') }}
                        </x-ui.button>
                    </div>
                @elseif ($fair->registrationNotYetOpen())
                    <div class="rounded-xl border border-line bg-white p-6 shadow-card">
                        <h2 class="font-display text-[19px] font-bold uppercase text-ink-900">{{ __('Registration is not open yet') }}</h2>

                        <p class="mt-2 text-[16px] leading-[1.6] text-ink-600">
                            {{ __('Registration opens on :date. Leave us your email and we will tell you the moment it does.', [
                                'date' => $fair->registration_opens_at->format('l, F j, Y'),
                            ]) }}
                        </p>

                        <div class="mt-5">
                            <livewire:event-interest :event="$fair" />
                        </div>
                    </div>
                @else
                    <div class="rounded-xl border border-line bg-white p-6 shadow-card">
                        <h2 class="font-display text-[19px] font-bold uppercase text-ink-900">{{ __('Registration has closed') }}</h2>

                        <p class="mt-2 text-[16px] leading-[1.6] text-ink-600">
                            {{ __('Leave us your email and we will tell you the moment it opens again.') }}
                        </p>

                        <div class="mt-5">
                            <livewire:event-interest :event="$fair" />
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </x-site.container>
</x-layouts.app>
