{{--
    The interior page header (design handoff, "Interior Page").

    A full-bleed brand-tinted band carrying a breadcrumb, the Caveat eyebrow,
    the page title and an intro. Every public page except the landing page
    opens with one, which is what makes them read as a set.

    Props:
      $title    the H1
      $eyebrow  the script line above it
      $intro    optional lead paragraph, 62ch
      $crumbs   ['Label' => url|null] — a null url renders as the current page
--}}
@props([
    'title',
    'eyebrow' => null,
    'intro' => null,
    'crumbs' => [],
])

<div class="border-b border-line bg-brand-100">
    <x-site.container class="py-9">
        @if ($crumbs !== [])
            <nav aria-label="{{ __('Breadcrumb') }}" class="mb-3 text-[13.5px] text-ink-400">
                <ol class="flex flex-wrap items-center gap-2">
                    @foreach ($crumbs as $label => $url)
                        <li class="flex items-center gap-2">
                            @if ($url)
                                <a href="{{ $url }}" class="transition-colors hover:text-brand-600">{{ $label }}</a>
                                <span aria-hidden="true">/</span>
                            @else
                                <span class="font-semibold text-ink-600" aria-current="page">{{ $label }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif

        @if ($eyebrow)
            <x-ui.eyebrow class="mb-2.5 text-[28px]">{{ $eyebrow }}</x-ui.eyebrow>
        @endif

        <h1 class="font-display text-[clamp(30px,3.6vw,44px)] font-extrabold uppercase leading-tight tracking-[-0.01em] text-ink-900">
            {{ $title }}
        </h1>

        @if ($intro)
            <p class="mt-4 max-w-[62ch] text-[17px] leading-[1.65] text-ink-700">{{ $intro }}</p>
        @endif
    </x-site.container>
</div>
