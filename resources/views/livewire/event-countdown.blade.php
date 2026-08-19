{{--
    The countdown (card 8.2).

    Ticks in Alpine on a one-second interval rather than through `wire:poll` —
    see the component's docblock for why that matters on a public page. The
    numbers below are rendered server-side so the first paint is already
    correct and a visitor with no JavaScript still sees a true figure.

    `tabular` holds the digits to a fixed width so the row does not twitch as
    the seconds change.
--}}
<section class="py-16 text-center"
         x-data="{
             target: new Date('{{ $target }}').getTime(),
             days: '{{ $units['days'] }}',
             hours: '{{ $units['hours'] }}',
             minutes: '{{ $units['minutes'] }}',
             seconds: '{{ $units['seconds'] }}',
             done: {{ $hasHappened ? 'true' : 'false' }},
             tick() {
                 const remaining = Math.max(0, this.target - Date.now());
                 this.done = remaining <= 0;
                 const pad = (n) => String(n).padStart(2, '0');
                 this.days = String(Math.floor(remaining / 86400000));
                 this.hours = pad(Math.floor(remaining / 3600000) % 24);
                 this.minutes = pad(Math.floor(remaining / 60000) % 60);
                 this.seconds = pad(Math.floor(remaining / 1000) % 60);
             },
         }"
         x-init="tick(); setInterval(() => tick(), 1000)">

    <p class="mb-6 font-script text-[30px] font-bold text-brand-600">{{ $heading }}</p>

    @unless ($hasHappened)
        <div class="flex flex-wrap justify-center gap-[clamp(20px,5vw,64px)]" x-show="! done">
            @foreach ([
                ['days', __('Days'), true],
                ['hours', __('Hours'), false],
                ['minutes', __('Minutes'), false],
                ['seconds', __('Seconds'), false],
            ] as [$key, $label, $isBrand])
                <div>
                    <p @class([
                        'tabular m-0 mb-2 font-display text-[clamp(40px,4.4vw,60px)] font-extrabold leading-none',
                        'text-brand-600' => $isBrand,
                        'text-ink-900' => ! $isBrand,
                    ]) x-text="{{ $key }}">{{ $units[$key] }}</p>

                    <p class="m-0 text-[12.5px] font-semibold uppercase tracking-[0.12em] text-ink-400">
                        {{ $label }}
                    </p>
                </div>
            @endforeach
        </div>
    @endunless
</section>
