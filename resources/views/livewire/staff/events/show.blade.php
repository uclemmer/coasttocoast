{{-- One fair in full, and the announcement (docs/13). --}}
@php($event = $this->record)

<div>
    <x-ui::action-bar :heading="$event->name" :description="$event->venue_name">
        <x-ui::button href="{{ route('staff.events') }}" variant="secondary">
            {{ __('Back to fairs') }}
        </x-ui::button>
        <x-ui::button href="{{ route('staff.events.edit', $event) }}" variant="secondary">
            {{ __('Edit') }}
        </x-ui::button>

        @if ($this->canAnnounce())
            <x-ui::button variant="brand" wire:click="confirmAnnounce">
                {{ __('Tell the interest list') }}
            </x-ui::button>
        @endif
    </x-ui::action-bar>

    @unless ($event->is_published)
        <div class="mt-4">
            <x-ui::alert variant="warning">
                {{ __('This fair is a draft. It is invisible to the public and cannot accept registrations or money.') }}
            </x-ui::alert>
        </div>
    @endunless

    <div class="mt-6 max-w-3xl">
        <x-ui::section :heading="__('Details')">
            <x-ui::description-list :columns="2">
                <x-ui::description-list.item :term="__('Slug')" :description="$event->slug" />

                <x-ui::description-list.item :term="__('Published')">
                    <x-ui::badge :variant="$event->is_published ? 'success' : 'gray'">
                        {{ $event->is_published ? __('Published') : __('Draft') }}
                    </x-ui::badge>
                </x-ui::description-list.item>

                <x-ui::description-list.item :term="__('Fair opens')"
                    :description="$event->starts_at?->toDayDateTimeString()" />
                <x-ui::description-list.item :term="__('Fair closes')"
                    :description="$event->ends_at?->toDayDateTimeString()" />
                <x-ui::description-list.item :term="__('Counselor reception')"
                    :description="$event->reception_starts_at?->toDayDateTimeString() ?? __('None this year')" />

                <x-ui::description-list.item :term="__('Registration fee')"
                    :description="\App\Support\Money::format($event->price_cents)" />
                <x-ui::description-list.item :term="__('Capacity')"
                    :description="$event->capacity === null ? __('No cap') : (string) $event->capacity" />

                <x-ui::description-list.item :term="__('Registration opens')"
                    :description="$event->registration_opens_at?->toDayDateTimeString() ?? __('No opening date')" />
                <x-ui::description-list.item :term="__('Registration closes')"
                    :description="$event->registration_closes_at?->toDayDateTimeString() ?? __('No closing date')" />

                <x-ui::description-list.item :term="__('Venue address')" :description="$event->venue_address" />
            </x-ui::description-list>
        </x-ui::section>
    </div>

    {{--
        The announcement. Safe to press twice by design: only people with no
        `notified_at` are sent to, and each is stamped as the mail goes out — so
        a coordinator unsure whether the first press worked can just press
        again, rather than risking a hundred duplicates.
    --}}
    <x-ui::confirm-modal id="announce-registration" :title="__('Announce that registration is open?')"
        :confirm="__('Send it')" variant="brand" wire:click="announce">
        {{ trans_choice(
            '{0}Nobody is waiting to be told.|{1}One person asked to be told about this fair and has not been.|[2,*]:count people asked to be told about this fair and have not been.',
            $this->waitingCount,
            ['count' => $this->waitingCount],
        ) }}
        {{ __('Anyone already told is skipped, so this is safe to press twice.') }}
    </x-ui::confirm-modal>
</div>
