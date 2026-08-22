{{--
    The sponsors list (docs/13).

    Table chrome is `uclemmer/laravel-ui`; the query, the selection and the
    ordering are all the component's. That division is the package's own rule —
    it renders affordances and announces state, and sorts, searches and selects
    nothing.
--}}
<div>
    <x-ui::action-bar :heading="__('Sponsors')"
        :description="__('Shown on the public Sponsors page, in this order.')" level="h2">
        <x-ui::button href="{{ route('staff.sponsors.create') }}" variant="brand">
            {{ __('Add a sponsor') }}
        </x-ui::button>
    </x-ui::action-bar>

    <div class="mt-6">
        <x-ui::table>
            <x-slot:before>
                <x-ui::table.toolbar>
                    <x-slot:search>
                        <x-ui::forms.input name="search" wire:model.live.debounce.300ms="search" type="search"
                            :label="__('Search sponsors')" :placeholder="__('Search by name')" />
                    </x-slot:search>
                </x-ui::table.toolbar>

                {{-- The bulk bar's visibility is the host's job, not the
                     package's. --}}
                @if (count($selected) > 0)
                    <x-ui::table.bulk-bar :count="count($selected)" :noun="__('sponsor')">
                        <x-ui::button size="sm" variant="danger" wire:click="deleteSelected">
                            {{ __('Remove') }}
                        </x-ui::button>
                    </x-ui::table.bulk-bar>
                @endif
            </x-slot:before>

            <x-ui::table.head>
                <x-ui::table.heading><span class="sr-only">{{ __('Select') }}</span></x-ui::table.heading>
                <x-ui::table.heading>{{ __('Logo') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Name') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Website') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Staff listed') }}</x-ui::table.heading>
                <x-ui::table.heading><span class="sr-only">{{ __('Actions') }}</span></x-ui::table.heading>
            </x-ui::table.head>

            @forelse ($this->sponsors as $sponsor)
                <x-ui::table.row wire:key="sponsor-{{ $sponsor->id }}">
                    <x-ui::table.cell>
                        {{-- No `label`, an `aria-label` instead: the component
                             always renders a visible label when given one, and
                             "Select Baylor School" beside every tick is noise.
                             Attributes fall through to the input, so the name
                             still reaches a screen reader. --}}
                        <x-ui::forms.checkbox name="selected" wire:model.live="selected"
                            :value="(string) $sponsor->id"
                            :aria-label="__('Select :name', ['name' => $sponsor->name])" />
                    </x-ui::table.cell>

                    {{-- `alt` is required by the component and throws without
                         one; empty is the right answer here because the name is
                         in the next cell and repeating it is noise. --}}
                    <x-ui::table.image-cell :src="$sponsor->logo_path ? Storage::disk('public')->url($sponsor->logo_path) : null"
                        alt="" />

                    <x-ui::table.cell header>{{ $sponsor->name }}</x-ui::table.cell>

                    <x-ui::table.cell>
                        @if ($sponsor->website)
                            <a href="{{ $sponsor->website }}" rel="noopener noreferrer" target="_blank"
                                class="text-fg-brand hover:underline">{{ $sponsor->website }}</a>
                        @else
                            <span class="text-body">&mdash;</span>
                        @endif
                    </x-ui::table.cell>

                    <x-ui::table.cell>{{ $sponsor->staff_count }}</x-ui::table.cell>

                    <x-ui::table.cell>
                        <div class="flex items-center justify-end gap-1">
                            @if ($this->canReorder)
                                <x-ui::button size="xs" variant="ghost" wire:click="moveUp({{ $sponsor->id }})"
                                    :disabled="$loop->first">
                                    <span class="sr-only">{{ __('Move :name up', ['name' => $sponsor->name]) }}</span>
                                    <span aria-hidden="true">&uarr;</span>
                                </x-ui::button>
                                <x-ui::button size="xs" variant="ghost" wire:click="moveDown({{ $sponsor->id }})"
                                    :disabled="$loop->last">
                                    <span class="sr-only">{{ __('Move :name down', ['name' => $sponsor->name]) }}</span>
                                    <span aria-hidden="true">&darr;</span>
                                </x-ui::button>
                            @endif

                            <x-ui::button size="xs" variant="secondary"
                                href="{{ route('staff.sponsors.edit', $sponsor) }}">
                                {{ __('Edit') }}
                            </x-ui::button>

                            <x-ui::button size="xs" variant="ghost"
                                wire:click="confirmDelete({{ $sponsor->id }})">
                                {{ __('Remove') }}
                            </x-ui::button>
                        </div>
                    </x-ui::table.cell>
                </x-ui::table.row>
            @empty
                <x-ui::table.row>
                    <x-ui::table.empty-state :colspan="6"
                        :heading="$search === '' ? __('No sponsors yet') : __('No sponsors match that search')">
                        @if ($search === '')
                            {{ __('Sponsors appear on the public Sponsors page in the order you set here.') }}
                            <x-slot:action>
                                <x-ui::button href="{{ route('staff.sponsors.create') }}" variant="brand" size="sm">
                                    {{ __('Add a sponsor') }}
                                </x-ui::button>
                            </x-slot:action>
                        @endif
                    </x-ui::table.empty-state>
                </x-ui::table.row>
            @endforelse
        </x-ui::table>
    </div>

    <x-ui::confirm-modal id="delete-sponsor" :title="__('Remove this sponsor?')"
        :confirm="__('Remove')" variant="danger" wire:click="delete">
        {{ __('The sponsor and the staff listed under it are removed from the public page. This cannot be undone.') }}
    </x-ui::confirm-modal>
</div>
