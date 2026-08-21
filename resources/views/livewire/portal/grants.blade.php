<div class="space-y-6">
    {{-- Appendix A copy: what this page is, before anything else on it. --}}
    <p class="max-w-prose text-sm text-body">
        {{ __("If the registration fee is a barrier for your institution, you can request a reduced or waived fee for this year's fair. Requests are reviewed by the fair coordinator, and you'll hear back by email — usually within a week. Applying does not register you for the fair; once your request is decided, register as usual and any approved discount is applied automatically.") }}
    </p>

    @if ($this->membershipNotice())
        <x-ui::alert variant="warning">{{ $this->membershipNotice() }}</x-ui::alert>
    @endif

    <x-ui::table>
        <x-slot:before>
            <x-ui::table.toolbar>
                @if ($this->canApply)
                    <x-ui::button x-on:click="$dispatch('ui-modal-open', { id: 'apply-for-grant' })">
                        {{ __('Request fee assistance') }}
                    </x-ui::button>
                @endif
            </x-ui::table.toolbar>
        </x-slot:before>

        <x-ui::table.head>
            <x-ui::table.heading>{{ __('Fair') }}</x-ui::table.heading>
            <x-ui::table.heading>{{ __('Status') }}</x-ui::table.heading>
            <x-ui::table.heading>{{ __('Outcome') }}</x-ui::table.heading>
            <x-ui::table.heading>{{ __('Requested') }}</x-ui::table.heading>
            <x-ui::table.heading><span class="sr-only">{{ __('Actions') }}</span></x-ui::table.heading>
        </x-ui::table.head>

        @forelse ($this->grants as $grant)
            <x-ui::table.row wire:key="grant-{{ $grant->id }}">
                <x-ui::table.cell header>{{ $grant->event?->name }}</x-ui::table.cell>
                <x-ui::table.cell>
                    <x-ui::badge>{{ $grant->status->value }}</x-ui::badge>
                </x-ui::table.cell>
                {{-- The sentence, not the status. It is what the page is for. --}}
                <x-ui::table.cell class="max-w-md whitespace-normal">{{ $this->statusCopy($grant) }}</x-ui::table.cell>
                <x-ui::table.cell>{{ $grant->created_at?->toFormattedDateString() }}</x-ui::table.cell>
                <x-ui::table.cell>
                    @if ($grant->status === \App\Enums\GrantStatus::Pending && $this->actsForOrganization())
                        <x-ui::button variant="secondary" size="sm"
                            wire:click="confirmWithdraw({{ $grant->id }})">{{ __('Withdraw') }}</x-ui::button>
                    @endif
                </x-ui::table.cell>
            </x-ui::table.row>
        @empty
            <x-ui::table.row>
                <x-ui::table.empty-state :colspan="5" heading="{{ __('No requests yet') }}">
                    {{ __('If the registration fee is a barrier for your institution, you can ask for a reduced or waived fee.') }}
                </x-ui::table.empty-state>
            </x-ui::table.row>
        @endforelse
    </x-ui::table>

    {{-- ── Apply ────────────────────────────────────────────────────────── --}}
    <x-ui::modal id="apply-for-grant" title="{{ __('Request fee assistance') }}">
        <form wire:submit="apply" class="space-y-4">
            <x-ui::forms.select name="event_id" label="{{ __('Fair') }}" wire:model="event_id" required>
                <option value="">{{ __('Choose a fair') }}</option>
                @foreach ($this->applicableFairs as $fair)
                    <option value="{{ $fair->id }}">{{ $fair->name }}</option>
                @endforeach
            </x-ui::forms.select>

            <x-ui::forms.textarea name="justification" rows="5"
                label="{{ __('Why are you requesting fee assistance?') }}" wire:model="justification" required
                hint="{{ __('A couple of sentences is plenty — e.g., budget constraints, first time attending, non-profit or community program.') }}" />

            <div class="flex justify-end gap-2">
                <x-ui::button type="button" variant="secondary"
                    x-on:click="$dispatch('ui-modal-close', { id: 'apply-for-grant' })">{{ __('Cancel') }}</x-ui::button>
                <x-ui::button type="submit" wire:loading.attr="disabled">{{ __('Submit request') }}</x-ui::button>
            </div>
        </form>
    </x-ui::modal>

    {{-- ── Withdraw ─────────────────────────────────────────────────────── --}}
    <x-ui::confirm-modal id="withdraw-grant" title="{{ __('Withdraw this request?') }}"
        confirm="{{ __('Withdraw') }}" wire:click="withdraw">
        {{ __('You can submit a new one for the same fair afterwards.') }}
    </x-ui::confirm-modal>
</div>
