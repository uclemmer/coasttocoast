{{--
    One grant application in full (docs/13) — replaces the Filament infolist.

    `x-ui::description-list` is the component that replaces it; the justification
    sits outside the list because it is prose, and it is the thing the
    coordinator is actually here to read.
--}}
@php($grant = $this->record)

<div>
    <x-ui::action-bar :heading="$grant->organization?->name ?? __('Grant application')"
        :description="$grant->event?->name">
        <x-ui::button href="{{ route('staff.grants') }}" variant="secondary">
            {{ __('Back to the queue') }}
        </x-ui::button>

        @if ($this->canDecide($grant))
            <x-ui::button variant="success" wire:click="startApprove({{ $grant->id }})">
                {{ __('Approve') }}
            </x-ui::button>
            <x-ui::button variant="danger" wire:click="startDeny({{ $grant->id }})">
                {{ __('Deny') }}
            </x-ui::button>
        @endif

        @if ($this->canRevoke($grant))
            <x-ui::button variant="ghost" wire:click="startRevoke({{ $grant->id }})">
                {{ __('Revoke') }}
            </x-ui::button>
        @endif
    </x-ui::action-bar>

    @if ($grant->isUsed())
        <div class="mt-4">
            <x-ui::alert variant="info">
                {{ __('A live registration is priced under this grant, so it can no longer be revoked.') }}
            </x-ui::alert>
        </div>
    @endif

    <div class="mt-6 max-w-3xl">
        <x-ui::section :heading="__('The application')">
            <x-ui::description-list :columns="2">
                <x-ui::description-list.item :term="__('Organization')" :description="$grant->organization?->name" />
                <x-ui::description-list.item :term="__('Fair')" :description="$grant->event?->name ?? '—'" />

                <x-ui::description-list.item :term="__('Status')">
                    <x-ui::badge :variant="match ($grant->status) {
                        \App\Enums\GrantStatus::Pending => 'warning',
                        \App\Enums\GrantStatus::Approved => 'success',
                        default => 'gray',
                    }">
                        {{ $grant->status->getLabel() }}
                    </x-ui::badge>
                </x-ui::description-list.item>

                <x-ui::description-list.item :term="__('Benefit')"
                    :description="$grant->benefitSummary() ?? __('Not decided')" />

                <x-ui::description-list.item :term="__('Applied by')"
                    :description="$grant->requester?->name ?? '—'" />
                <x-ui::description-list.item :term="__('Applied')"
                    :description="$grant->created_at?->toDayDateTimeString() ?? '—'" />

                <x-ui::description-list.item :term="__('Decided by')"
                    :description="$grant->decider?->name ?? '—'" />
                <x-ui::description-list.item :term="__('Decided')"
                    :description="$grant->decided_at?->toDayDateTimeString() ?? '—'" />
            </x-ui::description-list>
        </x-ui::section>

        {{-- Prose, not a cell. This is the organization's case for needing help and
             the reason this screen exists at all. --}}
        <div class="mt-6">
            <x-ui::section :heading="__('Why they applied')">
                <p class="max-w-prose whitespace-pre-line text-[15px] leading-relaxed text-body">
                    {{ $grant->justification }}
                </p>
            </x-ui::section>
        </div>

        @if (filled($grant->denial_reason))
            <div class="mt-6">
                <x-ui::section :heading="__('Reason given')">
                    <p class="max-w-prose whitespace-pre-line text-[15px] leading-relaxed text-body">
                        {{ $grant->denial_reason }}
                    </p>
                </x-ui::section>
            </div>
        @endif
    </div>

    @include('livewire.staff.grants.partials.decision-modals')
</div>
