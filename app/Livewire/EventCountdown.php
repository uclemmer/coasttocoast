<?php

namespace App\Livewire;

use App\Models\Event;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The countdown to the fair (card 8.2).
 *
 * **It does not poll.** The handoff offers "`wire:poll.1s` or a JS interval",
 * and a one-second poll on a public marketing page means one HTTP request per
 * visitor per second — a hundred people reading the page is a hundred requests
 * a second, all to render four numbers the browser can work out on its own.
 * The ticking is a `setInterval` in Alpine; this component's only job is to
 * hand the target moment to the client and render a correct first paint.
 *
 * Rendering the numbers server-side matters too: without it the hero is
 * followed by four empty boxes until JavaScript runs, and by nothing at all if
 * it never does.
 */
class EventCountdown extends Component
{
    #[Locked]
    public Event $event;

    public function mount(Event $event): void
    {
        $this->event = $event;
    }

    /**
     * Whether the fair is still ahead of us.
     *
     * Past-date behaviour is part of the design: the number grid disappears and
     * the heading changes, so a stale page reads as "that has happened" rather
     * than as a broken clock.
     */
    public function hasHappened(): bool
    {
        return $this->event->starts_at->isPast();
    }

    public function heading(): string
    {
        return $this->hasHappened()
            ? __('This year\'s fair has concluded — details for next spring are coming')
            : __('The fair opens in…');
    }

    /**
     * The first paint, computed on the server. Alpine takes over on the next
     * tick, so a visitor never sees zeroes.
     *
     * @return array<string, string>
     */
    public function initialUnits(): array
    {
        $seconds = max(0, now()->diffInSeconds($this->event->starts_at, absolute: false));

        return [
            // Days is unpadded; the rest are zero-padded to two digits.
            'days' => (string) intdiv($seconds, 86400),
            'hours' => str_pad((string) (intdiv($seconds, 3600) % 24), 2, '0', STR_PAD_LEFT),
            'minutes' => str_pad((string) (intdiv($seconds, 60) % 60), 2, '0', STR_PAD_LEFT),
            'seconds' => str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT),
        ];
    }

    public function render(): View
    {
        return view('livewire.event-countdown', [
            'heading' => $this->heading(),
            'hasHappened' => $this->hasHappened(),
            'units' => $this->initialUnits(),
            // ISO 8601 with the offset, so the browser computes against the
            // fair's real moment rather than the visitor's interpretation of a
            // naive date.
            'target' => $this->event->starts_at->toIso8601String(),
        ]);
    }
}
