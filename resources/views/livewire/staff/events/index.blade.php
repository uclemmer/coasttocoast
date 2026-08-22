{{-- The fair calendar (docs/13). --}}
<div>
    <x-ui::action-bar :heading="__('Fairs')" :description="__('Every fair, past and upcoming.')" level="h2">
        <x-ui::button href="{{ route('staff.events.create') }}" variant="brand">
            {{ __('Add a fair') }}
        </x-ui::button>
    </x-ui::action-bar>

    <div class="mt-6">
        <x-ui::table>
            <x-slot:before>
                <x-ui::table.toolbar>
                    <x-slot:search>
                        <div class="flex flex-wrap items-end gap-3">
                            <x-ui::forms.input name="search" wire:model.live.debounce.300ms="search" type="search"
                                :label="__('Search fairs')" :placeholder="__('Search by name')" />

                            <x-ui::forms.select name="published" wire:model.live="published" :label="__('Published')">
                                <option value="">{{ __('All') }}</option>
                                <option value="yes">{{ __('Published only') }}</option>
                                <option value="no">{{ __('Drafts only') }}</option>
                            </x-ui::forms.select>
                        </div>
                    </x-slot:search>
                </x-ui::table.toolbar>
            </x-slot:before>

            <x-ui::table.head>
                <x-ui::table.heading>{{ __('Fair') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Date') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Fee') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Registered') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Published') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Registration') }}</x-ui::table.heading>
                <x-ui::table.heading><span class="sr-only">{{ __('Actions') }}</span></x-ui::table.heading>
            </x-ui::table.head>

            @forelse ($this->events as $event)
                <x-ui::table.row wire:key="event-{{ $event->id }}">
                    <x-ui::table.cell header>
                        {{ $event->name }}
                        <span class="block text-sm font-normal text-body">{{ $event->venue_name }}</span>
                    </x-ui::table.cell>

                    <x-ui::table.cell>{{ $event->starts_at?->toFormattedDateString() }}</x-ui::table.cell>

                    <x-ui::table.cell>{{ \App\Support\Money::format($event->price_cents) }}</x-ui::table.cell>

                    <x-ui::table.cell>
                        {{ $event->registrations_count }}
                        <span class="block text-sm text-body">{{ $this->capacityNote($event) }}</span>
                    </x-ui::table.cell>

                    <x-ui::table.icon-cell :state="$event->is_published" :true-label="__('Published')"
                        :false-label="__('Draft')" />

                    <x-ui::table.cell>
                        @php($state = $this->registrationState($event))
                        <x-ui::badge :variant="match ($state) {
                            __('Open') => 'success',
                            __('Not yet open') => 'brand',
                            default => 'gray',
                        }">{{ $state }}</x-ui::badge>
                    </x-ui::table.cell>

                    <x-ui::table.cell>
                        <div class="flex items-center justify-end gap-1">
                            <x-ui::button size="xs" variant="secondary"
                                href="{{ route('staff.events.show', $event) }}">{{ __('Open') }}</x-ui::button>
                            <x-ui::button size="xs" variant="secondary"
                                href="{{ route('staff.events.edit', $event) }}">{{ __('Edit') }}</x-ui::button>
                            <x-ui::button size="xs" variant="ghost"
                                wire:click="confirmDelete({{ $event->id }})">{{ __('Remove') }}</x-ui::button>
                        </div>
                    </x-ui::table.cell>
                </x-ui::table.row>
            @empty
                <x-ui::table.row>
                    <x-ui::table.empty-state :colspan="7" :heading="__('No fairs yet')">
                        {{ __('A fair has to exist before schools can register for it.') }}
                        <x-slot:action>
                            <x-ui::button href="{{ route('staff.events.create') }}" variant="brand" size="sm">
                                {{ __('Add a fair') }}
                            </x-ui::button>
                        </x-slot:action>
                    </x-ui::table.empty-state>
                </x-ui::table.row>
            @endforelse
        </x-ui::table>
    </div>

    {{-- The policy refuses this once registrations exist; the dialog says so
         rather than letting the refusal arrive as a bare 403. --}}
    <x-ui::confirm-modal id="delete-event" :title="__('Remove this fair?')" :confirm="__('Remove')"
        variant="danger" wire:click="delete">
        {{ __('Only possible while no school has registered. This cannot be undone.') }}
    </x-ui::confirm-modal>
</div>
