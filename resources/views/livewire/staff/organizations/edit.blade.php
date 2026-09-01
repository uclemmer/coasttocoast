{{-- Add or edit an organization (docs/13). --}}
<div>
    <x-ui::action-bar :heading="$pageHeading">
        <x-ui::button href="{{ route('staff.organizations') }}" variant="secondary">
            {{ __('Back to organizations') }}
        </x-ui::button>
    </x-ui::action-bar>

    <form wire:submit="save" class="mt-6 max-w-3xl space-y-6">
        <x-ui::section :heading="__('Organization')">
            {{-- `.live`, debounced: the warning is worth having while there is
                 still a chance to stop, not after a second "The Baylor School"
                 has been saved. --}}
            <x-ui::forms.input name="name" wire:model.live.debounce.500ms="name" :label="__('Name')" required />

            {{--
                Surfaced, not blocking (R2.7). "Boston University" and "Boston
                College" normalize differently on purpose, so a match is worth a
                second look rather than a veto — the coordinator knows which
                organizations are genuinely distinct and the normaliser does not.
            --}}
            @if ($this->possibleDuplicates->isNotEmpty())
                <div class="mt-2">
                    <x-ui::alert variant="warning">
                        {{ __('Looks like a duplicate of: :names', [
                            'names' => $this->possibleDuplicates->pluck('name')->join(', '),
                        ]) }}
                    </x-ui::alert>
                </div>
            @endif

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <x-ui::forms.input name="website" wire:model="website" type="url" :label="__('Website')" />
            </div>

            <div class="mt-4">
                @if ($this->isEditing() && $organization->logo_path && ! $removeLogo)
                    <div class="mb-3 flex items-center gap-3">
                        <img src="{{ Storage::disk('public')->url($organization->logo_path) }}" alt=""
                            class="h-12 w-12 rounded-full object-cover">
                        <x-ui::forms.checkbox name="removeLogo" wire:model.live="removeLogo"
                            :label="__('Remove this logo when I save')" />
                    </div>
                @endif

                <x-ui::forms.file name="logo" wire:model="logo" accept="image/*" :label="__('Logo')"
                    :hint="__('Shown on the public roster. Rosters fall back to an initial when there is none. Up to 2 MB.')" />

                <div wire:loading wire:target="logo" class="mt-2 text-sm text-body">{{ __('Uploading…') }}</div>
            </div>
        </x-ui::section>

        <x-ui::section :heading="__('Admissions contact')"
            :description="__('Used when the organization has no active representative — the campaign fallback.')">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui::forms.input name="admissions_office" wire:model="admissions_office" :label="__('Office')" />
                <x-ui::forms.input name="admissions_email" wire:model="admissions_email" type="email"
                    :label="__('Email')" />
                <x-ui::forms.input name="admissions_phone" wire:model="admissions_phone" type="tel"
                    :label="__('Phone')" />
            </div>
        </x-ui::section>

        <x-ui::section :heading="__('Address')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui::forms.input name="address_line1" wire:model="address_line1" :label="__('Address')" />
                <x-ui::forms.input name="address_line2" wire:model="address_line2" :label="__('Address line 2')" />
                <x-ui::forms.input name="city" wire:model="city" :label="__('City')" />
                <x-ui::forms.input name="state" wire:model="state" :label="__('State')" />
                <x-ui::forms.input name="postal_code" wire:model="postal_code" :label="__('ZIP')" />
            </div>
        </x-ui::section>

        <div class="flex items-center gap-3">
            <x-ui::button type="submit" variant="brand">{{ __('Save organization') }}</x-ui::button>
            <span wire:loading wire:target="save" class="text-sm text-body">{{ __('Saving…') }}</span>
        </div>
    </form>
</div>
