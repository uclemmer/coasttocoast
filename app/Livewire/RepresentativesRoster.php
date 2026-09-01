<?php

namespace App\Livewire;

use App\Livewire\Concerns\ShowsARoster;
use App\Models\Event;
use App\Support\ContentBlocks;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Who is coming to this year's fair (R1.3).
 *
 * Doubles as social proof and as a duplicate check — a rep who finds their
 * organization already listed knows not to register it twice.
 */
class RepresentativesRoster extends Component
{
    use ShowsARoster;

    public function fair(): ?Event
    {
        return Event::active();
    }

    public function render(): View
    {
        return view('livewire.roster', [
            'roster' => $this->rosterProperty(),
            'fair' => $this->fair(),
            'title' => __('Participating institutions'),
            'eyebrow' => __('Who is coming'),
            'intro' => ContentBlocks::render('representatives.intro'),
            'emptyHeading' => __('No institutions listed yet'),
            'emptyBody' => __('Colleges appear here as their registrations are confirmed.'),
            'crumbs' => [__('Home') => route('site.home'), __('Representatives') => null],
        ]);
    }
}
