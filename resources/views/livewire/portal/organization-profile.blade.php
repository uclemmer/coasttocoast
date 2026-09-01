<div class="max-w-3xl">
    <h1 class="mb-6 font-display text-2xl font-bold tracking-tight text-heading">
        {{ $organization?->name ?? __('Your organization') }}
    </h1>

    <form wire:submit="save" class="space-y-6">
        <x-ui::section heading="{{ __('Organization') }}">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-ui::forms.input name="name" label="{{ __('Name') }}" wire:model="name" required />
                </div>

                <x-ui::forms.input name="website" type="url" label="{{ __('Website') }}" wire:model="website" />

                <x-ui::forms.file name="logo" label="{{ __('Logo') }}" wire:model="logo" accept="image/*"
                    hint="{{ __('Shown beside your name on the public roster. Max 2 MB.') }}" />
            </div>

            @if ($organization?->logo_path)
                <p class="mt-3 text-sm text-body">
                    {{ __('Current logo:') }}
                    <img src="{{ asset('storage/' . $organization->logo_path) }}"
                        alt="{{ __('Logo for :organization', ['organization' => $organization->name]) }}"
                        class="mt-1 h-12 w-12 rounded-base object-cover">
                </p>
            @endif
        </x-ui::section>

        <x-ui::section heading="{{ __('Admissions contact') }}"
            description="{{ __('A general address and number for your office. We use these to reach your organization if nobody there has an account with us.') }}">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui::forms.input name="admissions_office" label="{{ __('Office') }}"
                    wire:model="admissions_office" />
                <x-ui::forms.input name="admissions_email" type="email" label="{{ __('Email') }}"
                    wire:model="admissions_email" />
                <x-ui::forms.input name="admissions_phone" type="tel" label="{{ __('Phone') }}"
                    wire:model="admissions_phone" />
            </div>
        </x-ui::section>

        <x-ui::section heading="{{ __('Address') }}">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-ui::forms.input name="address_line1" label="{{ __('Address') }}"
                        wire:model="address_line1" />
                </div>
                <div class="sm:col-span-2">
                    <x-ui::forms.input name="address_line2" label="{{ __('Address line 2') }}"
                        wire:model="address_line2" />
                </div>
                <x-ui::forms.input name="city" label="{{ __('City') }}" wire:model="city" />
                <x-ui::forms.input name="state" label="{{ __('State') }}" wire:model="state" />
                <x-ui::forms.input name="postal_code" label="{{ __('ZIP') }}" wire:model="postal_code" />
            </div>
        </x-ui::section>

        <x-ui::action-bar>
            <x-ui::button type="submit" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save">{{ __('Save') }}</span>
                <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
            </x-ui::button>
        </x-ui::action-bar>
    </form>
</div>
