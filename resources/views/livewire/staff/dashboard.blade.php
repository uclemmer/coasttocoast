{{--
    The staff landing page (docs/13) — replaces the two Filament widgets.

    Laid out from the design handoff's "Admin Dashboard.dc.html" (docs/16):
    stat cards across the top, then a main column of chart-then-table beside a
    360px rail. The design draws that as a Filament panel; this app has had no
    Filament since 2026-08-22, so what it contributes is the information design
    — what a coordinator should see first, and next to what.

    Two of the design's rail cards are NOT here, and their absence is the
    decision rather than an omission. "Tasks" is a to-do list and "Activity" is
    an audit feed; neither has a table behind it, and both are drawn with sample
    data. Building the widget first and inventing the data to fill it is how a
    dashboard ends up lying.
--}}
<div>
    @if ($this->fair === null)
        <x-ui::alert variant="info">
            {{ __('No fair is active. Publish one to see its numbers here.') }}
            <a href="{{ route('staff.events') }}" class="font-medium underline">{{ __('Go to fairs') }}</a>
        </x-ui::alert>
    @else
        @php($numbers = $this->numbers)

        @php($interest = $this->interestList)

        <x-ui::stat-group :columns="4">
            <x-ui::stat :label="__('Confirmed organizations')" :value="(string) $numbers['confirmed']"
                :description="$this->fair->capacity === null
                    ? $this->fair->name
                    : __(':left of :capacity places left', [
                        'left' => $this->fair->remainingCapacity(),
                        'capacity' => $this->fair->capacity,
                    ])"
                :sentiment="$this->fair->isFull() ? 'bad' : 'good'" />

            {{-- From registrations, not the payments table: this is the price
                 each organization was quoted and confirmed against. --}}
            <x-ui::stat :label="__('Collected')" :value="$this->formatMoney($numbers['collected'])"
                :description="__('Confirmed registrations, at the price each organization was quoted')"
                sentiment="good" />

            {{-- Checks are money in the post, not money lost. --}}
            <x-ui::stat :label="__('Awaiting payment')" :value="$this->formatMoney($numbers['awaited'])"
                :description="trans_choice(
                    ':count of these is a check in the post|:count of these are checks in the post',
                    $numbers['awaitingChecks'],
                    ['count' => $numbers['awaitingChecks']],
                )"
                :sentiment="$numbers['awaited'] > 0 ? 'bad' : 'neutral'" />

            {{-- Scoped to the active fair like the three beside it. The
                 headline is the count still waiting, not the total, because
                 that is the set the fair page's announcement would mail.

                 No link on the card: `x-ui::stat` is pure markup with no href,
                 and the other three are not links either. There are already two
                 ways to the list — the sidebar, and the fair page's own link to
                 this fair's waiting set. --}}
            <x-ui::stat :label="__('Waiting to be told')" :value="(string) $interest['waiting']"
                :description="trans_choice(
                    '{0}Nobody has asked about this fair yet|{1}:total person on the notify-me list for this fair|[2,*]:total people on the notify-me list for this fair',
                    $interest['total'],
                    ['total' => $interest['total']],
                )"
                sentiment="neutral" />
        </x-ui::stat-group>

        {{-- The card above reports the number; this says it is now a job. The
             condition is registration being open, not the fair merely being
             published, because the announcement's own words are "It is open
             now" — see the component. --}}
        @if ($this->shouldAnnounceRegistration)
            <div class="mt-6">
                <x-ui::alert variant="info">
                    {{ trans_choice(
                        'Registration is open and :count person on the notify-me list has not been told.|Registration is open and :count people on the notify-me list have not been told.',
                        $interest['waiting'],
                        ['count' => $interest['waiting']],
                    ) }}
                    <a href="{{ route('staff.events.show', $this->fair) }}"
                        class="font-medium underline">{{ __('Tell them') }}</a>
                    <a href="{{ route('staff.interests', ['eventId' => $this->fair->id, 'status' => 'waiting']) }}"
                        class="font-medium underline">{{ __('or see who they are') }}</a>
                </x-ui::alert>
            </div>
        @endif

        @if ($this->pendingGrants > 0)
            <div class="mt-6">
                <x-ui::alert variant="warning">
                    {{ trans_choice(
                        ':count fee-assistance application is waiting on a decision.|:count fee-assistance applications are waiting on a decision.',
                        $this->pendingGrants,
                        ['count' => $this->pendingGrants],
                    ) }}
                    <a href="{{ route('staff.grants') }}" class="font-medium underline">{{ __('Review them') }}</a>
                </x-ui::alert>
            </div>
        @endif

        {{-- `minmax(0,1fr)` on the main column, not `1fr`: a grid track's
             default minimum is its content, and the table inside this one is
             wide enough to push the rail off the screen without it. --}}
        <div class="mt-8 grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="flex min-w-0 flex-col gap-8">
                {{--
                    The design colours the current week's bar differently from
                    the eleven behind it. `x-ui::chart.bars` colours a series,
                    not a bar, so that highlight is dropped rather than
                    hand-rolling a second chart — the package component is the
                    workspace's first reach, and a per-bar colour is a gap to
                    raise there if anyone misses it. Little is lost from the
                    reading: the current week is already the one on the right.

                    `:max` is left to the component. Passing one matters when
                    two charts sit side by side, and there is only ever one here.
                --}}
                <x-ui::chart.bars :title="__('Registrations per week')"
                    :description="__('Last :count weeks', ['count' => 12])"
                    :height="150"
                    :labels="$this->weeklyRegistrations['labels']"
                    :series="[[
                        'label' => __('Registrations'),
                        'color' => 'brand',
                        'values' => $this->weeklyRegistrations['values'],
                    ]]"
                    :empty-message="__('Nobody has registered yet')" />

                <div>
                    <x-ui::action-bar :heading="__('Recent registrations')" level="h2"
                        :description="$this->fair->name">
                        <x-ui::button href="{{ route('staff.registrations') }}" variant="secondary" size="sm">
                            {{ __('All registrations') }}
                        </x-ui::button>
                    </x-ui::action-bar>

                    <div class="mt-4">
                        <x-ui::table>
                            <x-ui::table.head>
                                <x-ui::table.heading>{{ __('Organization') }}</x-ui::table.heading>
                                <x-ui::table.heading>{{ __('Status') }}</x-ui::table.heading>
                                <x-ui::table.heading>{{ __('Payment') }}</x-ui::table.heading>
                                <x-ui::table.heading>{{ __('Price') }}</x-ui::table.heading>
                                <x-ui::table.heading>{{ __('When') }}</x-ui::table.heading>
                            </x-ui::table.head>

                            @forelse ($this->recent as $registration)
                                <x-ui::table.row wire:key="recent-{{ $registration->id }}">
                                    <x-ui::table.cell header>{{ $registration->organization?->name }}</x-ui::table.cell>

                                    <x-ui::table.cell>
                                        <x-ui::badge :variant="match ($registration->status) {
                                            \App\Enums\RegistrationStatus::Confirmed => 'success',
                                            \App\Enums\RegistrationStatus::PendingPayment => 'warning',
                                            default => 'gray',
                                        }">{{ $registration->status->getLabel() }}</x-ui::badge>
                                    </x-ui::table.cell>

                                    <x-ui::table.cell>
                                        {{ $registration->payment_method?->getLabel() ?? __('Free') }}
                                    </x-ui::table.cell>

                                    <x-ui::table.cell>
                                        {{ $this->formatMoney($registration->price_cents) }}
                                        @if ($registration->grant)
                                            <span class="block text-sm text-body">
                                                {{ $registration->grant->benefitSummary() }}
                                            </span>
                                        @endif
                                    </x-ui::table.cell>

                                    <x-ui::table.cell>{{ $registration->created_at?->diffForHumans() }}</x-ui::table.cell>
                                </x-ui::table.row>
                            @empty
                                <x-ui::table.row>
                                    <x-ui::table.empty-state :colspan="5" :heading="__('Nobody has registered yet')" />
                                </x-ui::table.row>
                            @endforelse
                        </x-ui::table>
                    </div>
                </div>
            </div>

            <aside class="flex flex-col gap-6">
                {{-- The rail's countdown card. Whole days, not the public page's
                     ticking clock: the coordinator is reading a deadline, not
                     watching one. --}}
                <div class="rounded-lg bg-brand-600 px-6 py-[22px] text-white">
                    <p class="text-[12.5px] font-semibold uppercase tracking-[0.06em] text-brand-500">
                        {{ __('Event countdown') }}
                    </p>

                    @if ($this->daysUntilFair === null)
                        <p class="mt-2 font-display text-[34px] font-extrabold leading-none">
                            {{ __('Concluded') }}
                        </p>
                        <p class="mt-2 text-sm text-brand-400">
                            {{ __('Publish next spring\'s fair to restart the clock.') }}
                        </p>
                    @else
                        <p class="mt-2 font-display text-[34px] font-extrabold leading-none">
                            {{ trans_choice(':count day|:count days', $this->daysUntilFair, [
                                'count' => $this->daysUntilFair,
                            ]) }}
                        </p>
                        <p class="mt-2 text-sm text-brand-400">
                            {{ __('until :date', ['date' => $this->fair->starts_at->format('F j, Y · g:i a')]) }}
                        </p>
                    @endif

                    <x-ui.button variant="on-green" class="mt-4 px-4 py-2.5 text-[12.5px]"
                        :href="route('staff.events.edit', $this->fair)">
                        {{ __('Edit event settings') }}
                    </x-ui.button>
                </div>
            </aside>
        </div>
    @endif
</div>
