<?php

namespace App\Livewire;

use App\Livewire\Concerns\ShowsARoster;
use App\Models\Event;
use App\Services\RosterService;
use App\Support\ContentBlocks;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Last year's roster (R1.4).
 *
 * Reads `RosterService::previousEvent()`, which reads the `previousPublished()`
 * scope — the same definition the cross-year campaign audiences use, so the
 * site and the mailing list cannot disagree about which fair was last
 * (doc 10, D-5.3-a).
 */
class LastYearRoster extends Component
{
    use ShowsARoster;

    public function fair(): ?Event
    {
        return app(RosterService::class)->previousEvent();
    }

    public function render(): View
    {
        return view('livewire.roster', [
            'roster' => $this->rosterProperty(),
            'fair' => $this->fair(),
            'title' => __('Last year at the fair'),
            'eyebrow' => __('Last year'),
            'intro' => ContentBlocks::render('last_year.intro'),
            'emptyHeading' => __('No previous fair on record yet'),
            'emptyBody' => __('This page fills in after the first fair run on this system.'),
            'crumbs' => [__('Home') => route('site.home'), __('Last year') => null],
        ]);
    }
}
