<?php

namespace App\Livewire\Staff\Sponsors;

use App\Livewire\Staff\Concerns\ActsForStaff;
use App\Livewire\Staff\Concerns\ReordersRecords;
use App\Models\Sponsor;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The sponsoring schools (R3.5) — the Livewire replacement for the admin
 * panel's SponsorResource list (docs/13).
 *
 * Ordering is by hand, not alphabetical: sponsors pay for billing position, and
 * ties fall back to name so the list is stable across page loads. That rule
 * lives on `Sponsor::ordered()` and is not restated here.
 *
 * REORDERING IS BUTTONS, NOT DRAG. Filament's table was `->reorderable()`, and
 * its comment recorded the intent — "the coordinator should never have to work
 * out what integer puts a school second". Buttons satisfy that intent and drag
 * costs two things buttons do not: it is unusable by keyboard and by screen
 * reader, and it cannot be exercised in this project's headless browser, which
 * does not composite frames and so never fires the animation frames a drag
 * depends on. Alpine's `sort` plugin ships inside Livewire's bundle if drag is
 * ever wanted on top; it should be *on top*, not instead.
 *
 * Reordering is hidden while a search is active. "Move up" in a filtered list
 * means nothing the user can predict — the row above on screen is not the row
 * above in the order.
 */
#[Layout('components.layouts.staff', ['title' => 'Sponsors', 'heading' => 'Sponsors'])]
class Index extends Component
{
    use ActsForStaff;
    use ReordersRecords;

    public string $search = '';

    /** Row ids ticked for the bulk bar. Livewire hands these back as strings. */
    /** @var array<int, string> */
    public array $selected = [];

    /** The sponsor the delete dialog is asking about. */
    public ?int $deleting = null;

    public function mount(): void
    {
        $this->abortUnlessStaff();
        $this->authorize('viewAny', Sponsor::class);
    }

    /**
     * @return Collection<int, Sponsor>
     */
    #[Computed]
    public function sponsors(): Collection
    {
        return Sponsor::query()
            ->withCount('staff')
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->ordered()
            ->get();
    }

    /** Whether the up/down controls make sense right now. See the class note. */
    #[Computed]
    public function canReorder(): bool
    {
        return $this->search === '';
    }

    public function updatedSearch(): void
    {
        // Selections are row ids from the previous result set; keeping them
        // would let a bulk delete reach rows the user can no longer see.
        $this->selected = [];
        unset($this->sponsors, $this->canReorder);
    }

    public function moveUp(int $sponsorId): void
    {
        $this->swap($sponsorId, -1);
    }

    public function moveDown(int $sponsorId): void
    {
        $this->swap($sponsorId, 1);
    }

    /** The mechanic is in ReordersRecords; the permission stays here. */
    protected function swap(int $sponsorId, int $offset): void
    {
        if (! $this->canReorder) {
            return;
        }

        $ordered = Sponsor::query()->ordered()->get();
        $moving = $ordered->firstWhere(fn (Sponsor $sponsor): bool => $sponsor->getKey() === $sponsorId);

        if ($moving === null) {
            return;
        }

        // Against the record, not the class: `SponsorPolicy::update()` takes a
        // Sponsor, and handing Gate a class-string for it throws rather than
        // failing closed.
        $this->authorize('update', $moving);

        if ($this->reorderWithin($ordered, $sponsorId, $offset)) {
            unset($this->sponsors);
        }
    }

    public function confirmDelete(int $sponsorId): void
    {
        $this->deleting = $sponsorId;
        $this->dispatch('ui-modal-open', id: 'delete-sponsor');
    }

    public function delete(): void
    {
        $sponsor = Sponsor::query()->find($this->deleting);

        if ($sponsor === null) {
            $this->toast(__('That sponsor could not be found.'), 'danger');

            return;
        }

        // Authorised against the record, not the class: the policy takes one,
        // and a class-only check would be a different question.
        $this->authorize('delete', $sponsor);

        $sponsor->delete();

        $this->deleting = null;
        unset($this->sponsors);

        $this->dispatch('ui-modal-close', id: 'delete-sponsor');
        $this->toast(__('Sponsor removed.'));
    }

    public function deleteSelected(): void
    {
        $this->authorize('deleteAny', Sponsor::class);

        $sponsors = Sponsor::query()->whereKey($this->selected)->get();

        // Each row is still checked individually. `deleteAny` says the user may
        // use the bulk control; it does not say every row in the ids the
        // browser sent back is theirs to remove.
        foreach ($sponsors as $sponsor) {
            $this->authorize('delete', $sponsor);
            $sponsor->delete();
        }

        $count = $sponsors->count();
        $this->selected = [];
        unset($this->sponsors);

        $this->toast(trans_choice(':count sponsor removed.|:count sponsors removed.', $count, ['count' => $count]));
    }

    public function render(): View
    {
        return view('livewire.staff.sponsors.index');
    }
}
