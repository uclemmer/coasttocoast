{{-- The campaign list (docs/13). --}}
<div>
    <x-ui::action-bar :heading="__('Campaigns')"
        :description="__('Email and text sent to schools. A sent campaign cannot be edited or removed.')" level="h2">
        <x-ui::button href="{{ route('staff.messages.create') }}" variant="brand">
            {{ __('New campaign') }}
        </x-ui::button>
    </x-ui::action-bar>

    <div class="mt-6">
        <x-ui::table>
            <x-slot:before>
                <x-ui::table.toolbar>
                    <x-slot:search>
                        <div class="flex flex-wrap items-end gap-3">
                            <x-ui::forms.input name="search" wire:model.live.debounce.300ms="search" type="search"
                                :label="__('Search campaigns')" :placeholder="__('Search the subject')" />

                            <x-ui::forms.select name="audience" wire:model.live="audience" :label="__('Audience')">
                                <option value="">{{ __('All audiences') }}</option>
                                @foreach ($this->audiences() as $case)
                                    <option value="{{ $case->value }}">{{ $case->getLabel() }}</option>
                                @endforeach
                            </x-ui::forms.select>
                        </div>
                    </x-slot:search>
                </x-ui::table.toolbar>
            </x-slot:before>

            <x-ui::table.head>
                <x-ui::table.heading>{{ __('Subject') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Audience') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Fair') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Recipients') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Status') }}</x-ui::table.heading>
                <x-ui::table.heading><span class="sr-only">{{ __('Actions') }}</span></x-ui::table.heading>
            </x-ui::table.head>

            @forelse ($this->messages as $message)
                <x-ui::table.row wire:key="message-{{ $message->id }}">
                    <x-ui::table.cell header>
                        <span class="block max-w-prose">{{ $message->subject }}</span>
                    </x-ui::table.cell>

                    <x-ui::table.cell>
                        <x-ui::badge variant="gray">{{ $message->audience?->getLabel() ?? '—' }}</x-ui::badge>
                    </x-ui::table.cell>

                    {{-- No fair means the active one, which is a real setting
                         rather than a missing value. --}}
                    <x-ui::table.cell>{{ $message->event?->name ?? __('Active fair') }}</x-ui::table.cell>

                    <x-ui::table.cell>{{ $message->recipients_count }}</x-ui::table.cell>

                    <x-ui::table.cell>
                        <x-ui::badge :variant="$message->isSent() ? 'success' : 'gray'">
                            {{ $this->statusLine($message) }}
                        </x-ui::badge>
                    </x-ui::table.cell>

                    <x-ui::table.cell>
                        <div class="flex items-center justify-end gap-1">
                            <x-ui::button size="xs" variant="secondary"
                                href="{{ route('staff.messages.show', $message) }}">{{ __('Open') }}</x-ui::button>

                            {{-- A sent campaign is the record of what was said.
                                 No edit, no delete. --}}
                            @unless ($message->isSent())
                                <x-ui::button size="xs" variant="secondary"
                                    href="{{ route('staff.messages.edit', $message) }}">{{ __('Edit') }}</x-ui::button>
                                <x-ui::button size="xs" variant="ghost"
                                    wire:click="confirmDelete({{ $message->id }})">{{ __('Remove') }}</x-ui::button>
                            @endunless
                        </div>
                    </x-ui::table.cell>
                </x-ui::table.row>
            @empty
                <x-ui::table.row>
                    <x-ui::table.empty-state :colspan="6"
                        :heading="$search === '' && $audience === '' ? __('No campaigns yet') : __('Nothing matches those filters')" />
                </x-ui::table.row>
            @endforelse
        </x-ui::table>
    </div>

    <x-ui::confirm-modal id="delete-message" :title="__('Remove this campaign?')" :confirm="__('Remove')"
        variant="danger" wire:click="delete">
        {{ __('Only a campaign that has never been sent can be removed.') }}
    </x-ui::confirm-modal>
</div>
