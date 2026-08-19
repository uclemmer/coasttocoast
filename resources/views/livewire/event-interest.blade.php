<div>
    @if ($sent)
        <x-ui.alert variant="success">
            {{ __('Thanks — we will email you as soon as registration opens.') }}
        </x-ui.alert>
    @else
        <form wire:submit="submit" class="grid gap-3.5" novalidate>
            <x-ui.field :label="__('Your email')" name="interest-email" type="email" required
                        placeholder="you@college.edu"
                        :error="$errors->first('email')"
                        wire:model="email" autocomplete="email" />

            <x-ui.field :label="__('Your institution')" name="interest-organization"
                        :placeholder="__('College or university')"
                        :hint="__('Optional.')"
                        :error="$errors->first('organizationName')"
                        wire:model="organizationName" autocomplete="organization" />

            {{-- Visually hidden rather than `type="hidden"`: bots skip hidden
                 inputs and fill visible ones. --}}
            <div class="absolute -left-[9999px]" aria-hidden="true">
                <label for="interest-website">{{ __('Leave this field empty') }}</label>
                <input type="text" id="interest-website" wire:model="website" tabindex="-1" autocomplete="off">
            </div>

            <x-ui.button type="submit" class="justify-self-start" wire:loading.attr="disabled">
                {{ __('Tell me when registration opens') }}
            </x-ui.button>
        </form>
    @endif
</div>
