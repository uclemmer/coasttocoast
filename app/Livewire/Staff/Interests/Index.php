<?php

namespace App\Livewire\Staff\Interests;

use App\Livewire\Staff\Concerns\ActsForStaff;
use App\Models\Event;
use App\Models\EventInterest;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The notify-me list (R2.7) — the people who found the site between fairs and
 * asked to be told when registration opens.
 *
 * The first /staff screen with no Filament ancestor: the resource never
 * existed, so these rows were only ever visible as a count on the fair page
 * and as an audience in the campaign composer. A coordinator could see that
 * forty people were waiting and had no way to see who.
 *
 * THE ANNOUNCEMENT IS NOT HERE, AND THAT IS DELIBERATE. Telling the list that
 * registration is open belongs to `Staff\Events\Show::announce()`, because it
 * is an action on a fair — it needs the fair published, it stamps `notified_at`
 * per row so a second press is a no-op, and splitting it across two screens
 * would give the coordinator two buttons that look like they do the same thing.
 * This screen reads and prunes. The `waiting` filter is the seam between them:
 * it shows exactly the set that button would mail.
 *
 * Ordered on `organization_sort_name`, the same key the roster and the delivery
 * table use (doc 10, D-10-c), then by email so rows with no organization — the
 * form's organization field is optional — are stable rather than arbitrary.
 */
#[Layout('components.layouts.staff', ['title' => 'Notify-me list', 'heading' => 'Notify-me list'])]
class Index extends Component
{
    use ActsForStaff;

    public string $search = '';

    public string $eventId = '';

    /** '' (all), 'waiting' (never told), 'notified'. */
    public string $status = '';

    /** Row ids ticked for the bulk bar. Livewire hands these back as strings. */
    /** @var array<int, string> */
    public array $selected = [];

    /** The signup the delete dialog is asking about. */
    public ?int $deleting = null;

    public function mount(): void
    {
        $this->abortUnlessStaff();
        $this->authorize('viewAny', EventInterest::class);
    }

    /**
     * @return Builder<EventInterest>
     */
    public function filteredQuery(): Builder
    {
        return EventInterest::query()
            ->when($this->eventId !== '', fn ($query) => $query->where('event_id', $this->eventId))
            ->when($this->status === 'waiting', fn ($query) => $query->unnotified())
            ->when($this->status === 'notified', fn ($query) => $query->whereNotNull('notified_at'))
            ->when($this->search !== '', fn ($query) => $query->where(function ($inner): void {
                $inner->where('email', 'like', '%'.$this->search.'%')
                    ->orWhere('organization_name', 'like', '%'.$this->search.'%');
            }));
    }

    /**
     * @return Collection<int, EventInterest>
     */
    #[Computed]
    public function interests(): Collection
    {
        return $this->filteredQuery()
            ->with('event')
            ->orderBy('organization_sort_name')
            ->orderBy('email')
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

    /** How many of the rows on screen the announcement would still reach. */
    #[Computed]
    public function waitingCount(): int
    {
        return $this->filteredQuery()->clone()->unnotified()->count();
    }

    public function updatedSearch(): void
    {
        $this->resetListing();
    }

    public function updatedEventId(): void
    {
        $this->resetListing();
    }

    public function updatedStatus(): void
    {
        $this->resetListing();
    }

    /**
     * Forget the filtered result and the tick boxes over it.
     *
     * Named hooks rather than a blanket `updated()`, which `Registrations`
     * can afford because it has no selection and this cannot: `updated()`
     * fires for `$selected` too, so every tick would clear itself the moment
     * it was made and the bulk bar would never appear.
     *
     * Selections are dropped on a filter change because they are row ids from
     * the previous result set, and keeping them would let a bulk delete reach
     * rows the user can no longer see.
     */
    protected function resetListing(): void
    {
        $this->selected = [];
        unset($this->interests, $this->waitingCount);
    }

    public function confirmDelete(int $interestId): void
    {
        $this->deleting = $interestId;
        $this->dispatch('ui-modal-open', id: 'delete-interest');
    }

    public function delete(): void
    {
        $interest = EventInterest::query()->find($this->deleting);

        if ($interest === null) {
            $this->toast(__('That signup could not be found.'), 'danger');

            return;
        }

        // Against the record, not the class: the policy takes one, and handing
        // Gate a class-string for it is a different question.
        $this->authorize('delete', $interest);

        $interest->delete();

        $this->deleting = null;
        unset($this->interests, $this->waitingCount);

        $this->dispatch('ui-modal-close', id: 'delete-interest');
        $this->toast(__('Signup removed.'));
    }

    public function deleteSelected(): void
    {
        $this->authorize('deleteAny', EventInterest::class);

        $interests = EventInterest::query()->whereKey($this->selected)->get();

        // Each row is still checked individually. `deleteAny` says the user may
        // use the bulk control; it does not say every id the browser sent back
        // is theirs to remove.
        foreach ($interests as $interest) {
            $this->authorize('delete', $interest);
            $interest->delete();
        }

        $count = $interests->count();
        $this->selected = [];
        unset($this->interests, $this->waitingCount);

        $this->toast(trans_choice(':count signup removed.|:count signups removed.', $count, ['count' => $count]));
    }

    public function render(): View
    {
        return view('livewire.staff.interests.index');
    }
}
