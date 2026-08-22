<?php

namespace App\Livewire\Staff\Organizations;

use App\Livewire\Staff\Concerns\ActsForStaff;
use App\Models\Organization;
use App\Services\OrganizationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * The school directory (R3.3a) — the Livewire replacement for the admin
 * panel's OrganizationResource list (docs/13).
 *
 * THE MERGE IS THE INTERESTING ONE, and the reasoning is carried over whole.
 * Duplicate schools are inevitable — two reps sign up a year apart and one of
 * them types "The" — and the fix has to preserve both schools' registration
 * history, which a delete never can because the foreign keys cascade.
 * `OrganizationService::merge()` repoints everything first and reports back any
 * fair where the merge has left two live registrations.
 *
 * Those collisions are **not** resolved automatically and are **not** a toast.
 * Which of two paid registrations a school keeps is a decision about money, and
 * a message about it has to survive the next click — Filament used
 * `->persistent()`; here it is an alert that stays on the page.
 */
#[Layout('components.layouts.staff', ['title' => 'Schools', 'heading' => 'Schools'])]
class Index extends Component
{
    use ActsForStaff;

    public string $search = '';

    /** '' (none), 'needs_a_rep', 'possible_duplicates'. */
    public string $filter = '';

    public ?int $merging = null;

    public string $keepId = '';

    /**
     * Fairs where a merge has left the school holding two live registrations.
     *
     * Kept on the component rather than raised as a toast: a toast is gone by
     * the time somebody works out what to do about it.
     *
     * @var array<int, string>
     */
    public array $collisions = [];

    public ?int $deleting = null;

    public function mount(): void
    {
        $this->abortUnlessStaff();
        $this->authorize('viewAny', Organization::class);
    }

    /**
     * @return Collection<int, Organization>
     */
    #[Computed]
    public function organizations(): Collection
    {
        return Organization::query()
            ->withCount(['activeReps', 'registrations'])
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->filter === 'needs_a_rep', fn ($query) => $query->whereDoesntHave('activeReps'))
            ->when($this->filter === 'possible_duplicates', fn ($query) => $query->whereIn(
                'normalized_name',
                Organization::query()
                    ->select('normalized_name')
                    ->groupBy('normalized_name')
                    ->havingRaw('count(*) > 1'),
            ))
            ->orderBy('name')
            ->get();
    }

    /**
     * Schools a merge can fold the current one into.
     *
     * @return Collection<int, Organization>
     */
    #[Computed]
    public function mergeTargets(): Collection
    {
        if ($this->merging === null) {
            return collect();
        }

        return Organization::query()->whereKeyNot($this->merging)->orderBy('name')->get();
    }

    public function updatedSearch(): void
    {
        unset($this->organizations);
    }

    public function updatedFilter(): void
    {
        unset($this->organizations);
    }

    public function startMerge(int $organizationId): void
    {
        $this->merging = $organizationId;
        $this->keepId = '';
        $this->collisions = [];
        $this->resetValidation();

        unset($this->mergeTargets);

        $this->dispatch('ui-modal-open', id: 'merge-organization');
    }

    public function merge(OrganizationService $service): void
    {
        $losing = Organization::query()->find($this->merging);

        if ($losing === null) {
            $this->toast(__('That school could not be found.'), 'danger');

            return;
        }

        $this->authorize('merge', $losing);

        $this->validate([
            'keepId' => ['required', 'integer', 'exists:organizations,id'],
        ]);

        $keep = Organization::query()->findOrFail($this->keepId);

        /*
         * Merging a school into itself is refused by the service, not by a
         * rule here. Restating it as validation would be a second copy of the
         * same decision, and the service's message is already written to be
         * read — the same reasoning the grant decisions follow.
         */
        try {
            $collisions = $service->merge($losing, $keep);
        } catch (Throwable $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->merging = null;
        $this->keepId = '';
        unset($this->organizations, $this->mergeTargets);

        $this->dispatch('ui-modal-close', id: 'merge-organization');

        if ($collisions === []) {
            $this->toast(__('Merged.'));

            return;
        }

        // Deliberately not resolved automatically, and deliberately not a
        // toast. See the class note.
        $this->collisions = array_map(fn ($collision): string => (string) $collision, $collisions);
    }

    public function dismissCollisions(): void
    {
        $this->collisions = [];
    }

    public function confirmDelete(int $organizationId): void
    {
        $this->deleting = $organizationId;
        $this->dispatch('ui-modal-open', id: 'delete-organization');
    }

    public function delete(): void
    {
        $organization = Organization::query()->find($this->deleting);

        if ($organization === null) {
            $this->toast(__('That school could not be found.'), 'danger');

            return;
        }

        $this->authorize('delete', $organization);

        $organization->delete();

        $this->deleting = null;
        unset($this->organizations);

        $this->dispatch('ui-modal-close', id: 'delete-organization');
        $this->toast(__('School removed.'));
    }

    public function render(): View
    {
        return view('livewire.staff.organizations.index');
    }
}
