{{--
    The three decisions, shared by the queue and the detail screen (docs/13).

    Included rather than duplicated because both screens offer all three, and
    the Filament resource kept them in one place for the same reason. The state
    and the service calls live in `Grants\Concerns\DecidesGrants`.

    The amount fields are `wire:model.live` on the benefit choice: Filament
    expressed this as `visible()` plus `required()` on each field, and here the
    same statement is made once — the field appears and its rule is added
    together, from the one chosen benefit.
--}}
<x-ui::modal id="approve-grant" :title="__('Approve this grant')" size="lg">
    <form wire:submit="approve" class="space-y-4">
        <fieldset>
            <legend class="mb-2 text-sm font-medium text-heading">
                {{ __('What is this grant worth?') }}
            </legend>

            <div class="space-y-2">
                @foreach (\App\Enums\GrantBenefit::cases() as $case)
                    <x-ui::forms.radio name="benefitType" wire:model.live="benefitType" :value="$case->value"
                        :label="$case->getLabel()" />
                @endforeach
            </div>

            <x-ui::forms.error name="benefitType" />
        </fieldset>

        @if ($benefitType === \App\Enums\GrantBenefit::CustomPrice->value)
            {{-- Dollars in the box, cents in the column. --}}
            <x-ui::forms.input name="customPriceDollars" wire:model="customPriceDollars" type="number"
                step="0.01" min="0" :label="__('Price this organization pays')" :hint="__('In dollars.')" />
        @endif

        @if ($benefitType === \App\Enums\GrantBenefit::PercentOff->value)
            <x-ui::forms.input name="percentOff" wire:model="percentOff" type="number" min="1" max="100"
                :label="__('Percentage off')" />
        @endif

        <div class="flex items-center gap-3 pt-2">
            <x-ui::button type="submit" variant="success">{{ __('Approve') }}</x-ui::button>
            <x-ui::button type="button" variant="ghost"
                x-on:click="$dispatch('ui-modal-close', { id: 'approve-grant' })">
                {{ __('Cancel') }}
            </x-ui::button>
        </div>
    </form>
</x-ui::modal>

<x-ui::modal id="deny-grant" :title="__('Deny this application')" size="lg">
    <form wire:submit="deny" class="space-y-4">
        {{-- Required, because "denied" with nothing else is how you lose an
             organization for good. The service refuses a blank one too. --}}
        <x-ui::forms.textarea name="reason" wire:model="reason" rows="3" :label="__('Reason')"
            :hint="__('Included in the email the organization receives.')" required />

        <div class="flex items-center gap-3 pt-2">
            <x-ui::button type="submit" variant="danger">{{ __('Deny') }}</x-ui::button>
            <x-ui::button type="button" variant="ghost"
                x-on:click="$dispatch('ui-modal-close', { id: 'deny-grant' })">
                {{ __('Cancel') }}
            </x-ui::button>
        </div>
    </form>
</x-ui::modal>

<x-ui::modal id="revoke-grant" :title="__('Revoke this grant?')" size="lg">
    <form wire:submit="revoke" class="space-y-4">
        <p class="text-sm text-body">
            {{ __('The organization pays list price from now on. Only possible because nothing has used it yet.') }}
        </p>

        <x-ui::forms.textarea name="reason" wire:model="reason" rows="3" :label="__('Reason')"
            :hint="__('Optional.')" />

        <div class="flex items-center gap-3 pt-2">
            <x-ui::button type="submit" variant="danger">{{ __('Revoke') }}</x-ui::button>
            <x-ui::button type="button" variant="ghost"
                x-on:click="$dispatch('ui-modal-close', { id: 'revoke-grant' })">
                {{ __('Cancel') }}
            </x-ui::button>
        </div>
    </form>
</x-ui::modal>
