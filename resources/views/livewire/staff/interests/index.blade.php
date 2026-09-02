{{--
    The notify-me list (docs/13).

    Read and prune only. The button that mails these people lives on the fair
    page, because it is an action on a fair — see the component's class note.
--}}
<div>
    <x-ui::action-bar :heading="__('Notify-me list')"
        :description="__('People who asked to be told when registration opens.')" level="h2" />

    <div class="mt-6">
        <x-ui::table>
            <x-slot:before>
                <x-ui::table.toolbar>
                    <x-slot:search>
                        <div class="flex flex-wrap items-end gap-3">
                            <x-ui::forms.input name="search" wire:model.live.debounce.300ms="search" type="search"
                                :label="__('Search')" :placeholder="__('Email or organization')" />

                            <x-ui::forms.select name="eventId" wire:model.live="eventId" :label="__('Fair')">
                                <option value="">{{ __('All fairs') }}</option>
                                @foreach ($this->fairs as $fair)
                                    <option value="{{ $fair->id }}">{{ $fair->name }}</option>
                                @endforeach
                            </x-ui::forms.select>

                            <x-ui::forms.select name="status" wire:model.live="status" :label="__('Told yet?')">
                                <option value="">{{ __('All') }}</option>
                                <option value="waiting">{{ __('Still waiting') }}</option>
                                <option value="notified">{{ __('Already told') }}</option>
                            </x-ui::forms.select>
                        </div>
                    </x-slot:search>
                </x-ui::table.toolbar>

                @if ($this->waitingCount > 0)
                    {{-- Points at the fair page's button without offering a
                         second one that does the same thing. It becomes a link
                         only when a single fair is selected, because that is
                         the only time there is one fair page to send someone
                         to — "all fairs" has no destination and a link that
                         guessed one would be worse than prose. --}}
                    <div class="px-4 pb-3 text-sm text-body">
                        {{ trans_choice(
                            ':count person here has not been told yet.|:count people here have not been told yet.',
                            $this->waitingCount,
                            ['count' => $this->waitingCount],
                        ) }}
                        @if ($this->selectedFair)
                            <a href="{{ route('staff.events.show', $this->selectedFair) }}"
                                class="text-fg-brand hover:underline">{{ __('Announce it from the fair page.') }}</a>
                        @else
                            {{ __('Announce registration from the fair page.') }}
                        @endif
                    </div>
                @endif

                @if (count($selected) > 0)
                    <x-ui::table.bulk-bar :count="count($selected)" :noun="__('signup')">
                        <x-ui::button size="sm" variant="danger" wire:click="deleteSelected">
                            {{ __('Remove') }}
                        </x-ui::button>
                    </x-ui::table.bulk-bar>
                @endif
            </x-slot:before>

            <x-ui::table.head>
                <x-ui::table.heading><span class="sr-only">{{ __('Select') }}</span></x-ui::table.heading>
                <x-ui::table.heading>{{ __('Organization') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Email') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Fair') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Signed up') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Told yet?') }}</x-ui::table.heading>
                <x-ui::table.heading><span class="sr-only">{{ __('Actions') }}</span></x-ui::table.heading>
            </x-ui::table.head>

            @forelse ($this->interests as $interest)
                <x-ui::table.row wire:key="interest-{{ $interest->id }}">
                    <x-ui::table.cell>
                        <x-ui::forms.checkbox name="selected" wire:model.live="selected"
                            :value="(string) $interest->id"
                            :aria-label="__('Select :email', ['email' => $interest->email])" />
                    </x-ui::table.cell>

                    <x-ui::table.cell header>
                        @if ($interest->organization_name)
                            {{ $interest->organization_name }}
                        @else
                            {{-- The form's organization field is optional, so a
                                 blank one means "not given", not "no
                                 organization". --}}
                            <span class="text-body">{{ __('Not given') }}</span>
                        @endif
                    </x-ui::table.cell>

                    <x-ui::table.cell>{{ $interest->email }}</x-ui::table.cell>

                    <x-ui::table.cell>{{ $interest->event?->name ?? __('—') }}</x-ui::table.cell>

                    <x-ui::table.cell>{{ $interest->created_at?->toFormattedDateString() }}</x-ui::table.cell>

                    <x-ui::table.cell>
                        @if ($interest->notified_at)
                            <x-ui::badge variant="success">
                                {{ __('Told :date', ['date' => $interest->notified_at->toFormattedDateString()]) }}
                            </x-ui::badge>
                        @else
                            <x-ui::badge variant="warning">{{ __('Waiting') }}</x-ui::badge>
                        @endif
                    </x-ui::table.cell>

                    <x-ui::table.cell>
                        <div class="flex items-center justify-end gap-1">
                            <x-ui::button size="xs" variant="ghost"
                                wire:click="confirmDelete({{ $interest->id }})">
                                {{ __('Remove') }}
                            </x-ui::button>
                        </div>
                    </x-ui::table.cell>
                </x-ui::table.row>
            @empty
                <x-ui::table.row>
                    <x-ui::table.empty-state :colspan="7"
                        :heading="$search === '' && $eventId === '' && $status === ''
                            ? __('Nobody is waiting')
                            : __('No signups match those filters')">
                        @if ($search === '' && $eventId === '' && $status === '')
                            {{ __('Anyone who asks to be told when registration opens appears here.') }}
                        @endif
                    </x-ui::table.empty-state>
                </x-ui::table.row>
            @endforelse
        </x-ui::table>
    </div>

    <x-ui::confirm-modal id="delete-interest" :title="__('Remove this signup?')"
        :confirm="__('Remove')" variant="danger" wire:click="delete">
        {{ __('They stop appearing here and will not be told when registration opens. They can sign up again from the public page. This cannot be undone.') }}
    </x-ui::confirm-modal>
</div>
