<div class="space-y-6">
    @if ($this->membershipNotice())
        <x-ui::alert variant="warning">{{ $this->membershipNotice() }}</x-ui::alert>
    @endif

    <x-ui::table>
        <x-slot:before>
            <x-ui::table.toolbar>
                @if ($this->canRegister)
                    <x-ui::button href="{{ route('portal.registrations.create') }}">
                        {{ __('Register for a fair') }}
                    </x-ui::button>
                @endif
            </x-ui::table.toolbar>
        </x-slot:before>

        <x-ui::table.head>
            <x-ui::table.heading>{{ __('Fair') }}</x-ui::table.heading>
            <x-ui::table.heading>{{ __('Date') }}</x-ui::table.heading>
            <x-ui::table.heading>{{ __('Status') }}</x-ui::table.heading>
            <x-ui::table.heading>{{ __('Fee') }}</x-ui::table.heading>
            <x-ui::table.heading>{{ __('Contact') }}</x-ui::table.heading>
        </x-ui::table.head>

        @forelse ($this->registrations as $registration)
            <x-ui::table.row wire:key="registration-{{ $registration->id }}">
                <x-ui::table.cell header>
                    <a href="{{ route('portal.registrations.show', $registration) }}"
                        class="text-fg-brand hover:underline">{{ $registration->event?->name }}</a>
                </x-ui::table.cell>
                <x-ui::table.cell>{{ $registration->event?->starts_at?->toFormattedDateString() }}</x-ui::table.cell>
                <x-ui::table.cell>
                    <x-ui::badge>{{ $registration->status->value }}</x-ui::badge>
                </x-ui::table.cell>
                <x-ui::table.cell>{{ \App\Support\Money::format($registration->price_cents) }}</x-ui::table.cell>
                <x-ui::table.cell>{{ $registration->rep_name }}</x-ui::table.cell>
            </x-ui::table.row>
        @empty
            <x-ui::table.row>
                <x-ui::table.empty-state :colspan="5" heading="{{ __('No registrations yet') }}">
                    {{ __('When your organization registers for a fair, it will appear here with its payment status.') }}
                </x-ui::table.empty-state>
            </x-ui::table.row>
        @endforelse
    </x-ui::table>
</div>
