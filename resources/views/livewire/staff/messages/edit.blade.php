{{--
    Compose a campaign (docs/13).

    The count under the audience picker is live, so changing the audience shows
    the number before anybody commits to it. It is labelled a guide because the
    audience is resolved again when the campaign sends — a number that turns out
    to be stale is worse than one that said it might be.
--}}
<div>
    <x-ui::action-bar :heading="$pageHeading">
        <x-ui::button href="{{ route('staff.messages') }}" variant="secondary">
            {{ __('Back to campaigns') }}
        </x-ui::button>
    </x-ui::action-bar>

    <form wire:submit="save" class="mt-6 max-w-3xl space-y-6">
        <x-ui::section :heading="__('Who')">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui::forms.select name="event_id" wire:model.live="event_id" :label="__('Reference fair')"
                    :hint="__('The fair the audience is measured against. Leave blank to use the active one.')">
                    <option value="">{{ __('Active fair') }}</option>
                    @foreach ($this->fairs as $fair)
                        <option value="{{ $fair->id }}">{{ $fair->name }}</option>
                    @endforeach
                </x-ui::forms.select>

                <x-ui::forms.select name="audience" wire:model.live="audience" :label="__('Audience')" required>
                    <option value="">{{ __('Choose an audience…') }}</option>
                    @foreach (\App\Enums\Audience::cases() as $case)
                        <option value="{{ $case->value }}">{{ $case->getLabel() }}</option>
                    @endforeach
                </x-ui::forms.select>

                <div>
                    <span class="mb-2 block text-sm font-medium text-heading">{{ __('This will reach') }}</span>
                    <p class="text-2xl font-semibold text-heading" aria-live="polite">{{ $this->previewCount }}</p>
                    <p class="mt-1 text-sm text-body">
                        {{ __('Recalculated again when the campaign actually sends, so this is a guide.') }}
                    </p>
                </div>
            </div>

            {{-- "Lapsed" means nothing to a coordinator until somebody says
                 what it means. --}}
            @if ($this->audienceDescription())
                <p class="mt-3 max-w-prose text-sm text-body">{{ $this->audienceDescription() }}</p>
            @endif
        </x-ui::section>

        <x-ui::section :heading="__('What')">
            <x-ui::forms.input name="subject" wire:model="subject" :label="__('Subject')" required />

            <div class="mt-4">
                <x-ui::forms.checkbox-list name="channels" :label="__('Send by')">
                    @foreach (\App\Enums\MessageChannel::cases() as $case)
                        <x-ui::forms.checkbox-list.item name="channels" wire:model.live="channels"
                            :value="$case->value" :label="$case->getLabel()" />
                    @endforeach
                </x-ui::forms.checkbox-list>
            </div>

            {{-- Each body appears exactly when its channel is chosen, and its
                 rule is added at the same moment. One statement, in the
                 component. --}}
            @if ($this->sendsBy(\App\Enums\MessageChannel::Email->value))
                <div class="mt-4">
                    <x-ui::forms.markdown name="email_body" wire:model="email_body" :label="__('Email')"
                        rows="12" required />
                </div>
            @endif

            @if ($this->sendsBy(\App\Enums\MessageChannel::Sms->value))
                <div class="mt-4">
                    <x-ui::forms.textarea name="sms_body" wire:model="sms_body" rows="3" maxlength="320"
                        :label="__('Text message')"
                        :hint="__('Only reaches people who opted in to texts, and only about fair-day logistics. 320 characters.')"
                        required />
                </div>
            @endif
        </x-ui::section>

        <x-ui::section :heading="__('When')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui::forms.input name="scheduled_for" wire:model="scheduled_for" type="datetime-local"
                    :label="__('Scheduled for')"
                    :hint="__('Optional. A campaign is still sent by hand from its own page.')" />
            </div>
        </x-ui::section>

        <div class="flex items-center gap-3">
            <x-ui::button type="submit" variant="brand">{{ __('Save campaign') }}</x-ui::button>
            <span wire:loading wire:target="save" class="text-sm text-body">{{ __('Saving…') }}</span>
        </div>
    </form>
</div>
