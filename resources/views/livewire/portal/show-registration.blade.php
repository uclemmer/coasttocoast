<div class="max-w-3xl space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="font-display text-2xl font-bold tracking-tight text-heading">
                {{ $registration->event?->name }}
            </h1>
            <p class="mt-1 text-sm text-body">
                {{ $registration->organization?->name }}
            </p>
        </div>

        <x-ui::badge>{{ $registration->status->value }}</x-ui::badge>
    </div>

    {{-- The actions, gated on the registration's own state rather than on
         membership: finishing an outstanding payment is not something to lock
         a retired rep out of. --}}
    @if ($this->canPay() || $this->needsCheckForm() || $this->hasReceipt())
        <x-ui::action-bar>
            @if ($this->canPay())
                <x-ui::button wire:click="pay" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="pay">{{ __('Pay now') }}</span>
                    <span wire:loading wire:target="pay">{{ __('Opening…') }}</span>
                </x-ui::button>
            @endif

            @if ($this->needsCheckForm())
                <x-ui::button variant="secondary"
                    wire:click="checkForm">{{ __('Printable check form') }}</x-ui::button>
            @endif

            @if ($this->hasReceipt())
                <x-ui::button variant="secondary" wire:click="receipt">{{ __('Download receipt') }}</x-ui::button>
            @endif
        </x-ui::action-bar>
    @endif

    <x-ui::section heading="{{ __('The fair') }}">
        <x-ui::description-list>
            <x-ui::description-list.item term="{{ __('Fair') }}">
                {{ $registration->event?->name }}
            </x-ui::description-list.item>
            <x-ui::description-list.item term="{{ __('Date') }}">
                {{ $registration->event?->starts_at?->toDayDateTimeString() ?? '—' }}
            </x-ui::description-list.item>
            <x-ui::description-list.item term="{{ __('Venue') }}">
                {{ $registration->event?->venue_name ?? '—' }}
            </x-ui::description-list.item>
            <x-ui::description-list.item term="{{ __('Address') }}">
                {{ $registration->event?->venue_address ?? '—' }}
            </x-ui::description-list.item>
        </x-ui::description-list>
    </x-ui::section>

    <x-ui::section heading="{{ __('Registration') }}">
        <x-ui::description-list>
            <x-ui::description-list.item term="{{ __('Status') }}">
                {{ $registration->status->value }}
            </x-ui::description-list.item>
            <x-ui::description-list.item term="{{ __('Payment method') }}">
                {{ $registration->payment_method?->value ?? '—' }}
            </x-ui::description-list.item>
            {{-- The stored snapshot, not a recalculation: what this school was
                 charged stays what it was charged even if the fair's price
                 moves afterwards. --}}
            <x-ui::description-list.item term="{{ __('Fee') }}">
                {{ \App\Support\Money::format($registration->price_cents) }}
            </x-ui::description-list.item>
            @if ($registration->grant_benefit)
                <x-ui::description-list.item term="{{ __('Grant') }}">
                    {{ $registration->grant_benefit }}
                </x-ui::description-list.item>
            @endif
            <x-ui::description-list.item term="{{ __('Registered') }}">
                {{ $registration->created_at?->toDayDateTimeString() }}
            </x-ui::description-list.item>
            @if ($registration->confirmed_at)
                <x-ui::description-list.item term="{{ __('Confirmed') }}">
                    {{ $registration->confirmed_at->toDayDateTimeString() }}
                </x-ui::description-list.item>
            @endif
            @if ($registration->cancelled_at)
                <x-ui::description-list.item term="{{ __('Cancelled') }}">
                    {{ $registration->cancelled_at->toDayDateTimeString() }}
                </x-ui::description-list.item>
            @endif
        </x-ui::description-list>
    </x-ui::section>

    <x-ui::section heading="{{ __('Who is staffing the table') }}">
        <x-ui::description-list>
            <x-ui::description-list.item term="{{ __('Name') }}">
                {{ $registration->rep_name }}
            </x-ui::description-list.item>
            <x-ui::description-list.item term="{{ __('Email') }}">
                {{ $registration->rep_email }}
            </x-ui::description-list.item>
            <x-ui::description-list.item term="{{ __('Phone') }}">
                {{ $registration->rep_phone ?? '—' }}
            </x-ui::description-list.item>
        </x-ui::description-list>
    </x-ui::section>

    <p class="text-sm">
        <a href="{{ route('portal.registrations') }}"
            class="text-fg-brand hover:underline">{{ __('Back to registrations') }}</a>
    </p>
</div>
