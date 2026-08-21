<?php

namespace App\Livewire\Portal;

use App\Enums\GrantStatus;
use App\Livewire\Portal\Concerns\ActsForAnOrganization;
use App\Models\Event;
use App\Models\Grant;
use App\Models\Registration;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The portal's front page (docs/12).
 *
 * Filament's rep panel used its stock Dashboard, which rendered an empty page
 * with no widgets — a rep signing in landed on nothing and had to find the
 * navigation. This replaces it with the two questions somebody actually signs
 * in to answer: what is my school registered for, and where did my fee
 * assistance request get to.
 *
 * Browsable by everyone, including pending and retired reps. Their school's
 * history is theirs to look at; the membership banner explains why the buttons
 * elsewhere are missing.
 */
#[Layout('components.layouts.portal', ['title' => 'Overview', 'heading' => 'Overview'])]
class Dashboard extends Component
{
    use ActsForAnOrganization;

    /**
     * Registrations for fairs that have not happened yet, soonest first.
     *
     * @return Collection<int, Registration>
     */
    #[Computed]
    public function upcoming(): Collection
    {
        $organization = $this->currentOrganization();

        if ($organization === null) {
            return collect();
        }

        return Registration::query()
            ->with('event')
            ->where('organization_id', $organization->id)
            ->whereHas('event', fn ($query) => $query->where('starts_at', '>', now()))
            ->get()
            ->sortBy(fn (Registration $registration): mixed => $registration->event?->starts_at)
            ->values();
    }

    /**
     * Fee assistance still waiting on a decision.
     *
     * Only pending ones. A decided grant's outcome belongs on the grants page
     * with its explanation; surfacing it here would be a status with no room
     * for the sentence that makes it mean something.
     *
     * @return Collection<int, Grant>
     */
    #[Computed]
    public function pendingGrants(): Collection
    {
        $organization = $this->currentOrganization();

        if ($organization === null) {
            return collect();
        }

        return Grant::query()
            ->with('event')
            ->where('organization_id', $organization->id)
            ->where('status', GrantStatus::Pending)
            ->get();
    }

    /**
     * The next fair this school has NOT registered for, if there is one.
     *
     * The dashboard's one call to action. Absent when they are already
     * registered for everything ahead — a button that leads to "you have
     * already done this" is worse than no button.
     */
    #[Computed]
    public function nextUnregisteredFair(): ?Event
    {
        $organization = $this->currentOrganization();

        if ($organization === null) {
            return null;
        }

        $registered = Registration::query()
            ->where('organization_id', $organization->id)
            ->pluck('event_id');

        return Event::query()
            ->published()
            ->where('starts_at', '>', now())
            ->whereNotIn('id', $registered)
            ->orderBy('starts_at')
            ->first();
    }

    public function render(): View
    {
        return view('livewire.portal.dashboard');
    }
}
