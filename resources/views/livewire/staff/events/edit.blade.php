{{--
    Add or edit a fair (docs/13).

    The fee is typed in dollars and stored in cents. Both directions live in the
    component, next to each other — see its class note for the mistake that
    convention exists to prevent.
--}}
<div>
    <x-ui::action-bar :heading="$pageHeading">
        <x-ui::button href="{{ route('staff.events') }}" variant="secondary">
            {{ __('Back to fairs') }}
        </x-ui::button>
    </x-ui::action-bar>

    <form wire:submit="save" class="mt-6 max-w-3xl space-y-6">
        <x-ui::section :heading="__('The fair')">
            <div class="grid gap-4 sm:grid-cols-2">
                {{-- `.blur`, not `.live`: the slug is suggested when the name is
                     finished, not re-derived on every keystroke. --}}
                <x-ui::forms.input name="name" wire:model.blur="name" :label="__('Name')" required />

                <x-ui::forms.input name="slug" wire:model="slug" :label="__('Slug')"
                    :hint="__('Used in the public URL: /events/{slug}. Changing it breaks existing links.')"
                    required />

                <x-ui::forms.input name="venue_name" wire:model="venue_name" :label="__('Venue')" required />

                <div class="sm:col-span-2">
                    <x-ui::forms.textarea name="venue_address" wire:model="venue_address" rows="3"
                        :label="__('Venue address')" required />
                </div>
            </div>
        </x-ui::section>

        <x-ui::section :heading="__('When')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui::forms.input name="starts_at" wire:model="starts_at" type="datetime-local"
                    :label="__('Fair opens')" required />
                <x-ui::forms.input name="ends_at" wire:model="ends_at" type="datetime-local"
                    :label="__('Fair closes')" required />
                <x-ui::forms.input name="reception_starts_at" wire:model="reception_starts_at"
                    type="datetime-local" :label="__('Counselor reception starts')"
                    :hint="__('Optional. Leave blank if there is no reception this year.')" />
            </div>
        </x-ui::section>

        <x-ui::section :heading="__('Registration')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui::forms.input name="priceDollars" wire:model="priceDollars" type="number" step="0.01"
                    min="0" :label="__('Registration fee')"
                    :hint="__('In dollars. The list price — approved grants reduce what an individual organization pays.')"
                    required />

                <x-ui::forms.input name="capacity" wire:model="capacity" type="number" min="1"
                    :label="__('Capacity')"
                    :hint="__('Optional. Counts confirmed AND awaiting-payment registrations, so mailed checks cannot oversell the room.')" />

                <x-ui::forms.input name="registration_opens_at" wire:model="registration_opens_at"
                    type="datetime-local" :label="__('Registration opens')"
                    :hint="__('Blank means open as soon as the fair is published.')" />

                <x-ui::forms.input name="registration_closes_at" wire:model="registration_closes_at"
                    type="datetime-local" :label="__('Registration closes')"
                    :hint="__('Blank means it never closes on its own.')" />
            </div>

            <div class="mt-4">
                <x-ui::forms.toggle name="is_published" wire:model="is_published" :label="__('Published')" />
                <p class="mt-1 max-w-prose text-sm text-body">
                    {{ __('An unpublished fair is invisible to the public and cannot accept registrations or money, whatever the window above says.') }}
                </p>
            </div>
        </x-ui::section>

        <div class="flex items-center gap-3">
            <x-ui::button type="submit" variant="brand">{{ __('Save fair') }}</x-ui::button>
            <span wire:loading wire:target="save" class="text-sm text-body">{{ __('Saving…') }}</span>
        </div>
    </form>
</div>
