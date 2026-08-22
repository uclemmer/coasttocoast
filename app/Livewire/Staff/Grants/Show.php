<?php

namespace App\Livewire\Staff\Grants;

use App\Livewire\Staff\Concerns\ActsForStaff;
use App\Livewire\Staff\Grants\Concerns\DecidesGrants;
use App\Models\Grant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * One grant application in full (docs/13) — replaces the admin panel's
 * ViewGrant page and its infolist.
 *
 * The justification is why this screen exists. It is the school's case for
 * needing help, it does not fit in a table cell, and it is the thing a
 * coordinator is actually reading before deciding.
 */
#[Layout('components.layouts.staff', ['title' => 'Grant'])]
class Show extends Component
{
    use ActsForStaff;
    use DecidesGrants;

    public Grant $grant;

    public function mount(Grant $grant): void
    {
        $this->abortUnlessStaff();
        $this->authorize('view', $grant);

        $this->grant = $grant;
    }

    /**
     * Re-read rather than trusting the mounted copy.
     *
     * A decision writes through `GrantService` and the instance held on the
     * component is stale the moment it returns — showing "Pending" beside a
     * toast saying "Approved" is exactly the sort of thing that makes somebody
     * click twice.
     */
    #[Computed]
    public function record(): Grant
    {
        return Grant::query()
            ->with(['organization', 'event', 'requester', 'decider'])
            ->findOrFail($this->grant->getKey());
    }

    protected function refreshAfterDecision(): void
    {
        unset($this->record);
    }

    public function render(): View
    {
        return view('livewire.staff.grants.show');
    }
}
