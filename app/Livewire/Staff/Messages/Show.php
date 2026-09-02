<?php

namespace App\Livewire\Staff\Messages;

use App\Enums\Audience;
use App\Jobs\SendEventBroadcast;
use App\Livewire\Staff\Concerns\ActsForStaff;
use App\Models\Event;
use App\Models\Message;
use App\Models\MessageRecipient;
use App\Notifications\CampaignMessage;
use App\Services\AudienceBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as Notifier;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * One campaign: what it says, who it reaches, and how it landed (docs/13).
 *
 * Replaces the admin panel's ViewMessage page, its three header actions and its
 * RecipientsRelationManager.
 *
 * THE DELIVERY TABLE IS READ-ONLY and paginated, because a campaign to every
 * organization is hundreds of rows. It is the first paginated screen in /staff, so it
 * is the one that needed the package's paginator views published.
 */
#[Layout('components.layouts.staff', ['title' => 'Campaign'])]
class Show extends Component
{
    use ActsForStaff;
    use WithPagination;

    public Message $message;

    public function mount(Message $message): void
    {
        $this->abortUnlessStaff();
        $this->authorize('view', $message);

        $this->message = $message;
    }

    /** Re-read: sending changes what this page says about itself. */
    #[Computed]
    public function record(): Message
    {
        return Message::query()->with('event')->findOrFail($this->message->getKey());
    }

    /**
     * How many the audience resolves to right now.
     *
     * Recomputed rather than read off the record, because an audience is a
     * query and its answer moves as organizations register.
     */
    #[Computed]
    public function audienceCount(): int
    {
        $message = $this->record;

        if (! $message->audience instanceof Audience) {
            return 0;
        }

        return app(AudienceBuilder::class)->count($message->audience, $this->referenceFair());
    }

    /**
     * A sample of who the campaign would actually reach.
     *
     * Filament opened this in a modal over a Blade view. It is on the page,
     * behind a disclosure, because the answer to "who gets this" should not
     * require a round trip.
     *
     * @return Collection<int, mixed>
     */
    #[Computed]
    public function audiencePreview(): Collection
    {
        $message = $this->record;

        if (! $message->audience instanceof Audience) {
            return collect();
        }

        return app(AudienceBuilder::class)
            ->resolve($message->audience, $this->referenceFair(), (array) $message->audience_filters)
            ->take(25);
    }

    protected function referenceFair(): ?Event
    {
        return $this->record->event ?? Event::active();
    }

    /**
     * The delivery table. Paginated: a full campaign is hundreds of rows.
     *
     * Ordered on the frozen `organization_sort_name` rather than the raw
     * snapshot, so an institution files where the roster files it (doc 10,
     * D-10-a). A recipient who named no organization has no key and sorts
     * first — which is not the same set as the interest list, whose signup
     * form offers an optional organization name and usually gets one.
     */
    public function recipientsProperty()
    {
        return $this->record->recipients()
            ->orderBy('organization_sort_name')
            ->paginate(25);
    }

    public function sendTest(): void
    {
        $message = $this->record;

        $this->authorize('update', $message);

        /*
         * A throwaway recipient with a ULID that is never persisted. The
         * notification needs one to render — unsubscribe links, delivery
         * tracking — and a test send must not appear in this campaign's
         * delivery record, because that record is what somebody later reads to
         * find out who was told.
         */
        $rehearsal = new MessageRecipient([
            'message_id' => $message->getKey(),
            'email' => $this->currentUser()->email,
        ]);
        $rehearsal->id = (string) Str::ulid();

        Notifier::route('mail', $this->currentUser()->email)
            ->notify(new CampaignMessage($message, $rehearsal));

        $this->dispatch('ui-modal-close', id: 'test-send');
        $this->toast(__('Test sent to :email.', ['email' => $this->currentUser()->email]));
    }

    public function confirmTestSend(): void
    {
        $this->dispatch('ui-modal-open', id: 'test-send');
    }

    public function confirmSend(): void
    {
        $this->dispatch('ui-modal-open', id: 'send-campaign');
    }

    public function send(): void
    {
        $message = $this->record;

        /*
         * The already-sent check comes BEFORE authorize, and the order is the
         * point. `MessagePolicy::update()` refuses a sent campaign too, so
         * authorising first makes this branch unreachable and a stale tab
         * clicking Send gets a bare 403 page. Both guards are real; this one
         * is the one a person should meet.
         *
         * No information is leaked by answering first: reaching this screen at
         * all required `view`, which already shows whether it has gone.
         */
        if ($message->isSent()) {
            $this->toast(__('This campaign has already been sent.'), 'danger');

            return;
        }

        $this->authorize('update', $message);

        SendEventBroadcast::dispatch($message);

        unset($this->record);

        $this->dispatch('ui-modal-close', id: 'send-campaign');
        $this->toast(__('Sending. The delivery table below fills in as it goes.'));
    }

    public function render(): View
    {
        return view('livewire.staff.messages.show', [
            'recipients' => $this->recipientsProperty(),
        ]);
    }
}
