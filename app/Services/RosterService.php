<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Collection;

/**
 * The public rosters — Representatives and Last Year (R1.3, R1.4).
 *
 * One service for both pages because they are the same query against different
 * events, and the staleness bug on the current site (doc 00: the Last Year
 * page was showing the current roster) is exactly what happens when they are
 * two pieces of code.
 *
 * Card 5.3 adds the logo handling and the placeholder. The queries below are
 * complete, because they are just the model scopes composed, and the seeded
 * fixtures already exercise them.
 */
class RosterService
{
    /**
     * The organizations appearing at a given fair: confirmed, not hidden by the
     * coordinator, ordered by organization name.
     *
     * @return Collection<int, Registration>
     */
    public function forEvent(Event $event): Collection
    {
        return Registration::query()
            ->onRoster()
            ->where('event_id', $event->getKey())
            ->with('organization')
            ->join('organizations', 'organizations.id', '=', 'registrations.organization_id')
            ->orderBy('organizations.name')
            ->select('registrations.*')
            ->get();
    }

    /**
     * Last year's roster.
     *
     * Reads `previousPublished()` — the same scope the audience builder uses —
     * so "last year" cannot mean one thing on the public site and another in a
     * campaign (doc 07 §2 rule 5).
     *
     * @return Collection<int, Registration>
     */
    public function forPreviousEvent(): Collection
    {
        $previous = Event::query()->previousPublished()->first();

        /** @var Collection<int, Registration> $empty */
        $empty = new Collection;

        return $previous ? $this->forEvent($previous) : $empty;
    }

    public function previousEvent(): ?Event
    {
        return Event::query()->previousPublished()->first();
    }
}
