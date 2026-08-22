<?php

namespace App\Livewire\Staff\Faq;

use App\Livewire\Staff\Concerns\ActsForStaff;
use App\Livewire\Staff\Concerns\ReordersRecords;
use App\Models\FaqItem;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The public FAQ (R3.5) — the Livewire replacement for the admin panel's
 * FaqItemResource list (docs/13).
 *
 * A table rather than a content block because the coordinator reorders and
 * unpublishes individual questions, which one block of copy cannot express.
 *
 * Reordering is buttons and is hidden while filtering, for the reasons in
 * docs/13 and in `ReordersRecords`.
 */
#[Layout('components.layouts.staff', ['title' => 'FAQ', 'heading' => 'Frequently asked questions'])]
class Index extends Component
{
    use ActsForStaff;
    use ReordersRecords;

    public string $search = '';

    /**
     * Filament's TernaryFilter, as three states: '' (all), 'yes', 'no'.
     *
     * A string rather than a nullable bool because that is what a `<select>`
     * round-trips; an empty option and a `null` are the same thing to the
     * browser and not to PHP.
     */
    public string $published = '';

    /** @var array<int, string> */
    public array $selected = [];

    public ?int $deleting = null;

    public function mount(): void
    {
        $this->abortUnlessStaff();
        $this->authorize('viewAny', FaqItem::class);
    }

    /**
     * @return Collection<int, FaqItem>
     */
    #[Computed]
    public function items(): Collection
    {
        return FaqItem::query()
            ->when($this->search !== '', fn ($query) => $query->where('question', 'like', '%'.$this->search.'%'))
            ->when($this->published !== '', fn ($query) => $query->where('is_published', $this->published === 'yes'))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /** Only meaningful over the unfiltered list. See ReordersRecords. */
    #[Computed]
    public function canReorder(): bool
    {
        return $this->search === '' && $this->published === '';
    }

    /**
     * Questions still carrying a seeded placeholder.
     *
     * Doc 00 recorded that several FAQ sections exist without saying what they
     * say, and the seeder plants `TODO-OWNER` in those answers. Surfacing the
     * count is how they get found before launch rather than by a
     * representative looking for parking information.
     */
    #[Computed]
    public function needsCopyCount(): int
    {
        return FaqItem::query()->where('answer', 'like', '%TODO-OWNER%')->count();
    }

    public function needsCopy(FaqItem $item): bool
    {
        return Str::contains($item->answer, 'TODO-OWNER');
    }

    public function updatedSearch(): void
    {
        $this->clearListState();
    }

    public function updatedPublished(): void
    {
        $this->clearListState();
    }

    /**
     * Drop the selection whenever the visible set changes.
     *
     * The ticked ids belong to the previous result set; carrying them over
     * would let a bulk delete reach rows the user can no longer see.
     */
    protected function clearListState(): void
    {
        $this->selected = [];
        unset($this->items, $this->canReorder);
    }

    public function moveUp(int $itemId): void
    {
        $this->swap($itemId, -1);
    }

    public function moveDown(int $itemId): void
    {
        $this->swap($itemId, 1);
    }

    protected function swap(int $itemId, int $offset): void
    {
        if (! $this->canReorder) {
            return;
        }

        $ordered = FaqItem::query()->orderBy('sort_order')->orderBy('id')->get();
        $moving = $ordered->firstWhere(fn (FaqItem $item): bool => $item->getKey() === $itemId);

        if ($moving === null) {
            return;
        }

        $this->authorize('update', $moving);

        if ($this->reorderWithin($ordered, $itemId, $offset)) {
            unset($this->items);
        }
    }

    /**
     * Publish or unpublish in place.
     *
     * Filament's toggle lived only in the form, so hiding a question meant
     * opening it. It is the one thing a coordinator does to a FAQ row in a
     * hurry — a question that has gone wrong should come off the public page
     * without a round trip through an editor.
     */
    public function togglePublished(int $itemId): void
    {
        $item = FaqItem::query()->find($itemId);

        if ($item === null) {
            return;
        }

        $this->authorize('update', $item);

        $item->forceFill(['is_published' => ! $item->is_published])->save();

        unset($this->items);

        $this->toast($item->is_published ? __('Question published.') : __('Question hidden from the public page.'));
    }

    public function confirmDelete(int $itemId): void
    {
        $this->deleting = $itemId;
        $this->dispatch('ui-modal-open', id: 'delete-faq-item');
    }

    public function delete(): void
    {
        $item = FaqItem::query()->find($this->deleting);

        if ($item === null) {
            $this->toast(__('That question could not be found.'), 'danger');

            return;
        }

        $this->authorize('delete', $item);

        $item->delete();

        $this->deleting = null;
        unset($this->items, $this->needsCopyCount);

        $this->dispatch('ui-modal-close', id: 'delete-faq-item');
        $this->toast(__('Question removed.'));
    }

    public function deleteSelected(): void
    {
        $this->authorize('deleteAny', FaqItem::class);

        $items = FaqItem::query()->whereKey($this->selected)->get();

        foreach ($items as $item) {
            $this->authorize('delete', $item);
            $item->delete();
        }

        $count = $items->count();
        $this->selected = [];
        unset($this->items, $this->needsCopyCount);

        $this->toast(trans_choice(':count question removed.|:count questions removed.', $count, ['count' => $count]));
    }

    public function render(): View
    {
        return view('livewire.staff.faq.index');
    }
}
