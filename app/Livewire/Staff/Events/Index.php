<?php

namespace App\Livewire\Staff\Events;

use App\Livewire\Staff\Concerns\ActsForStaff;
use App\Models\Event;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The fair calendar (R3.2) — the Livewire replacement for the admin panel's
 * EventResource list (docs/13).
 */
#[Layout('components.layouts.staff', ['title' => 'Fairs', 'heading' => 'Fairs'])]
class Index extends Component
{
    use ActsForStaff;

    public string $search = '';

    /** '' (all), 'yes', 'no' — Filament's TernaryFilter. */
    public string $published = '';

    public ?int $deleting = null;

    public function mount(): void
    {
        $this->abortUnlessStaff();
        $this->authorize('viewAny', Event::class);
    }

    /**
     * @return Collection<int, Event>
     */
    #[Computed]
    public function events(): Collection
    {
        return Event::query()
            ->withCount('registrations')
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->published !== '', fn ($query) => $query->where('is_published', $this->published === 'yes'))
            ->orderByDesc('starts_at')
            ->get();
    }

    /**
     * The three states the public event page branches on.
     *
     * One expression, as Filament kept it, so the staff table and the public
     * call-to-action cannot disagree about which state a fair is in.
     */
    public function registrationState(Event $event): string
    {
        return match (true) {
            $event->isRegistrationOpen() => __('Open'),
            $event->registrationNotYetOpen() => __('Not yet open'),
            default => __('Closed'),
        };
    }

    public function capacityNote(Event $event): string
    {
        return $event->capacity === null
            ? __('no cap')
            : __(':left of :capacity left', [
                'left' => $event->remainingCapacity(),
                'capacity' => $event->capacity,
            ]);
    }

    public function updatedSearch(): void
    {
        unset($this->events);
    }

    public function updatedPublished(): void
    {
        unset($this->events);
    }

    public function confirmDelete(int $eventId): void
    {
        $this->deleting = $eventId;
        $this->dispatch('ui-modal-open', id: 'delete-event');
    }

    public function delete(): void
    {
        $event = Event::query()->find($this->deleting);

        if ($event === null) {
            $this->toast(__('That fair could not be found.'), 'danger');

            return;
        }

        /*
         * `EventPolicy::delete()` refuses once registrations exist, so this is
         * the check that stops a fair being deleted out from under the organizations
         * that signed up for it. Filament resolved it implicitly; here it is
         * the one line between a coordinator and a very bad afternoon.
         */
        $this->authorize('delete', $event);

        $event->delete();

        $this->deleting = null;
        unset($this->events);

        $this->dispatch('ui-modal-close', id: 'delete-event');
        $this->toast(__('Fair removed.'));
    }

    public function render(): View
    {
        return view('livewire.staff.events.index');
    }
}
