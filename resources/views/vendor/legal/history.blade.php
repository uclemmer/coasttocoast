{{-- A document's version history, in the site's layout (docs/31). --}}
<x-layouts.app :title="__(':title — version history', ['title' => $document->title])">

    <x-site.page-header
        :title="__('Version history')"
        :eyebrow="$document->title"
        :crumbs="[__('Home') => url('/'), __('Legal') => route('legal.index'), $document->title => route('legal.show', ['slug' => $document->slug]), __('History') => null]" />

    <x-site.container class="py-14">
        <p class="max-w-[62ch] text-[17px] leading-[1.7] text-ink-700">
            {{ __('Every version that has been in effect, newest first. Earlier versions are kept exactly as they were.') }}
        </p>

        <ol reversed class="mt-8 grid max-w-[62ch] gap-5">
            @foreach ($versions as $version)
                <li class="border-b border-line pb-5">
                    <a class="font-display text-[17px] font-bold text-brand-600 underline underline-offset-2 hover:text-brand-700"
                       href="{{ route('legal.version', ['slug' => $document->slug, 'version' => $version->version]) }}">
                        {{ __('Version :version', ['version' => $version->version]) }}
                    </a>
                    <span class="text-ink-500">
                        · {{ __('in effect from :date', ['date' => $version->effective_at?->format('F j, Y')]) }}
                        @if ($loop->first)
                            <strong class="text-ink-900">({{ __('current') }})</strong>
                        @endif
                    </span>

                    @if (filled($version->summary))
                        <p class="mt-1.5 text-[15.5px] leading-[1.6] text-ink-700">{{ $version->summary }}</p>
                    @endif
                </li>
            @endforeach
        </ol>
    </x-site.container>
</x-layouts.app>
