<?php

namespace App\Livewire\Portal;

use App\Livewire\Portal\Concerns\ActsForAnOrganization;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * A school's registrations — the Livewire replacement for the rep panel's
 * RegistrationResource list page (docs/12).
 *
 * Scoped to the viewer's school, and browsable by pending and retired reps:
 * their school's history is theirs to look at. Only the "register for a fair"
 * button is gated on active membership, and the membership banner explains its
 * absence rather than leaving a page that looks broken.
 *
 * Sorting and searching are deliberately absent. A school has a handful of
 * registrations — one per fair per year — so a toolbar over five rows would be
 * furniture. `table.sort-header` is there in the package if that ever changes.
 */
#[Layout('components.layouts.portal', ['title' => 'Registrations', 'heading' => 'Registrations'])]
class Registrations extends Component
{
    use ActsForAnOrganization;

    public function mount(): void
    {
        $this->abortUnlessAttachedToOrganization();
    }

    /**
     * This school's registrations, newest fair first.
     *
     * @return Collection<int, Registration>
     */
    #[Computed]
    public function registrations(): Collection
    {
        $organization = $this->currentOrganization();

        if ($organization === null) {
            return collect();
        }

        return Registration::query()
            ->with('event')
            ->where('organization_id', $organization->id)
            ->get()
            ->sortByDesc(fn (Registration $registration): mixed => $registration->event?->starts_at)
            ->values();
    }

    /**
     * Whether there is a fair open that this school has not already taken.
     *
     * Drives the button. One that leads to "there is nothing to register for"
     * is worse than no button.
     */
    #[Computed]
    public function canRegister(): bool
    {
        if (! $this->actsForOrganization()) {
            return false;
        }

        $organization = $this->currentOrganization();

        $taken = Registration::query()
            ->where('organization_id', $organization?->id)
            ->pluck('event_id');

        return Event::query()
            ->published()
            ->whereNotIn('id', $taken)
            ->get()
            ->contains(fn (Event $event): bool => $event->isRegistrationOpen());
    }

    public function render(): View
    {
        return view('livewire.portal.registrations');
    }
}
