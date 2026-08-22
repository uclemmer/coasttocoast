{{--
    The registration list (docs/13).

    Export and table read the SAME builder, which is the whole feature: apply a
    filter, press export, get that list.
--}}
<div>
    <x-ui::action-bar :heading="__('Registrations')"
        :description="__('Every school signed up, and what they owe.')" level="h2">
        <x-ui::button href="{{ route('staff.registrations.create') }}" variant="brand">
            {{ __('Add a manual registration') }}
        </x-ui::button>
        <x-ui::button variant="secondary" wire:click="export">
            {{ __('Export CSV') }}
        </x-ui::button>
    </x-ui::action-bar>

    <div class="mt-6">
        <x-ui::table>
            <x-slot:before>
                <x-ui::table.toolbar>
                    <x-slot:search>
                        <div class="flex flex-wrap items-end gap-3">
                            <x-ui::forms.input name="search" wire:model.live.debounce.300ms="search" type="search"
                                :label="__('Search')" :placeholder="__('School, contact name or email')" />

                            <x-ui::forms.select name="eventId" wire:model.live="eventId" :label="__('Fair')">
                                <option value="">{{ __('All fairs') }}</option>
                                @foreach ($this->fairs as $fair)
                                    <option value="{{ $fair->id }}">{{ $fair->name }}</option>
                                @endforeach
                            </x-ui::forms.select>

                            <x-ui::forms.select name="status" wire:model.live="status" :label="__('Status')">
                                <option value="">{{ __('All') }}</option>
                                @foreach ($this->statuses() as $case)
                                    <option value="{{ $case->value }}">{{ $case->getLabel() }}</option>
                                @endforeach
                            </x-ui::forms.select>

                            <x-ui::forms.select name="paymentMethod" wire:model.live="paymentMethod"
                                :label="__('Payment')">
                                <option value="">{{ __('All') }}</option>
                                @foreach ($this->paymentMethods() as $case)
                                    <option value="{{ $case->value }}">{{ $case->getLabel() }}</option>
                                @endforeach
                            </x-ui::forms.select>

                            <x-ui::forms.select name="onRoster" wire:model.live="onRoster" :label="__('On roster')">
                                <option value="">{{ __('All') }}</option>
                                <option value="yes">{{ __('Shown') }}</option>
                                <option value="no">{{ __('Hidden') }}</option>
                            </x-ui::forms.select>

                            <x-ui::forms.select name="hasGrant" wire:model.live="hasGrant" :label="__('Grant')">
                                <option value="">{{ __('All') }}</option>
                                <option value="yes">{{ __('With a grant') }}</option>
                                <option value="no">{{ __('Without') }}</option>
                            </x-ui::forms.select>
                        </div>
                    </x-slot:search>
                </x-ui::table.toolbar>
            </x-slot:before>

            <x-ui::table.head>
                <x-ui::table.heading>{{ __('School') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Fair') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Status') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Payment') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Price') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Contact') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('On roster') }}</x-ui::table.heading>
                <x-ui::table.heading><span class="sr-only">{{ __('Actions') }}</span></x-ui::table.heading>
            </x-ui::table.head>

            @forelse ($this->registrations as $registration)
                <x-ui::table.row wire:key="registration-{{ $registration->id }}">
                    <x-ui::table.cell header>{{ $registration->organization?->name }}</x-ui::table.cell>
                    <x-ui::table.cell>{{ $registration->event?->name }}</x-ui::table.cell>

                    <x-ui::table.cell>
                        <x-ui::badge :variant="match ($registration->status) {
                            \App\Enums\RegistrationStatus::Confirmed => 'success',
                            \App\Enums\RegistrationStatus::PendingPayment => 'warning',
                            default => 'gray',
                        }">{{ $registration->status->getLabel() }}</x-ui::badge>
                    </x-ui::table.cell>

                    {{-- No method means free, which is a real answer here
                         rather than missing data. --}}
                    <x-ui::table.cell>{{ $registration->payment_method?->getLabel() ?? __('Free') }}</x-ui::table.cell>

                    <x-ui::table.cell>
                        {{ \App\Support\Money::format($registration->price_cents) }}
                        @if ($registration->grant)
                            <span class="block text-sm text-body">{{ $registration->grant->benefitSummary() }}</span>
                        @endif
                    </x-ui::table.cell>

                    <x-ui::table.cell>
                        {{ $registration->rep_name }}
                        <span class="block text-sm text-body">{{ $registration->rep_email }}</span>
                    </x-ui::table.cell>

                    <x-ui::table.icon-cell :state="(bool) $registration->show_on_roster"
                        :true-label="__('Shown on the public roster')" :false-label="__('Hidden')" />

                    <x-ui::table.cell>
                        <div class="flex items-center justify-end gap-1">
                            <x-ui::button size="xs" variant="secondary"
                                href="{{ route('staff.registrations.show', $registration) }}">{{ __('Open') }}</x-ui::button>

                            {{-- Cancelled, never deleted: the seat is released
                                 through the service and the record survives. --}}
                            @if ($this->canCancel($registration))
                                <x-ui::button size="xs" variant="ghost"
                                    wire:click="confirmCancel({{ $registration->id }})">{{ __('Cancel') }}</x-ui::button>
                            @endif
                        </div>
                    </x-ui::table.cell>
                </x-ui::table.row>
            @empty
                <x-ui::table.row>
                    <x-ui::table.empty-state :colspan="8" :heading="__('No registrations match')" />
                </x-ui::table.row>
            @endforelse
        </x-ui::table>
    </div>

    <x-ui::modal id="cancel-registration" :title="__('Cancel this registration?')" size="lg">
        <form wire:submit="cancel" class="space-y-4">
            <p class="text-sm text-body">
                {{ __('The seat is released and the school stops appearing on the roster. Any payment already taken is not refunded by this — do that separately.') }}
            </p>

            <x-ui::forms.textarea name="cancelReason" wire:model="cancelReason" rows="3" :label="__('Reason')"
                :hint="__('Optional. Kept with the registration.')" />

            <div class="flex items-center gap-3 pt-2">
                <x-ui::button type="submit" variant="danger">{{ __('Cancel the registration') }}</x-ui::button>
                <x-ui::button type="button" variant="ghost"
                    x-on:click="$dispatch('ui-modal-close', { id: 'cancel-registration' })">
                    {{ __('Keep it') }}
                </x-ui::button>
            </div>
        </form>
    </x-ui::modal>
</div>
