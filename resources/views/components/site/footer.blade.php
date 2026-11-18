{{--
    The public footer (card 8.1).

    `min-height:72px`, ink-900 on footer-text, 13.5px, the two halves pushed
    apart and vertically centred. It wraps on small screens rather than
    squashing, which the prototype did not have to consider.

    The copyright year range starts at 2007 because the fair does — the live
    site says "2007–2026" (doc 00) — and ends at the current year rather than a
    hard-coded one, so it does not quietly go stale in January.
--}}
<footer class="bg-ink-900 text-footer-text">
    <div class="mx-auto flex min-h-[72px] max-w-site flex-wrap items-center justify-between gap-2 px-6 py-4 text-[13.5px]">
        <p class="m-0">
            &copy; 2007&ndash;{{ now()->year }} {{ config('app.name') }} &middot; {{ __('Chattanooga, Tennessee') }}
        </p>

        {{-- Terms and Privacy, once each is published in /admin (docs/31).
             Unpublished documents have no public page, so no link. --}}
        @if ($legalLinks = \App\Support\LegalLinks::published())
            <nav aria-label="{{ __('Legal') }}" class="flex gap-4">
                @foreach ($legalLinks as $label => $url)
                    <a href="{{ $url }}" class="underline underline-offset-2 hover:text-white">{{ $label }}</a>
                @endforeach
            </nav>
        @endif

        <p class="m-0">{{ __('Powered by Uriah Clemmer') }}</p>
    </div>
</footer>
