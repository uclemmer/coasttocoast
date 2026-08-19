{{--
    The contact form itself, with no page chrome (card 8.4).

    Embedded twice: in the landing page's contact section and on the contact
    page. One component, so the two cannot drift — the design shows the same
    form in both places.
--}}
<div>
    @if ($sent)
        <x-ui.alert variant="success" :title="__('Thanks — your message is with us.')">
            {{ __('You should have a confirmation by email, and the coordinator will reply directly.') }}
        </x-ui.alert>
    @else
        <form wire:submit="submit" class="grid gap-3.5" novalidate>
            <x-ui.field :label="__('Name')" name="name" required
                        :placeholder="__('Your name')"
                        :error="$errors->first('name')"
                        wire:model="name" autocomplete="name" />

            <x-ui.field :label="__('Email')" name="email" type="email" required
                        placeholder="you@college.edu"
                        :error="$errors->first('email')"
                        wire:model="email" autocomplete="email" />

            <x-ui.field :label="__('Institution')" name="institution"
                        :placeholder="__('College or university')"
                        :error="$errors->first('institution')"
                        wire:model="institution" autocomplete="organization" />

            <x-ui.field :label="__('Message')" name="message" required
                        :error="$errors->first('message')">
                <textarea id="message"
                          wire:model="message"
                          required
                          placeholder="{{ __('How can we help?') }}"
                          @class([
                              'min-h-[110px] w-full resize-y rounded-lg border-[1.5px] px-3.5 py-3 font-sans text-[15.5px] text-ink-800 placeholder:text-placeholder focus:outline-none focus:ring-0',
                              'border-danger bg-danger-100 focus:border-danger' => $errors->has('message'),
                              'border-field-border bg-field-bg focus:border-brand-600' => ! $errors->has('message'),
                          ])></textarea>
            </x-ui.field>

            <label class="flex items-start gap-2.5 text-[15px] leading-[1.6] text-ink-600">
                <input type="checkbox"
                       wire:model="consent"
                       class="mt-1 h-[17px] w-[17px] shrink-0 rounded border-field-border accent-brand-600">
                <span>{{ __('I understand that my message will be stored so the fair can reply to it.') }}</span>
            </label>

            @error('consent')
                <p class="-mt-2 text-[13.5px] text-danger-dark">{{ $message }}</p>
            @enderror

            {{-- Visually hidden rather than `type="hidden"`: bots skip hidden
                 inputs and fill visible ones, so a honeypot has to be present
                 and off-screen. --}}
            <div class="absolute -left-[9999px]" aria-hidden="true">
                <label for="website">{{ __('Leave this field empty') }}</label>
                <input type="text" id="website" wire:model="website" tabindex="-1" autocomplete="off">
            </div>

            <x-ui.button type="submit" class="justify-self-start" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="submit">{{ __('Send message') }}</span>
                <span wire:loading wire:target="submit">{{ __('Sending…') }}</span>
            </x-ui.button>
        </form>
    @endif
</div>
