<x-layouts.app :title="__('Sponsors')"
               :description="__('The fair is organised and underwritten by the college counseling offices of four Chattanooga preparatory schools.')">

    <x-site.page-header
        :title="__('Sponsors')"
        :eyebrow="__('Sponsored by')"
        :crumbs="[__('Home') => route('site.home'), __('Sponsors') => null]" />

    <x-site.container class="py-14">
        @if ($intro)
            <x-ui.prose :html="$intro" class="mb-10" />
        @endif

        <div class="grid gap-6 sm:grid-cols-2">
            @foreach ($sponsors as $sponsor)
                <div class="rounded-xl border border-line bg-white p-6 shadow-card">
                    <div class="flex items-start gap-5">
                        @if ($sponsor->logo_path)
                            <img src="{{ Storage::disk('public')->url($sponsor->logo_path) }}"
                                 alt="{{ $sponsor->name }}"
                                 loading="lazy"
                                 class="h-16 w-[120px] shrink-0 object-contain">
                        @endif

                        <div>
                            <h2 class="font-display text-[19px] font-bold text-ink-900">
                                @if ($sponsor->website)
                                    <a href="{{ $sponsor->website }}" target="_blank" rel="noopener noreferrer"
                                       class="text-ink-900 underline-offset-[3px] hover:text-brand-600 hover:underline">{{ $sponsor->name }}</a>
                                @else
                                    {{ $sponsor->name }}
                                @endif
                            </h2>

                            @if ($sponsor->staff->isNotEmpty())
                                <ul class="mt-3 space-y-1 text-[15.5px] text-ink-500">
                                    @foreach ($sponsor->staff as $person)
                                        <li>
                                            <span class="font-semibold text-ink-700">{{ $person->name }}</span>@if ($person->title)<span class="text-ink-500"> — {{ $person->title }}</span>@endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-site.container>
</x-layouts.app>
