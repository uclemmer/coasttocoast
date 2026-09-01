{{--
    Manual entry (docs/13) — a phone call, a form that arrived in the post.

    Goes through RegistrationService, not straight to the model, so the same
    rules the portal follows apply: duplicates refused, price read from the fair
    and any approved grant.
--}}
<div>
    <x-ui::action-bar :heading="__('Add a manual registration')"
        :description="__('Goes through the same rules as the portal, minus the membership and window checks.')">
        <x-ui::button href="{{ route('staff.registrations') }}" variant="secondary">
            {{ __('Back to registrations') }}
        </x-ui::button>
    </x-ui::action-bar>

    <form wire:submit="save" class="mt-6 max-w-3xl space-y-6">
        <x-ui::section :heading="__('Which organization, which fair')"
            :description="__('Neither can be changed afterwards: moving a registration to another fair would carry a price nobody agreed for it.')">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui::forms.select name="event_id" wire:model="event_id" :label="__('Fair')" required>
                    <option value="">{{ __('Choose a fair…') }}</option>
                    @foreach ($this->fairs as $fair)
                        <option value="{{ $fair->id }}">{{ $fair->name }}</option>
                    @endforeach
                </x-ui::forms.select>

                <x-ui::forms.select name="organization_id" wire:model="organization_id" :label="__('Organization')" required>
                    <option value="">{{ __('Choose an organization…') }}</option>
                    @foreach ($this->organizations as $organization)
                        <option value="{{ $organization->id }}">{{ $organization->name }}</option>
                    @endforeach
                </x-ui::forms.select>

                <x-ui::forms.select name="payment_method" wire:model="payment_method" :label="__('Payment method')"
                    :hint="__('Leave blank only if an approved grant makes this free.')">
                    <option value="">{{ __('None') }}</option>
                    @foreach ($this->paymentMethods() as $case)
                        <option value="{{ $case->value }}">{{ $case->getLabel() }}</option>
                    @endforeach
                </x-ui::forms.select>
            </div>
        </x-ui::section>

        <x-ui::section :heading="__('Fair contact')"
            :description="__('Who is staffing the table. Not necessarily the account holder.')">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui::forms.input name="rep_name" wire:model="rep_name" :label="__('Name')" required />
                <x-ui::forms.input name="rep_email" wire:model="rep_email" type="email" :label="__('Email')" required />
                <x-ui::forms.input name="rep_phone" wire:model="rep_phone" type="tel" :label="__('Phone')" />
            </div>
        </x-ui::section>

        <x-ui::section :heading="__('Notes')">
            <x-ui::forms.textarea name="notes" wire:model="notes" rows="4" :label="__('Internal notes')"
                :hint="__('Not shown to the organization.')" />
        </x-ui::section>

        <div class="flex items-center gap-3">
            <x-ui::button type="submit" variant="brand">{{ __('Add the registration') }}</x-ui::button>
            <span wire:loading wire:target="save" class="text-sm text-body">{{ __('Adding…') }}</span>
        </div>
    </form>
</div>
