<?php

namespace App\Livewire\Staff\Messages;

use App\Enums\Audience;
use App\Enums\MessageChannel;
use App\Livewire\Staff\Concerns\ActsForStaff;
use App\Models\Event;
use App\Models\Message;
use App\Services\AudienceBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Compose a campaign (doc 07 §3, R3.6) — replaces the admin panel's
 * CreateMessage and EditMessage pages (docs/13).
 *
 * A SENT CAMPAIGN CANNOT BE EDITED. `mount()` refuses one outright rather than
 * rendering a form that will not save: it is the record of what a hundred
 * organizations were told, and the delivery table beside it only means something if
 * the message still says what was sent.
 *
 * THE AUDIENCE COUNT IS LIVE, so changing the audience updates the number
 * before anybody commits to it (doc 07 §3). It is a guide — the audience is
 * resolved again when the campaign actually sends — and the screen says so,
 * because a number that turns out to be stale is worse than one labelled
 * approximate.
 *
 * A CHANNEL DECIDES WHETHER ITS BODY IS REQUIRED. Filament wrote that twice,
 * as `visible()` and `required()` on each body; here the rules are assembled
 * from the chosen channels, so the field appearing and its rule applying are
 * one statement.
 */
#[Layout('components.layouts.staff', ['title' => 'Campaign'])]
class Edit extends Component
{
    use ActsForStaff;

    public ?Message $message = null;

    public string $event_id = '';

    public string $audience = '';

    public string $subject = '';

    /** @var array<int, string> */
    public array $channels = [];

    public string $email_body = '';

    public string $sms_body = '';

    public string $scheduled_for = '';

    public function mount(?Message $message = null): void
    {
        $this->abortUnlessStaff();

        if (! $message?->exists) {
            $this->authorize('create', Message::class);
            $this->channels = [MessageChannel::Email->value];

            return;
        }

        // Refused here rather than on save: a form that cannot be saved should
        // not be rendered. The policy says the same thing.
        $this->authorize('update', $message);

        $this->message = $message;
        $this->event_id = (string) ($message->event_id ?? '');
        $this->audience = $message->audience?->value ?? '';
        $this->subject = $message->subject;
        $this->channels = array_map(
            fn ($channel): string => $channel instanceof MessageChannel ? $channel->value : (string) $channel,
            (array) $message->channels,
        );
        $this->email_body = (string) $message->email_body;
        $this->sms_body = (string) $message->sms_body;
        $this->scheduled_for = $message->scheduled_for?->format('Y-m-d\TH:i') ?? '';
    }

    public function isEditing(): bool
    {
        return $this->message?->exists === true;
    }

    /**
     * @return Collection<int, Event>
     */
    #[Computed]
    public function fairs(): Collection
    {
        return Event::query()->orderByDesc('starts_at')->get();
    }

    /** The chosen audience's definition, spelled out under the picker. */
    public function audienceDescription(): ?string
    {
        return Audience::tryFrom($this->audience)?->getDescription();
    }

    /**
     * How many people the chosen audience currently resolves to.
     *
     * Recomputed when the audience or the reference fair changes. Says
     * "Choose an audience" rather than "0" when nothing is chosen — zero is a
     * real and alarming answer, and it should not be shown when the question
     * has not been asked.
     */
    #[Computed]
    public function previewCount(): string
    {
        $audience = Audience::tryFrom($this->audience);

        if (! $audience instanceof Audience) {
            return __('Choose an audience');
        }

        $event = $this->event_id !== ''
            ? Event::query()->find($this->event_id)
            : Event::active();

        return (string) app(AudienceBuilder::class)->count($audience, $event);
    }

    public function updatedAudience(): void
    {
        unset($this->previewCount);
    }

    public function updatedEventId(): void
    {
        unset($this->previewCount);
    }

    public function sendsBy(string $channel): bool
    {
        return in_array($channel, $this->channels, true);
    }

    public function save(): void
    {
        $rules = [
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'audience' => ['required', 'string', 'in:'.collect(Audience::cases())
                ->map(fn (Audience $case): string => $case->value)->implode(',')],
            'subject' => ['required', 'string', 'max:255'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['string', 'in:'.collect(MessageChannel::cases())
                ->map(fn (MessageChannel $case): string => $case->value)->implode(',')],
            'scheduled_for' => ['nullable', 'date'],
        ];

        // A body is required exactly when its channel is chosen. One statement.
        if ($this->sendsBy(MessageChannel::Email->value)) {
            $rules['email_body'] = ['required', 'string'];
        }

        if ($this->sendsBy(MessageChannel::Sms->value)) {
            $rules['sms_body'] = ['required', 'string', 'max:320'];
        }

        $validated = $this->validate($rules);

        $message = $this->message;

        if ($message?->exists) {
            $this->authorize('update', $message);
        } else {
            $this->authorize('create', Message::class);

            $message = new Message;
            $message->created_by = $this->currentUser()->getKey();
        }

        $message->fill([
            'event_id' => $this->event_id === '' ? null : (int) $this->event_id,
            'audience' => $validated['audience'],
            'subject' => $validated['subject'],
            'channels' => $this->channels,
            'email_body' => $this->sendsBy(MessageChannel::Email->value) ? $this->email_body : null,
            'sms_body' => $this->sendsBy(MessageChannel::Sms->value) ? $this->sms_body : null,
            'scheduled_for' => $this->scheduled_for === '' ? null : $this->scheduled_for,
        ]);

        $message->save();

        $this->message = $message;

        session()->flash('status', __('Campaign saved.'));

        $this->redirect(route('staff.messages.show', $message), navigate: false);
    }

    public function render(): View
    {
        return view('livewire.staff.messages.edit', [
            'pageHeading' => $this->isEditing() ? __('Edit campaign') : __('New campaign'),
        ]);
    }
}
