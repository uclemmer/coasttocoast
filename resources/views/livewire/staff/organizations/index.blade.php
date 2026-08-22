{{-- The school directory (docs/13). --}}
<div>
    <x-ui::action-bar :heading="__('Schools')"
        :description="__('Every school in the directory, with or without a representative.')" level="h2">
        <x-ui::button href="{{ route('staff.organizations.create') }}" variant="brand">
            {{ __('Add a school') }}
        </x-ui::button>
    </x-ui::action-bar>

    {{--
        Merge collisions. An alert, not a toast, and dismissed by hand: which of
        two paid registrations a school keeps is a decision about money, and a
        message about it must survive the next click. Filament used
        `->persistent()` for exactly this.
    --}}
    @if ($collisions !== [])
        <div class="mt-4">
            <x-ui::alert variant="warning">
                <p class="font-medium">
                    {{ trans_choice(
                        'Merged, but :count registration now clashes.|Merged, but :count registrations now clash.',
                        count($collisions),
                        ['count' => count($collisions)],
                    ) }}
                </p>
                <p class="mt-1">
                    {{ __('The same school now holds two live registrations for the same fair. Cancel whichever is wrong.') }}
                </p>
                <x-ui::button size="xs" variant="ghost" class="mt-2" wire:click="dismissCollisions">
                    {{ __('Dismiss') }}
                </x-ui::button>
            </x-ui::alert>
        </div>
    @endif

    <div class="mt-6">
        <x-ui::table>
            <x-slot:before>
                <x-ui::table.toolbar>
                    <x-slot:search>
                        <div class="flex flex-wrap items-end gap-3">
                            <x-ui::forms.input name="search" wire:model.live.debounce.300ms="search" type="search"
                                :label="__('Search schools')" :placeholder="__('Search by name')" />

                            <x-ui::forms.select name="filter" wire:model.live="filter" :label="__('Show')">
                                <option value="">{{ __('All schools') }}</option>
                                <option value="needs_a_rep">{{ __('No active representative') }}</option>
                                <option value="possible_duplicates">{{ __('Possible duplicates') }}</option>
                            </x-ui::forms.select>
                        </div>
                    </x-slot:search>
                </x-ui::table.toolbar>
            </x-slot:before>

            <x-ui::table.head>
                <x-ui::table.heading>{{ __('Logo') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('School') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Active reps') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Registrations') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Admissions email') }}</x-ui::table.heading>
                <x-ui::table.heading><span class="sr-only">{{ __('Actions') }}</span></x-ui::table.heading>
            </x-ui::table.head>

            @forelse ($this->organizations as $organization)
                <x-ui::table.row wire:key="org-{{ $organization->id }}">
                    <x-ui::table.image-cell rounded="full"
                        :src="$organization->logo_path ? Storage::disk('public')->url($organization->logo_path) : null"
                        alt="" />

                    <x-ui::table.cell header>
                        {{ $organization->name }}
                        @if ($organization->website)
                            <span class="block text-sm font-normal text-body">{{ $organization->website }}</span>
                        @endif
                    </x-ui::table.cell>

                    {{-- Zero is the interesting number: campaigns then fall back
                         to admissions_email, or drop the school entirely. --}}
                    <x-ui::table.cell>
                        @if ($organization->active_reps_count === 0)
                            <x-ui::badge variant="warning">{{ __('None') }}</x-ui::badge>
                        @else
                            {{ $organization->active_reps_count }}
                        @endif
                    </x-ui::table.cell>

                    <x-ui::table.cell>{{ $organization->registrations_count }}</x-ui::table.cell>
                    <x-ui::table.cell>{{ $organization->admissions_email ?: '—' }}</x-ui::table.cell>

                    <x-ui::table.cell>
                        <div class="flex items-center justify-end gap-1">
                            <x-ui::button size="xs" variant="secondary"
                                href="{{ route('staff.organizations.show', $organization) }}">{{ __('Open') }}</x-ui::button>
                            <x-ui::button size="xs" variant="secondary"
                                href="{{ route('staff.organizations.edit', $organization) }}">{{ __('Edit') }}</x-ui::button>
                            <x-ui::button size="xs" variant="warning"
                                wire:click="startMerge({{ $organization->id }})">{{ __('Merge into…') }}</x-ui::button>
                            <x-ui::button size="xs" variant="ghost"
                                wire:click="confirmDelete({{ $organization->id }})">{{ __('Remove') }}</x-ui::button>
                        </div>
                    </x-ui::table.cell>
                </x-ui::table.row>
            @empty
                <x-ui::table.row>
                    <x-ui::table.empty-state :colspan="6"
                        :heading="$search === '' && $filter === '' ? __('No schools yet') : __('Nothing matches those filters')" />
                </x-ui::table.row>
            @endforelse
        </x-ui::table>
    </div>

    <x-ui::modal id="merge-organization" :title="__('Merge this school into another?')" size="lg">
        <form wire:submit="merge" class="space-y-4">
            <p class="text-sm text-body">
                {{ __('Nothing is lost: representatives, registrations and grants move to the school you keep, and the empty record is removed afterwards.') }}
            </p>

            <x-ui::forms.select name="keepId" wire:model="keepId" :label="__('Keep this school')" required>
                <option value="">{{ __('Choose a school…') }}</option>
                @foreach ($this->mergeTargets as $target)
                    <option value="{{ $target->id }}">{{ $target->name }}</option>
                @endforeach
            </x-ui::forms.select>

            <div class="flex items-center gap-3 pt-2">
                <x-ui::button type="submit" variant="warning">{{ __('Merge') }}</x-ui::button>
                <x-ui::button type="button" variant="ghost"
                    x-on:click="$dispatch('ui-modal-close', { id: 'merge-organization' })">
                    {{ __('Cancel') }}
                </x-ui::button>
            </div>
        </form>
    </x-ui::modal>

    <x-ui::confirm-modal id="delete-organization" :title="__('Remove this school?')" :confirm="__('Remove')"
        variant="danger" wire:click="delete">
        {{ __('Deleting takes its registration history with it. Merge into another school instead if this is a duplicate.') }}
    </x-ui::confirm-modal>
</div>
