{{--
    One page, three sections — not a wizard (owner decision, 2026-08-21).
    A form that ends in a payment should show the whole commitment at once.
--}}
<div class="max-w-2xl">
    <form wire:submit="submit" class="space-y-6">
        <x-ui::section heading="{{ __('Which fair') }}">
            @if ($this->openFairs->isEmpty())
                <x-ui::alert variant="info">
                    {{ __('There is nothing open for registration right now. We will email you when the next fair opens.') }}
                </x-ui::alert>
            @else
                <x-ui::forms.select name="event_id" label="{{ __('Fair') }}" wire:model.live="event_id" required
                    hint="{{ __('Only fairs that are open for registration are listed.') }}">
                    <option value="">{{ __('Choose a fair') }}</option>
                    @foreach ($this->openFairs as $fair)
                        <option value="{{ $fair->id }}">{{ $this->fairLabel($fair) }}</option>
                    @endforeach
                </x-ui::forms.select>
            @endif
        </x-ui::section>

        <x-ui::section heading="{{ __('Who is staffing the table') }}"
            description="{{ __('Not necessarily you — this is who we contact about this fair.') }}">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui::forms.input name="rep_name" label="{{ __('Name') }}" wire:model="rep_name" required />
                <x-ui::forms.input name="rep_email" type="email" label="{{ __('Email') }}" wire:model="rep_email"
                    required />
                <x-ui::forms.input name="rep_phone" type="tel" label="{{ __('Phone') }}" wire:model="rep_phone" />
            </div>
        </x-ui::section>

        <x-ui::section heading="{{ __('Payment') }}">
            {{-- Displayed, never accepted. There is no price field on this form
                 and no price argument in the service (N1). --}}
            <p class="text-sm text-body">{{ $this->priceSummary }}</p>

            @if ($this->payable)
                <fieldset class="mt-4">
                    <legend class="mb-2.5 block text-sm font-medium text-heading">
                        {{ __('How would you like to pay?') }}
                    </legend>

                    <div class="space-y-2">
                        <x-ui::forms.radio name="payment_method" value="{{ \App\Enums\PaymentMethod::Stripe->value }}"
                            label="{{ __('Card, now') }}" wire:model="payment_method"
                            hint="{{ __('You will be sent to our payment provider. We never see your card details.') }}" />

                        <x-ui::forms.radio name="payment_method" value="{{ \App\Enums\PaymentMethod::Check->value }}"
                            label="{{ __('Check by mail') }}" wire:model="payment_method"
                            hint="{{ __('We will email you a printable form and the address. Your place is held from now; it is confirmed when the check arrives.') }}" />
                    </div>
                </fieldset>
            @endif
        </x-ui::section>

        <x-ui::action-bar>
            <x-ui::button variant="secondary" type="button"
                href="{{ route('portal.registrations') }}">{{ __('Cancel') }}</x-ui::button>

            {{-- Not rendered at all when there is nothing to register for. A
                 disabled submit invites people to hunt for what unlocks it, and
                 @disabled() inside a component tag emits its own endif into the
                 component's wrapper and breaks compilation. --}}
            @if ($this->openFairs->isNotEmpty())
                <x-ui::button type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="submit">{{ __('Finish') }}</span>
                    <span wire:loading wire:target="submit">{{ __('Working…') }}</span>
                </x-ui::button>
            @endif
        </x-ui::action-bar>
    </form>
</div>
