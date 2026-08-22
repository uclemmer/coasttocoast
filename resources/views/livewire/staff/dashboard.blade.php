{{-- The staff landing page (docs/13) — replaces the two Filament widgets. --}}
<div>
    @if ($this->fair === null)
        <x-ui::alert variant="info">
            {{ __('No fair is active. Publish one to see its numbers here.') }}
            <a href="{{ route('staff.events') }}" class="font-medium underline">{{ __('Go to fairs') }}</a>
        </x-ui::alert>
    @else
        @php($numbers = $this->numbers)

        <x-ui::stat-group :columns="3">
            <x-ui::stat :label="__('Confirmed schools')" :value="(string) $numbers['confirmed']"
                :description="$this->fair->capacity === null
                    ? $this->fair->name
                    : __(':left of :capacity places left', [
                        'left' => $this->fair->remainingCapacity(),
                        'capacity' => $this->fair->capacity,
                    ])"
                :sentiment="$this->fair->isFull() ? 'bad' : 'good'" />

            {{-- From registrations, not the payments table: this is the price
                 each school was quoted and confirmed against. --}}
            <x-ui::stat :label="__('Collected')" :value="$this->formatMoney($numbers['collected'])"
                :description="__('Confirmed registrations, at the price each school was quoted')"
                sentiment="good" />

            {{-- Checks are money in the post, not money lost. --}}
            <x-ui::stat :label="__('Awaiting payment')" :value="$this->formatMoney($numbers['awaited'])"
                :description="trans_choice(
                    ':count of these is a check in the post|:count of these are checks in the post',
                    $numbers['awaitingChecks'],
                    ['count' => $numbers['awaitingChecks']],
                )"
                :sentiment="$numbers['awaited'] > 0 ? 'bad' : 'neutral'" />
        </x-ui::stat-group>

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

        <div class="mt-8">
            <x-ui::action-bar :heading="__('Recent registrations')" level="h2"
                :description="$this->fair->name">
                <x-ui::button href="{{ route('staff.registrations') }}" variant="secondary" size="sm">
                    {{ __('All registrations') }}
                </x-ui::button>
            </x-ui::action-bar>

            <div class="mt-4">
                <x-ui::table>
                    <x-ui::table.head>
                        <x-ui::table.heading>{{ __('School') }}</x-ui::table.heading>
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
    @endif
</div>
