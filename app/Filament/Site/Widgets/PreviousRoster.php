<?php

namespace App\Filament\Site\Widgets;

use App\Models\Event;

/**
 * Last year's schools.
 *
 * Reads `RosterService::previousEvent()`, which reads the `previousPublished()`
 * scope — the same one the campaign audiences use. That shared definition is
 * the fix for the staleness bug doc 00 recorded, where this page was showing
 * the current roster.
 */
class PreviousRoster extends RosterTable
{
    public function getTableHeading(): ?string
    {
        return $this->rosterEvent()?->name;
    }

    protected function rosterEvent(): ?Event
    {
        return $this->rosterService()->previousEvent();
    }

    protected function emptyHeading(): string
    {
        return __('No previous fair on record yet');
    }

    protected function emptyDescription(): string
    {
        return __('This page fills in after the first fair run on this system.');
    }
}
