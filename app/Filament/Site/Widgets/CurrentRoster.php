<?php

namespace App\Filament\Site\Widgets;

use App\Models\Event;

/**
 * The schools attending the fair the site is currently about.
 */
class CurrentRoster extends RosterTable
{
    public function getTableHeading(): ?string
    {
        return Event::active()?->name;
    }

    protected function rosterEvent(): ?Event
    {
        return Event::active();
    }
}
