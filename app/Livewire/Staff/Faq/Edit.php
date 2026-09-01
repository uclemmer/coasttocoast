<?php

namespace App\Livewire\Staff\Faq;

use App\Livewire\Staff\Concerns\ActsForStaff;
use App\Models\FaqItem;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Write or edit one FAQ question (docs/13) — replaces the admin panel's
 * CreateFaqItem and EditFaqItem pages.
 *
 * One component for both, routed twice, as with sponsors.
 *
 * THE ANSWER IS MARKDOWN, and `x-ui::forms.markdown` is a styled textarea
 * rather than a rich editor — the owner's call when the package was built
 * (docs/12). The public page renders it through `Str::markdown()`, so what is
 * typed here and what a visitor reads can diverge; the preview below is here to
 * close that gap rather than to look clever.
 */
#[Layout('components.layouts.staff', ['title' => 'FAQ'])]
class Edit extends Component
{
    use ActsForStaff;
    use WithFileUploads;

    public ?FaqItem $item = null;

    public string $question = '';

    public string $answer = '';

    public bool $is_published = true;

    /**
     * A newly chosen file, before saving. Null means "leave what is there".
     *
     * This is what the signed W-9 hangs off (doc 11's owner queue), but it is
     * deliberately a generic attachment — a floor plan or a parking map is the
     * same shape.
     */
    public $attachment = null;

    /** Ticked to clear an existing attachment on save. */
    public bool $removeAttachment = false;

    public function mount(?FaqItem $item = null): void
    {
        $this->abortUnlessStaff();

        // Route-model binding hands us an unsaved instance on /create.
        if ($item?->exists) {
            $this->authorize('update', $item);

            $this->item = $item;
            $this->question = $item->question;
            $this->answer = $item->answer;
            $this->is_published = $item->is_published;

            return;
        }

        $this->authorize('create', FaqItem::class);
    }

    public function isEditing(): bool
    {
        return $this->item?->exists === true;
    }

    /**
     * The answer as a visitor will see it.
     *
     * Rendered through the same `Str::markdown()` the public FAQ page uses, so
     * the two cannot drift. Not a computed property: it depends on `$answer`,
     * which changes on every keystroke that syncs, and a computed value is
     * cached for the request.
     */
    public function preview(): string
    {
        return Str::markdown($this->answer === '' ? '' : $this->answer);
    }

    public function save(): void
    {
        $validated = $this->validate([
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string'],
            'is_published' => ['boolean'],
            // `mimes` checks the file's actual type, not the extension the
            // browser claimed. PDF only: this field exists to publish documents
            // to a public page, and it is reachable by anyone who can edit the
            // FAQ, so the narrowest useful type is the right one.
            'attachment' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        $item = $this->item;

        if ($item?->exists) {
            $this->authorize('update', $item);
        } else {
            $this->authorize('create', FaqItem::class);

            $item = new FaqItem;
            // Appended: a new question jumping to the top of a hand-ordered
            // list would reorder the page nobody asked to reorder.
            $item->sort_order = ((int) FaqItem::query()->max('sort_order')) + 1;
        }

        $item->question = $validated['question'];
        $item->answer = $validated['answer'];
        $item->is_published = $this->is_published;

        if ($this->attachment instanceof TemporaryUploadedFile) {
            $this->deleteStoredAttachment($item);

            $item->attachment_path = $this->attachment->store(
                FaqItem::ATTACHMENT_DIRECTORY,
                FaqItem::ATTACHMENT_DISK,
            );
            $item->attachment_name = $this->attachment->getClientOriginalName();
        } elseif ($this->removeAttachment) {
            $this->deleteStoredAttachment($item);

            $item->attachment_path = null;
            $item->attachment_name = null;
        }

        $item->save();

        $this->item = $item;
        $this->attachment = null;
        $this->removeAttachment = false;

        session()->flash('status', __('Question saved.'));

        $this->redirect(route('staff.faq.edit', $item), navigate: false);
    }

    /**
     * Remove the file behind a question's current attachment.
     *
     * Replacing or clearing one without this leaves the old file on disk for
     * ever — nothing else references it, so nothing else will ever delete it.
     * Mirrors `Sponsors\Edit::deleteStoredLogo()`.
     */
    protected function deleteStoredAttachment(FaqItem $item): void
    {
        if (filled($item->attachment_path)) {
            Storage::disk(FaqItem::ATTACHMENT_DISK)->delete($item->attachment_path);
        }
    }

    public function render(): View
    {
        return view('livewire.staff.faq.edit', [
            'pageHeading' => $this->isEditing() ? __('Edit question') : __('Add a question'),
        ]);
    }
}
