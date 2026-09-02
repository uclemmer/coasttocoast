<?php

namespace App\Livewire\Staff\Messages;

use App\Enums\Audience;
use App\Livewire\Staff\Concerns\ActsForStaff;
use App\Models\Message;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use UClemmer\LaravelCore\Support\LikeTerm;

/**
 * The campaign list (doc 07 §3, R3.6) — the Livewire replacement for the admin
 * panel's MessageResource list (docs/13).
 *
 * A SENT MESSAGE IS IMMUTABLE. No edit screen reaches one and it cannot be
 * deleted: it is the record of what a hundred organizations were told and when, and
 * the delivery table hanging off it is only meaningful if the message it
 * describes still says what was sent. Filament expressed that as `canEdit()`
 * and `canDelete()` returning false once sent; here it is `isSent()` guarding
 * both the links and the actions.
 */
#[Layout('components.layouts.staff', ['title' => 'Campaigns', 'heading' => 'Campaigns'])]
class Index extends Component
{
    use ActsForStaff;

    public string $search = '';

    public string $audience = '';

    public ?int $deleting = null;

    public function mount(): void
    {
        $this->abortUnlessStaff();
        $this->authorize('viewAny', Message::class);
    }

    /**
     * @return Collection<int, Message>
     */
    #[Computed]
    public function messages(): Collection
    {
        return Message::query()
            ->with('event')
            ->withCount('recipients')
            ->when($this->search !== '', fn ($query) => $query->whereRaw(LikeTerm::clause('subject'), [LikeTerm::contains($this->search)]))
            ->when($this->audience !== '', fn ($query) => $query->where('audience', $this->audience))
            ->orderByDesc('created_at')
            ->get();
    }

    /** @return array<int, Audience> */
    public function audiences(): array
    {
        return Audience::cases();
    }

    /**
     * "Sent 3 April", "Scheduled for 1 May", or "Draft".
     *
     * One method rather than three branches in the view, because the three
     * states are one question: where is this campaign in its life.
     */
    public function statusLine(Message $message): string
    {
        return match (true) {
            $message->isSent() => __('Sent :when', ['when' => $message->sent_at?->toFormattedDateString()]),
            $message->scheduled_for !== null => __('Scheduled for :when', [
                'when' => $message->scheduled_for->toFormattedDateString(),
            ]),
            default => __('Draft'),
        };
    }

    public function updatedSearch(): void
    {
        unset($this->messages);
    }

    public function updatedAudience(): void
    {
        unset($this->messages);
    }

    public function confirmDelete(int $messageId): void
    {
        $this->deleting = $messageId;
        $this->dispatch('ui-modal-open', id: 'delete-message');
    }

    public function delete(): void
    {
        $message = Message::query()->find($this->deleting);

        if ($message === null) {
            $this->toast(__('That campaign could not be found.'), 'danger');

            return;
        }

        // `MessagePolicy::delete()` refuses a sent campaign. That is the line
        // keeping the delivery record honest.
        $this->authorize('delete', $message);

        $message->delete();

        $this->deleting = null;
        unset($this->messages);

        $this->dispatch('ui-modal-close', id: 'delete-message');
        $this->toast(__('Campaign removed.'));
    }

    public function render(): View
    {
        return view('livewire.staff.messages.index');
    }
}
