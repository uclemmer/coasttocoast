{{-- One organization, and its representatives (docs/13). --}}
@php($organization = $this->record)

<div>
    <x-ui::action-bar :heading="$organization->name" :description="$organization->website">
        <x-ui::button href="{{ route('staff.organizations') }}" variant="secondary">
            {{ __('Back to organizations') }}
        </x-ui::button>
        <x-ui::button href="{{ route('staff.organizations.edit', $organization) }}" variant="secondary">
            {{ __('Edit') }}
        </x-ui::button>
    </x-ui::action-bar>

    @if ($this->possibleDuplicates->isNotEmpty())
        <div class="mt-4">
            <x-ui::alert variant="warning">
                {{ __('Looks like a duplicate of: :names', [
                    'names' => $this->possibleDuplicates->pluck('name')->join(', '),
                ]) }}
                {{ __('Merge from the organizations list if they really are the same organization.') }}
            </x-ui::alert>
        </div>
    @endif

    <div class="mt-6 max-w-3xl space-y-6">
        <x-ui::section :heading="__('Details')">
            <x-ui::description-list :columns="2">
                <x-ui::description-list.item :term="__('Admissions office')"
                    :description="$organization->admissions_office ?: '—'" />
                <x-ui::description-list.item :term="__('Admissions email')"
                    :description="$organization->admissions_email ?: '—'" />
                <x-ui::description-list.item :term="__('Admissions phone')"
                    :description="$organization->admissions_phone ?: '—'" />
                <x-ui::description-list.item :term="__('Registrations')"
                    :description="(string) $organization->registrations_count" />
                <x-ui::description-list.item :term="__('Address')"
                    :description="$organization->formattedAddress() ?? '—'" />
                {{-- The form used for duplicate detection. Worth showing: it is
                     why two organizations that look different are flagged, or two
                     that look the same are not. --}}
                <x-ui::description-list.item :term="__('Matched as')"
                    :description="$organization->normalized_name" />
            </x-ui::description-list>
        </x-ui::section>

        <x-ui::section :heading="__('Representatives')"
            :description="__('Everyone who has claimed this organization, whatever state their membership is in.')">
            <x-ui::table>
                <x-ui::table.head>
                    <x-ui::table.heading>{{ __('Name') }}</x-ui::table.heading>
                    <x-ui::table.heading>{{ __('Email') }}</x-ui::table.heading>
                    <x-ui::table.heading>{{ __('Membership') }}</x-ui::table.heading>
                    <x-ui::table.heading><span class="sr-only">{{ __('Actions') }}</span></x-ui::table.heading>
                </x-ui::table.head>

                @forelse ($this->representatives as $rep)
                    <x-ui::table.row wire:key="rep-{{ $rep->id }}">
                        <x-ui::table.cell header>{{ $rep->name }}</x-ui::table.cell>
                        <x-ui::table.cell>{{ $rep->email }}</x-ui::table.cell>

                        <x-ui::table.cell>
                            <x-ui::badge :variant="match (true) {
                                $rep->isPendingApproval() => 'warning',
                                $rep->isRetired() => 'gray',
                                default => 'success',
                            }">
                                {{ $rep->membership_status?->getLabel() ?? '—' }}
                            </x-ui::badge>

                            @if ($rep->isRetired() && $rep->retiredBy)
                                <span class="mt-1 block text-sm text-body">
                                    {{ __('Retired by :name', ['name' => $rep->retiredBy->name]) }}
                                </span>
                            @endif
                        </x-ui::table.cell>

                        <x-ui::table.cell>
                            <div class="flex items-center justify-end gap-1">
                                @if ($rep->isPendingApproval())
                                    <x-ui::button size="xs" variant="success"
                                        wire:click="approveClaim({{ $rep->id }})">{{ __('Approve') }}</x-ui::button>
                                    <x-ui::button size="xs" variant="danger"
                                        wire:click="startDeny({{ $rep->id }})">{{ __('Deny') }}</x-ui::button>
                                @endif

                                @if ($rep->actsForOrganization())
                                    <x-ui::button size="xs" variant="ghost"
                                        wire:click="startRetire({{ $rep->id }})">{{ __('Retire') }}</x-ui::button>
                                @endif

                                @if ($rep->isRetired())
                                    <x-ui::button size="xs" variant="secondary"
                                        wire:click="reinstate({{ $rep->id }})">{{ __('Reinstate') }}</x-ui::button>
                                @endif
                            </div>
                        </x-ui::table.cell>
                    </x-ui::table.row>
                @empty
                    <x-ui::table.row>
                        <x-ui::table.empty-state :colspan="4" :heading="__('Nobody has claimed this organization')">
                            {{ __('Campaigns fall back to the admissions email above until somebody does.') }}
                        </x-ui::table.empty-state>
                    </x-ui::table.row>
                @endforelse
            </x-ui::table>
        </x-ui::section>
    </div>

    <x-ui::modal id="deny-claim" :title="__('Deny this claim?')" size="lg">
        <form wire:submit="denyClaim" class="space-y-4">
            <p class="text-sm text-body">
                {{ __('They keep their account but stop being attached to this organization.') }}
            </p>

            <x-ui::forms.textarea name="reason" wire:model="reason" rows="3" :label="__('Reason')"
                :hint="__('Optional. Included in the email they receive.')" />

            <div class="flex items-center gap-3 pt-2">
                <x-ui::button type="submit" variant="danger">{{ __('Deny') }}</x-ui::button>
                <x-ui::button type="button" variant="ghost"
                    x-on:click="$dispatch('ui-modal-close', { id: 'deny-claim' })">{{ __('Cancel') }}</x-ui::button>
            </div>
        </form>
    </x-ui::modal>

    <x-ui::confirm-modal id="retire-rep" :title="__('Retire this representative?')" :confirm="__('Retire')"
        variant="danger" wire:click="retire">
        {{ __('They can still sign in and see this organization’s history, but they can no longer register it or edit its details. Campaigns stop reaching them.') }}
    </x-ui::confirm-modal>
</div>
