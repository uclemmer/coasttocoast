<div class="space-y-6">
    {{-- Why the buttons elsewhere may be missing. Rendered for pending and
         retired reps, absent for everyone else. --}}
    @if ($this->membershipNotice())
        <x-ui::alert variant="warning">{{ $this->membershipNotice() }}</x-ui::alert>
    @endif

    @if ($this->currentOrganization())
        <x-ui::stat-group>
            <x-ui::stat label="{{ __('Upcoming registrations') }}" :value="$this->upcoming->count()" />
            <x-ui::stat label="{{ __('Fee assistance pending') }}" :value="$this->pendingGrants->count()" />
            <x-ui::stat label="{{ __('Your school') }}" :value="$this->currentOrganization->name" />
        </x-ui::stat-group>
    @endif

    <x-ui::section heading="{{ __('Upcoming fairs') }}">
        @if ($this->upcoming->isNotEmpty())
            <x-ui::table>
                <x-ui::table.head>
                    <x-ui::table.heading>{{ __('Fair') }}</x-ui::table.heading>
                    <x-ui::table.heading>{{ __('Date') }}</x-ui::table.heading>
                    <x-ui::table.heading>{{ __('Status') }}</x-ui::table.heading>
                </x-ui::table.head>

                @foreach ($this->upcoming as $registration)
                    <x-ui::table.row wire:key="upcoming-{{ $registration->id }}">
                        <x-ui::table.cell header>
                            <a href="{{ route('portal.registrations.show', $registration) }}"
                                class="text-fg-brand hover:underline">{{ $registration->event?->name }}</a>
                        </x-ui::table.cell>
                        <x-ui::table.cell>{{ $registration->event?->starts_at?->toFormattedDayDateString() }}</x-ui::table.cell>
                        <x-ui::table.cell>
                            <x-ui::badge>{{ $registration->status->value }}</x-ui::badge>
                        </x-ui::table.cell>
                    </x-ui::table.row>
                @endforeach
            </x-ui::table>
        @else
            <p class="text-sm text-body">
                {{ __('Nothing booked yet.') }}
            </p>
        @endif

        @if ($this->nextUnregisteredFair && $this->actsForOrganization())
            <div class="mt-4">
                <x-ui::button href="{{ route('portal.registrations.create', ['event' => $this->nextUnregisteredFair]) }}">
                    {{ __('Register for :fair', ['fair' => $this->nextUnregisteredFair->name]) }}
                </x-ui::button>
            </div>
        @endif
    </x-ui::section>

    @if ($this->pendingGrants->isNotEmpty())
        <x-ui::section heading="{{ __('Fee assistance') }}"
            description="{{ __('Waiting on a decision. We will email you when there is one.') }}">
            <x-ui::description-list>
                @foreach ($this->pendingGrants as $grant)
                    <x-ui::description-list.item wire:key="grant-{{ $grant->id }}"
                        term="{{ $grant->event?->name }}">
                        {{ __('Requested :when', ['when' => $grant->created_at?->diffForHumans()]) }}
                    </x-ui::description-list.item>
                @endforeach
            </x-ui::description-list>

            <div class="mt-4">
                <x-ui::button variant="secondary" size="sm"
                    href="{{ route('portal.grants') }}">{{ __('See all requests') }}</x-ui::button>
            </div>
        </x-ui::section>
    @endif
</div>
