{{--
    Write or edit one FAQ question (docs/13).

    The preview renders through the same `Str::markdown()` the public FAQ page
    uses, so what is typed here and what a visitor reads cannot drift. The
    editor is a styled textarea, not a rich editor — the owner's call when the
    package was built (docs/12) — which is exactly why a preview earns its
    place.
--}}
<div>
    <x-ui::action-bar :heading="$pageHeading">
        <x-ui::button href="{{ route('staff.faq') }}" variant="secondary">
            {{ __('Back to the FAQ') }}
        </x-ui::button>
    </x-ui::action-bar>

    <form wire:submit="save" class="mt-6 max-w-3xl">
        <x-ui::section>
            <x-ui::forms.input name="question" wire:model="question" :label="__('Question')" required />

            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <x-ui::forms.markdown name="answer" wire:model.live.debounce.500ms="answer" :label="__('Answer')"
                    rows="14" required />

                <div>
                    <span class="mb-2 block text-sm font-medium text-heading">{{ __('Preview') }}</span>
                    {{-- `aria-live` off on purpose: this updates on a debounce
                         while typing, and announcing every keystroke's worth of
                         re-rendered prose would be unusable. --}}
                    <div class="rounded-base border border-default bg-neutral-primary-soft p-4">
                        @if (trim($answer) === '')
                            <p class="text-sm text-body">{{ __('Nothing to preview yet.') }}</p>
                        @else
                            <x-ui.prose :html="$this->preview()" class="text-[15px]" />
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <x-ui::forms.toggle name="is_published" wire:model="is_published" :label="__('Published')" />
                <p class="mt-1 text-sm text-body">
                    {{ __('Unpublished questions stay here but disappear from the public page.') }}
                </p>
            </div>
        </x-ui::section>

        <div class="mt-6 flex items-center gap-3">
            <x-ui::button type="submit" variant="brand">{{ __('Save question') }}</x-ui::button>
            <span wire:loading wire:target="save" class="text-sm text-body">{{ __('Saving…') }}</span>
        </div>
    </form>
</div>
