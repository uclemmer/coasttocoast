{{--
    The grant review queue (docs/13).

    Filtered to Pending on arrival, because this is a queue and not an archive.
--}}
<div>
    <x-ui::action-bar :heading="__('Fee assistance')"
        :description="__('Applications from schools, and the three decisions you can make on them.')" level="h2" />

    @if ($this->pendingCount > 0)
        {{-- An alert rather than a sidebar badge: the staff nav is a flat list
             of six links, and this number is worth a sentence. Somebody is
             waiting on the other end of each one. --}}
        <div class="mt-4">
            <x-ui::alert variant="warning">
                {{ trans_choice(
                    ':count application is waiting on a decision.|:count applications are waiting on a decision.',
                    $this->pendingCount,
                    ['count' => $this->pendingCount],
                ) }}
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
                                :label="__('Search schools')" :placeholder="__('Search by school name')" />

                            <x-ui::forms.select name="status" wire:model.live="status" :label="__('Status')">
                                <option value="">{{ __('All') }}</option>
                                @foreach (\App\Enums\GrantStatus::cases() as $case)
                                    <option value="{{ $case->value }}">{{ $case->getLabel() }}</option>
                                @endforeach
                            </x-ui::forms.select>

                            <x-ui::forms.select name="eventId" wire:model.live="eventId" :label="__('Fair')">
                                <option value="">{{ __('All fairs') }}</option>
                                @foreach ($this->fairs as $fair)
                                    <option value="{{ $fair->id }}">{{ $fair->name }}</option>
                                @endforeach
                            </x-ui::forms.select>
                        </div>
                    </x-slot:search>
                </x-ui::table.toolbar>
            </x-slot:before>

            <x-ui::table.head>
                <x-ui::table.heading>{{ __('School') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Fair') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Status') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Benefit') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Used') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Applied') }}</x-ui::table.heading>
                <x-ui::table.heading><span class="sr-only">{{ __('Actions') }}</span></x-ui::table.heading>
            </x-ui::table.head>

            @forelse ($this->grants as $grant)
                <x-ui::table.row wire:key="grant-{{ $grant->id }}">
                    <x-ui::table.cell header>{{ $grant->organization?->name }}</x-ui::table.cell>
                    <x-ui::table.cell>{{ $grant->event?->name ?? '—' }}</x-ui::table.cell>

                    <x-ui::table.cell>
                        <x-ui::badge :variant="match ($grant->status) {
                            \App\Enums\GrantStatus::Pending => 'warning',
                            \App\Enums\GrantStatus::Approved => 'success',
                            default => 'gray',
                        }">
                            {{ $grant->status->getLabel() }}
                        </x-ui::badge>
                    </x-ui::table.cell>

                    <x-ui::table.cell>{{ $grant->benefitSummary() ?? '—' }}</x-ui::table.cell>

                    {{-- The column that explains why Revoke has disappeared: a
                         used grant can no longer be revoked. --}}
                    <x-ui::table.icon-cell :state="$grant->isUsed()"
                        :true-label="__('A live registration is priced under this grant, so it can no longer be revoked.')"
                        :false-label="__('Not used yet')" />

                    <x-ui::table.cell>{{ $grant->created_at?->toFormattedDateString() }}</x-ui::table.cell>

                    <x-ui::table.cell>
                        <div class="flex items-center justify-end gap-1">
                            <x-ui::button size="xs" variant="secondary"
                                href="{{ route('staff.grants.show', $grant) }}">{{ __('Open') }}</x-ui::button>

                            @if ($this->canDecide($grant))
                                <x-ui::button size="xs" variant="success"
                                    wire:click="startApprove({{ $grant->id }})">{{ __('Approve') }}</x-ui::button>
                                <x-ui::button size="xs" variant="danger"
                                    wire:click="startDeny({{ $grant->id }})">{{ __('Deny') }}</x-ui::button>
                            @endif

                            @if ($this->canRevoke($grant))
                                <x-ui::button size="xs" variant="ghost"
                                    wire:click="startRevoke({{ $grant->id }})">{{ __('Revoke') }}</x-ui::button>
                            @endif
                        </div>
                    </x-ui::table.cell>
                </x-ui::table.row>
            @empty
                <x-ui::table.row>
                    <x-ui::table.empty-state :colspan="7" :heading="__('Nothing here')">
                        {{ $status === \App\Enums\GrantStatus::Pending->value
                            ? __('No applications are waiting on a decision. Choose another status to see decided ones.')
                            : __('No applications match those filters.') }}
                    </x-ui::table.empty-state>
                </x-ui::table.row>
            @endforelse
        </x-ui::table>
    </div>

    @include('livewire.staff.grants.partials.decision-modals')
</div>
