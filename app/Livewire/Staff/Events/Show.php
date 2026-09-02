<?php

namespace App\Livewire\Staff\Events;

use App\Livewire\Staff\Concerns\ActsForStaff;
use App\Models\Event;
use App\Notifications\RegistrationOpenAnnouncement;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification as Notifier;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * One fair in full, and the announcement (docs/13) — replaces the admin
 * panel's ViewEvent page, its infolist and its header action.
 */
#[Layout('components.layouts.staff', ['title' => 'Fair'])]
class Show extends Component
{
    use ActsForStaff;

    public Event $event;

    public function mount(Event $event): void
    {
        $this->abortUnlessStaff();
        $this->authorize('view', $event);

        $this->event = $event;
    }

    /** Re-read, so the page cannot show a state the announcement just changed. */
    #[Computed]
    public function record(): Event
    {
        return Event::query()->findOrFail($this->event->getKey());
    }

    /** How many people asked to be told about this fair and have not been. */
    #[Computed]
    public function waitingCount(): int
    {
        return $this->record->interests()->unnotified()->count();
    }

    /**
     * Whether the announcement is appropriate right now.
     *
     * Asks `isRegistrationOpen()` rather than `is_published`, and the
     * distinction is not pedantry: the notification's subject is "Registration
     * for X is now open", its body says "It is open now", and its button goes
     * to a page that would turn the reader away. A published fair whose
     * registration window has not started is exactly that email being untrue.
     *
     * The dashboard's nudge asks the same question, so the two cannot disagree
     * about when this button should exist.
     */
    public function canAnnounce(): bool
    {
        return $this->record->isRegistrationOpen()
            && $this->waitingCount > 0
            && $this->currentUser()->can('update', $this->record);
    }

    public function confirmAnnounce(): void
    {
        $this->dispatch('ui-modal-open', id: 'announce-registration');
    }

    /**
     * Tell the notify-me list that registration is open (card 6.5).
     *
     * IDEMPOTENT BY DESIGN, and the reason is worth keeping: the realistic
     * mistake is a coordinator who is not sure whether the first press worked,
     * and the answer to that should be "press it again", not a hundred
     * duplicate emails. Only rows with no `notified_at` are sent to.
     */
    public function announce(): void
    {
        $event = $this->record;

        $this->authorize('update', $event);

        if (! $event->is_published) {
            // An unpublished fair has nothing for these people to register for.
            $this->toast(__('Publish the fair before announcing it.'), 'danger');

            return;
        }

        if (! $event->isRegistrationOpen()) {
            // Covers both ends of the window. The announcement claims
            // registration is open and links somewhere that would refuse one.
            $this->toast(__('Registration for this fair is not open, and the announcement says it is.'), 'danger');

            return;
        }

        $waiting = $event->interests()->unnotified()->get();

        foreach ($waiting as $interest) {
            Notifier::route('mail', $interest->email)
                ->notify(new RegistrationOpenAnnouncement($event));

            /*
             * Stamped one at a time rather than in bulk afterwards: if this
             * dies halfway, the people already mailed are marked and a re-run
             * picks up where it stopped. A bulk update at the end would either
             * mark people who were never told, or tell them twice.
             */
            $interest->forceFill(['notified_at' => Carbon::now()])->save();
        }

        unset($this->record, $this->waitingCount);

        $this->dispatch('ui-modal-close', id: 'announce-registration');

        $this->toast(trans_choice(
            '{0}Nobody was waiting.|{1}One person told.|[2,*]:count people told.',
            $waiting->count(),
            ['count' => $waiting->count()],
        ));
    }

    public function render(): View
    {
        return view('livewire.staff.events.show');
    }
}
