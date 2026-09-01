{{-- One registration, and the money on it (docs/13). --}}
@php($registration = $this->record)

<div>
    <x-ui::action-bar :heading="$registration->organization?->name ?? __('Registration')"
        :description="$registration->event?->name">
        <x-ui::button href="{{ route('staff.registrations') }}" variant="secondary">
            {{ __('Back to registrations') }}
        </x-ui::button>

        @if ($this->canMarkCheckReceived())
            <x-ui::button variant="success" wire:click="confirmCheck">{{ __('Mark check received') }}</x-ui::button>
        @endif

        @if ($this->canRefund())
            <x-ui::button variant="warning" wire:click="confirmRefund">{{ __('Refund') }}</x-ui::button>
        @endif
    </x-ui::action-bar>

    {{--
        A short check is surfaced, not blocked — the alternative is noticing in
        April — and it stays put until dismissed, because a toast would be gone
        before anybody could act on it.
    --}}
    @if ($shortfall !== '')
        <div class="mt-4">
            <x-ui::alert variant="warning">
                <p class="font-medium">{{ __('Recorded, but the check is short.') }}</p>
                <p class="mt-1">{{ $shortfall }}</p>
                <x-ui::button size="xs" variant="ghost" class="mt-2" wire:click="dismissShortfall">
                    {{ __('Dismiss') }}
                </x-ui::button>
            </x-ui::alert>
        </div>
    @endif

    <div class="mt-6 max-w-3xl space-y-6">
        <x-ui::section :heading="__('The registration')">
            <x-ui::description-list :columns="2">
                <x-ui::description-list.item :term="__('Status')">
                    <x-ui::badge :variant="match ($registration->status) {
                        \App\Enums\RegistrationStatus::Confirmed => 'success',
                        \App\Enums\RegistrationStatus::PendingPayment => 'warning',
                        default => 'gray',
                    }">{{ $registration->status->getLabel() }}</x-ui::badge>
                </x-ui::description-list.item>

                <x-ui::description-list.item :term="__('Price')"
                    :description="\App\Support\Money::format($registration->price_cents)" />
                <x-ui::description-list.item :term="__('Payment method')"
                    :description="$registration->payment_method?->getLabel() ?? __('Free')" />
                <x-ui::description-list.item :term="__('Grant')"
                    :description="$registration->grant?->benefitSummary() ?? '—'" />
                <x-ui::description-list.item :term="__('Registered')"
                    :description="$registration->created_at?->toDayDateTimeString()" />
                <x-ui::description-list.item :term="__('Confirmed')"
                    :description="$registration->confirmed_at?->toDayDateTimeString() ?? '—'" />
            </x-ui::description-list>

            <p class="mt-3 max-w-prose text-sm text-body">
                {{ __('Status, price, organization and fair are not editable here. Each is the outcome of a decision that has to go through the registration service, so receipts are sent and the price snapshot stays honest.') }}
            </p>
        </x-ui::section>

        <form wire:submit="saveDetails">
            <x-ui::section :heading="__('Coordinator controls')">
                <x-ui::forms.toggle name="show_on_roster" wire:model="show_on_roster"
                    :label="__('Show on the public roster')" />
                <p class="mt-1 text-sm text-body">
                    {{ __('Confirmed registrations appear on the Representatives page unless this is off.') }}
                </p>

                <div class="mt-4">
                    <x-ui::forms.textarea name="notes" wire:model="notes" rows="4" :label="__('Internal notes')" />
                </div>
            </x-ui::section>

            <div class="mt-6">
                <x-ui::section :heading="__('Fair contact')"
                    :description="__('Who is staffing the table. Not necessarily the account holder.')">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-ui::forms.input name="rep_name" wire:model="rep_name" :label="__('Name')" required />
                        <x-ui::forms.input name="rep_email" wire:model="rep_email" type="email" :label="__('Email')"
                            required />
                        <x-ui::forms.input name="rep_phone" wire:model="rep_phone" type="tel" :label="__('Phone')" />
                    </div>
                </x-ui::section>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <x-ui::button type="submit" variant="brand">{{ __('Save changes') }}</x-ui::button>
                <span wire:loading wire:target="saveDetails" class="text-sm text-body">{{ __('Saving…') }}</span>
            </div>
        </form>

        @if ($registration->payments->isNotEmpty())
            <x-ui::section :heading="__('Payments')">
                <x-ui::table>
                    <x-ui::table.head>
                        <x-ui::table.heading>{{ __('Method') }}</x-ui::table.heading>
                        <x-ui::table.heading>{{ __('Status') }}</x-ui::table.heading>
                        <x-ui::table.heading>{{ __('Amount') }}</x-ui::table.heading>
                        <x-ui::table.heading>{{ __('When') }}</x-ui::table.heading>
                    </x-ui::table.head>

                    @foreach ($registration->payments as $payment)
                        <x-ui::table.row wire:key="payment-{{ $payment->id }}">
                            <x-ui::table.cell header>{{ $payment->method?->getLabel() ?? '—' }}</x-ui::table.cell>
                            <x-ui::table.cell>
                                <x-ui::badge variant="gray">{{ $payment->status?->getLabel() ?? '—' }}</x-ui::badge>
                            </x-ui::table.cell>
                            <x-ui::table.cell>{{ \App\Support\Money::format($payment->amount_cents) }}</x-ui::table.cell>
                            <x-ui::table.cell>{{ $payment->created_at?->toFormattedDateString() }}</x-ui::table.cell>
                        </x-ui::table.row>
                    @endforeach
                </x-ui::table>
            </x-ui::section>
        @endif
    </div>

    <x-ui::modal id="record-check" :title="__('Record a check')" size="lg">
        <form wire:submit="markCheckReceived" class="space-y-4">
            <p class="text-sm text-body">{{ __('This confirms the registration and queues the receipt.') }}</p>

            <x-ui::forms.input name="checkNumber" wire:model="checkNumber" :label="__('Check number')" />
            <x-ui::forms.date name="receivedOn" wire:model="receivedOn" :label="__('Received on')" required />

            {{-- Defaults to what is owed: the common case is a check for the
                 right amount, and making somebody retype it invites a typo into
                 the one number that matters. --}}
            <x-ui::forms.input name="checkAmountDollars" wire:model="checkAmountDollars" type="number" step="0.01"
                min="0" :label="__('Amount on the check')"
                :hint="__('In dollars. Defaults to what was owed — change it only if the check differs.')" required />

            <div class="flex items-center gap-3 pt-2">
                <x-ui::button type="submit" variant="success">{{ __('Record it') }}</x-ui::button>
                <x-ui::button type="button" variant="ghost"
                    x-on:click="$dispatch('ui-modal-close', { id: 'record-check' })">{{ __('Cancel') }}</x-ui::button>
            </div>
        </form>
    </x-ui::modal>

    <x-ui::modal id="refund-payment" :title="__('Refund this payment?')" size="lg">
        <form wire:submit="refund" class="space-y-4">
            <p class="text-sm text-body">
                {{ __('Sent to Stripe. The payment is marked refunded only when Stripe confirms it, so an admin refund and one issued from the Stripe dashboard leave the same record.') }}
            </p>

            <x-ui::forms.input name="refundAmountDollars" wire:model="refundAmountDollars" type="number"
                step="0.01" min="0" :label="__('Amount to refund')"
                :hint="__('In dollars. Defaults to the full price.')" required />

            <div class="flex items-center gap-3 pt-2">
                <x-ui::button type="submit" variant="warning">{{ __('Send the refund') }}</x-ui::button>
                <x-ui::button type="button" variant="ghost"
                    x-on:click="$dispatch('ui-modal-close', { id: 'refund-payment' })">{{ __('Cancel') }}</x-ui::button>
            </div>
        </form>
    </x-ui::modal>
</div>
