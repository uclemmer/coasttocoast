{{-- The legal documents in force, in the site's layout (docs/31). --}}
<x-layouts.app :title="__('Legal')">

    <x-site.page-header
        :title="__('Legal')"
        :crumbs="[__('Home') => url('/'), __('Legal') => null]" />

    <x-site.container class="py-14">
        @forelse ($documents as $document)
            <h2 class="mb-4 font-display text-[22px] font-bold">
                <a class="text-brand-600 underline underline-offset-2 hover:text-brand-700"
                   href="{{ route('legal.show', ['slug' => $document->slug]) }}">{{ $document->title }}</a>
            </h2>
        @empty
            <p class="text-[17px] text-ink-700">{{ __('Nothing published yet.') }}</p>
        @endforelse
    </x-site.container>
</x-layouts.app>
