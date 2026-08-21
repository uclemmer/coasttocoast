{{--
    Representative sign-up (D9). See App\Livewire\Auth\Register for why the two
    paths differ and why the school picker is a search box rather than a select.

    Rendered as a full-page Livewire component, so Livewire injects its own
    assets here and the layout's @livewireScripts is belt-and-braces rather
    than the thing keeping Alpine alive on this page.
--}}
<div>
    <form wire:submit="register" class="space-y-6">
        {{-- Honeypot. Visually hidden but present: bots skip type="hidden". --}}
        <div class="sr-only" aria-hidden="true">
            <label for="website">{{ __('Leave this field empty') }}</label>
            <input id="website" type="text" wire:model="website" tabindex="-1" autocomplete="off">
        </div>

        <x-ui::section heading="{{ __('About you') }}" level="h2">
            <div class="space-y-4">
                <x-ui::forms.input name="name" label="{{ __('Your name') }}" wire:model="name" required
                    autocomplete="name" />

                <x-ui::forms.input name="email" type="email" label="{{ __('Email') }}" wire:model="email" required
                    autocomplete="email" />

                <x-ui::forms.input name="phone" type="tel" label="{{ __('Phone') }}" wire:model="phone"
                    autocomplete="tel"
                    hint="{{ __('Optional. Used for fair-day logistics only, and only if you opt in later.') }}" />

                <x-ui::forms.input name="password" type="password" label="{{ __('Password') }}"
                    wire:model="password" required autocomplete="new-password" />

                <x-ui::forms.input name="password_confirmation" type="password"
                    label="{{ __('Confirm password') }}" wire:model="password_confirmation" required
                    autocomplete="new-password" />
            </div>
        </x-ui::section>

        <x-ui::section heading="{{ __('Your school') }}" level="h2">
            <div class="space-y-4">
                <fieldset>
                    <legend class="mb-2.5 block text-sm font-medium text-heading">
                        {{ __('Is your school already registered with us?') }}
                    </legend>

                    <div class="space-y-2">
                        <x-ui::forms.radio name="organization_choice" value="claim"
                            label="{{ __('Yes — find it in the list') }}" wire:model.live="organization_choice" />
                        <x-ui::forms.radio name="organization_choice" value="create"
                            label="{{ __('No — add it') }}" wire:model.live="organization_choice" />
                    </div>
                </fieldset>

                {{-- ── Claim an existing school ─────────────────────────── --}}
                @if ($organization_choice === 'claim')
                    @if ($this->chosen)
                        <div
                            class="flex items-center justify-between gap-3 rounded-base border border-default bg-brand-softer p-3">
                            <p class="text-sm text-heading">
                                <span class="font-medium">{{ $this->chosen->name }}</span>
                                <span class="block text-ink-600">
                                    {{ __('Your account will be active once the fair coordinator confirms you work there.') }}
                                </span>
                            </p>

                            <x-ui::button type="button" variant="secondary" size="sm"
                                wire:click="clearChoice">{{ __('Change') }}</x-ui::button>
                        </div>
                    @else
                        <x-ui::forms.input name="organization_search" label="{{ __('Find your school') }}"
                            wire:model.live.debounce.300ms="organization_search"
                            placeholder="{{ __('Start typing a school name') }}"
                            hint="{{ __('Search by name, then choose your school from the results.') }}" />

                        @if ($this->matches->isNotEmpty())
                            <x-ui::list-group>
                                @foreach ($this->matches as $match)
                                    <x-ui::list-group.item wire:key="org-{{ $match->id }}"
                                        wire:click="choose({{ $match->id }})" class="cursor-pointer text-left">
                                        {{ $match->name }}
                                    </x-ui::list-group.item>
                                @endforeach
                            </x-ui::list-group>
                        @elseif (strlen(trim($organization_search)) >= 2)
                            <p class="text-sm text-ink-600">
                                {{ __('No schools match that. If yours is not listed, choose "No — add it" above.') }}
                            </p>
                        @endif

                        {{-- The id is what actually gets validated, and it has
                             no visible input of its own, so its error needs a
                             home. --}}
                        <x-ui::forms.error name="organization_id" />
                    @endif
                @endif

                {{-- ── Add a new school ─────────────────────────────────── --}}
                @if ($organization_choice === 'create')
                    <x-ui::forms.input name="organization_name" label="{{ __('School name') }}"
                        wire:model.live.blur="organization_name" required />

                    @if ($this->duplicateWarning->isNotEmpty())
                        {{-- Warns, never blocks (R2.7). --}}
                        <x-ui::alert variant="warning">
                            {{ __('We already have :names. If that is your school, choose "Yes" above instead.', ['names' => $this->duplicateWarning->join(', ')]) }}
                        </x-ui::alert>
                    @endif

                    <x-ui::forms.input name="organization_website" type="url"
                        label="{{ __('School website') }}" wire:model="organization_website" />

                    <x-ui::forms.input name="organization_admissions_email" type="email"
                        label="{{ __('Admissions office email') }}" wire:model="organization_admissions_email"
                        hint="{{ __('A general address we can use if nobody from your school has an account with us.') }}" />
                @endif
            </div>
        </x-ui::section>

        <div class="flex items-center justify-between gap-3">
            <a href="{{ route('core.login') }}" class="text-sm text-fg-brand hover:underline">
                {{ __('Already have an account?') }}
            </a>

            <x-ui::button type="submit" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="register">{{ __('Create account') }}</span>
                <span wire:loading wire:target="register">{{ __('Creating…') }}</span>
            </x-ui::button>
        </div>
    </form>
</div>
