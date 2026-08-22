{{-- One campaign: what it says, who it reaches, how it landed (docs/13). --}}
@php($message = $this->record)

<div>
    <x-ui::action-bar :heading="$message->subject"
        :description="$message->event?->name ?? __('Measured against the active fair')">
        <x-ui::button href="{{ route('staff.messages') }}" variant="secondary">
            {{ __('Back to campaigns') }}
        </x-ui::button>

        @unless ($message->isSent())
            <x-ui::button href="{{ route('staff.messages.edit', $message) }}" variant="secondary">
                {{ __('Edit') }}
            </x-ui::button>
            <x-ui::button variant="secondary" wire:click="confirmTestSend">
                {{ __('Send a test to me') }}
            </x-ui::button>
            <x-ui::button variant="brand" wire:click="confirmSend">
                {{ __('Send') }}
            </x-ui::button>
        @endunless
    </x-ui::action-bar>

    @if ($message->isSent())
        <div class="mt-4">
            <x-ui::alert variant="success">
                {{ __('Sent :when. A sent campaign cannot be edited or removed — it is the record of what schools were told.', [
                    'when' => $message->sent_at?->toDayDateTimeString(),
                ]) }}
            </x-ui::alert>
        </div>
    @endif

    <div class="mt-6 max-w-4xl space-y-6">
        <x-ui::section :heading="__('The campaign')">
            <x-ui::description-list :columns="2">
                <x-ui::description-list.item :term="__('Audience')"
                    :description="$message->audience?->getLabel() ?? '—'" />
                <x-ui::description-list.item :term="__('Reaches now')"
                    :description="(string) $this->audienceCount" />
                <x-ui::description-list.item :term="__('Channels')"
                    :description="collect((array) $message->channels)
                        ->map(fn ($c) => $c instanceof \App\Enums\MessageChannel ? $c->getLabel() : (string) $c)
                        ->join(', ')" />
                <x-ui::description-list.item :term="__('Scheduled for')"
                    :description="$message->scheduled_for?->toDayDateTimeString() ?? '—'" />
            </x-ui::description-list>

            @if ($message->audience?->getDescription())
                <p class="mt-3 max-w-prose text-sm text-body">{{ $message->audience->getDescription() }}</p>
            @endif
        </x-ui::section>

        @if (filled($message->email_body))
            <x-ui::section :heading="__('Email')">
                <x-ui.prose :html="Str::markdown($message->email_body)" class="text-[15px]" />
            </x-ui::section>
        @endif

        @if (filled($message->sms_body))
            <x-ui::section :heading="__('Text message')">
                <p class="max-w-prose whitespace-pre-line text-[15px] text-body">{{ $message->sms_body }}</p>
            </x-ui::section>
        @endif

        {{-- Who this reaches, on the page rather than behind a modal: the
             answer to "who gets this" should not need a round trip. --}}
        <x-ui::section :heading="__('Who this reaches')"
            :description="__('A sample of the audience as it stands right now.')">
            <x-ui::accordion>
                <x-ui::accordion.item level="h3"
                    :heading="trans_choice('Show :count recipient|Show the first :count of them', $this->audiencePreview->count(), ['count' => $this->audiencePreview->count()])">
                    @if ($this->audiencePreview->isEmpty())
                        <p class="text-sm text-body">{{ __('Nobody matches this audience right now.') }}</p>
                    @else
                        <ul class="space-y-1 text-sm text-body">
                            @foreach ($this->audiencePreview as $person)
                                <li>{{ $person->name ?? $person->email ?? __('Unnamed') }}
                                    @if (! empty($person->email))
                                        <span class="text-body">&mdash; {{ $person->email }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui::accordion.item>
            </x-ui::accordion>
        </x-ui::section>

        {{--
            The delivery record. Read-only by design: it is what somebody later
            reads to find out who was told and whether it arrived, and a table
            you can edit is not a record.

            The email column prefers laravel-core's email-log row over the local
            column, which is why it goes through `resolvedEmailStatus()`.
        --}}
        <x-ui::section :heading="__('Delivery')">
            <x-ui::table>
                <x-slot:after>
                    <div class="p-3">{{ $recipients->links('vendor.pagination.ui') }}</div>
                </x-slot:after>

                <x-ui::table.head>
                    <x-ui::table.heading>{{ __('School') }}</x-ui::table.heading>
                    <x-ui::table.heading>{{ __('Person') }}</x-ui::table.heading>
                    <x-ui::table.heading>{{ __('Email') }}</x-ui::table.heading>
                    <x-ui::table.heading>{{ __('SMS') }}</x-ui::table.heading>
                    <x-ui::table.heading>{{ __('Problem') }}</x-ui::table.heading>
                </x-ui::table.head>

                @forelse ($recipients as $recipient)
                    <x-ui::table.row wire:key="recipient-{{ $recipient->id }}">
                        <x-ui::table.cell header>
                            {{ $recipient->organization_name ?? __('Interest list') }}
                        </x-ui::table.cell>

                        <x-ui::table.cell>
                            {{ $recipient->name ?? __('Admissions office') }}
                            <span class="block text-sm text-body">{{ $recipient->email }}</span>
                        </x-ui::table.cell>

                        <x-ui::table.cell>
                            <x-ui::badge variant="gray">
                                {{ $recipient->resolvedEmailStatus()?->getLabel() ?? '—' }}
                            </x-ui::badge>
                        </x-ui::table.cell>

                        <x-ui::table.cell>
                            <x-ui::badge variant="gray">
                                {{ $recipient->sms_status?->getLabel() ?? '—' }}
                            </x-ui::badge>
                        </x-ui::table.cell>

                        <x-ui::table.cell>
                            <span class="block max-w-prose">{{ $recipient->error ?: '—' }}</span>
                        </x-ui::table.cell>
                    </x-ui::table.row>
                @empty
                    <x-ui::table.row>
                        <x-ui::table.empty-state :colspan="5" :heading="__('Nothing sent yet')">
                            {{ __('The delivery table fills in as the campaign goes out.') }}
                        </x-ui::table.empty-state>
                    </x-ui::table.row>
                @endforelse
            </x-ui::table>
        </x-ui::section>
    </div>

    <x-ui::confirm-modal id="test-send" :title="__('Send a test copy?')" :confirm="__('Send the test')"
        variant="brand" wire:click="sendTest">
        {{ __('It goes to :email and is not recorded against this campaign.', ['email' => auth()->user()->email]) }}
    </x-ui::confirm-modal>

    <x-ui::confirm-modal id="send-campaign" :title="__('Send this campaign?')" :confirm="__('Send it')"
        variant="brand" wire:click="send">
        {{ trans_choice(
            'It goes to :count person as the audience stands right now.|It goes to :count people as the audience stands right now.',
            $this->audienceCount,
            ['count' => $this->audienceCount],
        ) }}
        {{ __('This cannot be undone, and the campaign becomes read-only afterwards.') }}
    </x-ui::confirm-modal>
</div>
