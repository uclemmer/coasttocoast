{{--
    The public roster, shared by Representatives and Last Year (card 8.3).

    Table styling is the handoff's: bordered wrapper at radius 10, a brand-100
    header row in uppercase Montserrat, rows divided by `line-soft`, the first
    cell weighted.

    The rows render server-side on first paint. That is not incidental — this
    list is the page's whole content, and it has to be readable by a search
    engine and by anyone whose JavaScript did not run (doc 10, D-5.3-b).
    Livewire only takes over once the visitor searches or pages.
--}}
<div>
    {{-- The intro is rendered below rather than passed in: it comes from an
         editable content block and may contain markup, which the header's
         plain-text `intro` prop would escape. --}}
    <x-site.page-header :title="$title" :eyebrow="$eyebrow" :crumbs="$crumbs" />

    <x-site.container class="py-14">
        @if ($intro)
            <x-ui.prose :html="$intro" class="mb-8" />
        @endif

        @if ($fair)
            <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                <p class="font-display text-[17px] font-bold uppercase tracking-[0.04em] text-ink-900">
                    {{ $fair->name }}
                </p>

                <label class="w-full max-w-xs">
                    <span class="sr-only">{{ __('Search institutions') }}</span>
                    <input type="search"
                           wire:model.live.debounce.300ms="search"
                           placeholder="{{ __('Search institutions') }}"
                           class="w-full rounded-lg border-[1.5px] border-field-border bg-field-bg px-3.5 py-3 text-[15.5px] text-ink-800 placeholder:text-placeholder focus:border-brand-600 focus:outline-none focus:ring-0">
                </label>
            </div>
        @endif

        @if ($roster->isEmpty())
            <div class="rounded-[10px] border border-line bg-brand-100 px-6 py-12 text-center">
                <p class="font-display text-[17px] font-bold uppercase text-ink-900">
                    {{ filled($search) ? __('Nothing matches that search') : $emptyHeading }}
                </p>
                <p class="mt-2 text-[15.5px] text-ink-500">
                    {{ filled($search) ? __('Try part of the institution\'s name.') : $emptyBody }}
                </p>
            </div>
        @else
            <div class="overflow-hidden rounded-[10px] border border-line">
                <table class="w-full border-collapse text-start">
                    <caption class="sr-only">
                        {{ __('Institutions attending :fair', ['fair' => $fair?->name]) }}
                    </caption>
                    <thead>
                        <tr class="bg-brand-100">
                            <th scope="col" class="w-[92px] px-4 py-3 text-start font-display text-[12.5px] font-bold uppercase tracking-[0.08em] text-ink-600">
                                {{ __('Logo') }}
                            </th>
                            <th scope="col" class="px-4 py-3 text-start font-display text-[12.5px] font-bold uppercase tracking-[0.08em] text-ink-600">
                                {{ __('Institution') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($roster as $registration)
                            @php $school = $registration->organization; @endphp
                            <tr wire:key="roster-{{ $registration->getKey() }}" class="border-t border-line-soft">
                                <td class="px-4 py-3">
                                    @if ($school?->logo_path)
                                        <img src="{{ Storage::disk('public')->url($school->logo_path) }}"
                                             alt="{{ $school->name }}"
                                             loading="lazy"
                                             class="h-10 w-10 rounded-full object-contain">
                                    @else
                                        {{-- The initial-letter placeholder (R1.3). --}}
                                        <span aria-hidden="true"
                                              class="flex h-10 w-10 items-center justify-center rounded-full bg-line font-display text-[18px] font-bold text-ink-600">
                                            {{ $this->initialFor($school?->name) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-[15.5px] font-semibold text-ink-900">
                                    @if ($school?->website)
                                        <a href="{{ $school->website }}"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           class="text-ink-900 underline-offset-[3px] hover:text-brand-600 hover:underline">
                                            {{ $school->name }}
                                        </a>
                                    @else
                                        {{ $school?->name }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($roster->hasPages())
                <div class="mt-6">
                    {{ $roster->links() }}
                </div>
            @endif
        @endif
    </x-site.container>
</div>
