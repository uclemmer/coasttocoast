<?php

namespace App\Livewire\Staff\Grants;

use App\Enums\GrantStatus;
use App\Livewire\Staff\Concerns\ActsForStaff;
use App\Livewire\Staff\Grants\Concerns\DecidesGrants;
use App\Models\Event;
use App\Models\Grant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The grant review queue (R3.3b) — the Livewire replacement for the admin
 * panel's GrantResource list (docs/13).
 *
 * A QUEUE, NOT AN ARCHIVE, which is why the status filter starts on Pending
 * exactly as Filament's `->default()` did. Somebody is waiting on the other end
 * of each of these.
 */
#[Layout('components.layouts.staff', ['title' => 'Grants', 'heading' => 'Fee assistance'])]
class Index extends Component
{
    use ActsForStaff;
    use DecidesGrants;

    public string $search = '';

    /** Starts on Pending. See the class note. */
    public string $status = '';

    public string $eventId = '';

    public function mount(): void
    {
        $this->abortUnlessStaff();
        $this->authorize('viewAny', Grant::class);

        $this->status = GrantStatus::Pending->value;
    }

    /**
     * @return Collection<int, Grant>
     */
    #[Computed]
    public function grants(): Collection
    {
        return Grant::query()
            ->with(['organization', 'event', 'requester'])
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->eventId !== '', fn ($query) => $query->where('event_id', $this->eventId))
            ->when($this->search !== '', fn ($query) => $query->whereHas(
                'organization',
                fn ($organization) => $organization->where('name', 'like', '%'.$this->search.'%'),
            ))
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return Collection<int, Event>
     */
    #[Computed]
    public function fairs(): Collection
    {
        return Event::query()->orderByDesc('starts_at')->get();
    }

    /**
     * How many applications are waiting on a decision.
     *
     * Filament put this in the sidebar as a navigation badge. It is on the page
     * instead: the staff nav is a flat list of six links and a count on one of
     * them was more chrome than it was worth, but the number still matters
     * because somebody is on the other end of each one.
     */
    #[Computed]
    public function pendingCount(): int
    {
        return Grant::query()->where('status', GrantStatus::Pending)->count();
    }

    protected function refreshAfterDecision(): void
    {
        unset($this->grants, $this->pendingCount);
    }

    public function render(): View
    {
        return view('livewire.staff.grants.index');
    }
}
