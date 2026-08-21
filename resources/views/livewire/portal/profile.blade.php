<div class="max-w-2xl space-y-6">
    <form wire:submit="save" class="space-y-6">
        <x-ui::section heading="{{ __('About you') }}">
            <div class="space-y-4">
                <x-ui::forms.input name="name" label="{{ __('Your name') }}" wire:model="name" required
                    autocomplete="name" />

                <x-ui::forms.input name="email" type="email" label="{{ __('Email') }}" wire:model="email" required
                    autocomplete="email"
                    hint="{{ __('Changing this asks you to confirm the new address before it takes effect.') }}" />

                <x-ui::forms.input name="phone" type="tel" label="{{ __('Phone') }}" wire:model="phone"
                    autocomplete="tel"
                    hint="{{ __('Used only for fair-day logistics, and only if you turn texts on below.') }}" />

                {{-- Having a number is not consent (N3): the opt-in is its own
                     act, and off unless chosen. --}}
                <x-ui::forms.toggle name="sms_opt_in" label="{{ __('Text me fair-day reminders') }}"
                    wire:model="sms_opt_in"
                    hint="{{ __('Parking, check-in and shipping details on the day. Nothing else, ever.') }}" />
            </div>
        </x-ui::section>

        <x-ui::section heading="{{ __('Password') }}"
            description="{{ __('Leave both boxes empty to keep your current password.') }}">
            <div class="space-y-4">
                <x-ui::forms.input name="password" type="password" label="{{ __('New password') }}"
                    wire:model="password" autocomplete="new-password" />

                <x-ui::forms.input name="password_confirmation" type="password"
                    label="{{ __('Confirm new password') }}" wire:model="password_confirmation"
                    autocomplete="new-password" />
            </div>
        </x-ui::section>

        <x-ui::action-bar>
            <x-ui::button type="submit" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save">{{ __('Save changes') }}</span>
                <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
            </x-ui::button>
        </x-ui::action-bar>
    </form>

    {{-- Retiring is destructive and permanent-feeling, so it sits apart from
         the save button rather than beside it. --}}
    @if ($this->actsForOrganization())
        <x-ui::section heading="{{ __('Stepping down') }}">
            <p class="text-sm text-body">
                {{ __('If you no longer represent :school, you can step down. You keep your account and its history.', ['school' => $this->currentOrganization()?->name]) }}
            </p>

            <div class="mt-4">
                <x-ui::button variant="danger" type="button"
                    x-on:click="$dispatch('ui-modal-open', { id: 'confirm-retire' })">
                    {{ __('I no longer represent this school') }}
                </x-ui::button>
            </div>
        </x-ui::section>

        <x-ui::confirm-modal id="confirm-retire" title="{{ __('Step down as a representative?') }}"
            confirm="{{ __('Step down') }}" wire:click="retire">
            {{ __("You will keep your account and be able to see :school's past registrations, but you will no longer be able to register it, apply for grants, or edit its details. The coordinator can undo this.", ['school' => $this->currentOrganization()?->name]) }}
        </x-ui::confirm-modal>
    @endif
</div>
