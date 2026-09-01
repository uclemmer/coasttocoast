<?php

namespace App\Livewire\Staff\Sponsors;

use App\Livewire\Staff\Concerns\ActsForStaff;
use App\Livewire\Staff\Concerns\ReordersRecords;
use App\Models\Sponsor;
use App\Models\SponsorStaff;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Add or edit a sponsor, and manage the counseling staff listed under it
 * (docs/13) — the Livewire replacement for the admin panel's CreateSponsor and
 * EditSponsor pages plus their StaffRelationManager.
 *
 * ONE COMPONENT FOR BOTH create and edit, routed twice. Filament had two page
 * classes because its resource pattern wants them; here the difference is a
 * null `$sponsor` and one heading, and two classes would be the same form
 * written twice.
 *
 * The staff list only appears when editing. A staff row needs a `sponsor_id`,
 * so there is nothing to attach them to until the sponsor is saved — Filament
 * had the same constraint, expressed as a relation manager that only renders on
 * the edit page.
 */
#[Layout('components.layouts.staff', ['title' => 'Sponsor'])]
class Edit extends Component
{
    use ActsForStaff;
    use ReordersRecords;
    use WithFileUploads;

    public ?Sponsor $sponsor = null;

    public string $name = '';

    public string $website = '';

    /** A newly chosen logo, before saving. Null means "leave what is there". */
    public $logo = null;

    /** Ticked to clear an existing logo on save. */
    public bool $removeLogo = false;

    // --- the staff sub-form ------------------------------------------------

    /** The staff row being edited, or null when the form is adding one. */
    public ?int $editingStaffId = null;

    public string $staffName = '';

    public string $staffTitle = '';

    /** The staff row the delete dialog is asking about. */
    public ?int $deletingStaffId = null;

    public function mount(?Sponsor $sponsor = null): void
    {
        $this->abortUnlessStaff();

        // Route-model binding hands us an unsaved instance on /create, so the
        // key is what distinguishes the two, not null-ness.
        if ($sponsor?->exists) {
            $this->authorize('update', $sponsor);

            $this->sponsor = $sponsor;
            $this->name = $sponsor->name;
            $this->website = (string) $sponsor->website;

            return;
        }

        $this->authorize('create', Sponsor::class);
    }

    public function isEditing(): bool
    {
        return $this->sponsor?->exists === true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            // Matches the Filament field it replaces: images only, 2 MB.
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $sponsor = $this->sponsor;

        if ($sponsor?->exists) {
            $this->authorize('update', $sponsor);
        } else {
            $this->authorize('create', Sponsor::class);

            $sponsor = new Sponsor;
            // Appended, not inserted: a new sponsor going straight to the top
            // of a hand-ordered list would silently demote a paying one.
            $sponsor->sort_order = ((int) Sponsor::query()->max('sort_order')) + 1;
        }

        $sponsor->name = $validated['name'];
        $sponsor->website = $this->website === '' ? null : $this->website;

        if ($this->logo instanceof TemporaryUploadedFile) {
            $this->deleteStoredLogo($sponsor);
            $sponsor->logo_path = $this->logo->store('sponsor-logos', 'public');
        } elseif ($this->removeLogo) {
            $this->deleteStoredLogo($sponsor);
            $sponsor->logo_path = null;
        }

        $sponsor->save();

        $this->sponsor = $sponsor;
        $this->logo = null;
        $this->removeLogo = false;

        session()->flash('status', __('Sponsor saved.'));

        $this->redirect(route('staff.sponsors.edit', $sponsor), navigate: false);
    }

    /**
     * Remove the file behind a sponsor's current logo.
     *
     * Replacing or clearing a logo without this leaves the old file on disk
     * forever — nothing else references it, so nothing else will ever delete
     * it. Filament's FileUpload did this for us.
     */
    protected function deleteStoredLogo(Sponsor $sponsor): void
    {
        if ($sponsor->logo_path !== null && $sponsor->logo_path !== '') {
            Storage::disk('public')->delete($sponsor->logo_path);
        }
    }

    // --- staff -------------------------------------------------------------

    /**
     * @return Collection<int, SponsorStaff>
     */
    #[Computed]
    public function staff(): Collection
    {
        if (! $this->isEditing()) {
            return collect();
        }

        return $this->sponsor->staff()->get();
    }

    public function editStaff(int $staffId): void
    {
        $member = $this->staffMember($staffId);

        if ($member === null) {
            return;
        }

        $this->editingStaffId = $member->getKey();
        $this->staffName = $member->name;
        $this->staffTitle = (string) $member->title;

        $this->dispatch('ui-modal-open', id: 'sponsor-staff');
    }

    public function addStaff(): void
    {
        $this->resetStaffForm();
        $this->dispatch('ui-modal-open', id: 'sponsor-staff');
    }

    public function saveStaff(): void
    {
        $this->authorize('update', $this->sponsor);

        $validated = $this->validate([
            'staffName' => ['required', 'string', 'max:255'],
            'staffTitle' => ['nullable', 'string', 'max:255'],
        ]);

        $member = $this->editingStaffId === null
            ? new SponsorStaff([
                'sponsor_id' => $this->sponsor->getKey(),
                'sort_order' => ((int) $this->sponsor->staff()->max('sort_order')) + 1,
            ])
            : $this->staffMember($this->editingStaffId);

        if ($member === null) {
            $this->toast(__('That person could not be found.'), 'danger');

            return;
        }

        $member->name = $validated['staffName'];
        $member->title = $this->staffTitle === '' ? null : $this->staffTitle;
        $member->save();

        $this->resetStaffForm();
        unset($this->staff);

        $this->dispatch('ui-modal-close', id: 'sponsor-staff');
        $this->toast(__('Staff list updated.'));
    }

    public function confirmDeleteStaff(int $staffId): void
    {
        $this->deletingStaffId = $staffId;
        $this->dispatch('ui-modal-open', id: 'delete-sponsor-staff');
    }

    public function deleteStaff(): void
    {
        $this->authorize('update', $this->sponsor);

        $member = $this->staffMember($this->deletingStaffId);

        if ($member === null) {
            $this->toast(__('That person could not be found.'), 'danger');

            return;
        }

        $member->delete();

        $this->deletingStaffId = null;
        unset($this->staff);

        $this->dispatch('ui-modal-close', id: 'delete-sponsor-staff');
        $this->toast(__('Removed from the staff list.'));
    }

    public function moveStaffUp(int $staffId): void
    {
        $this->swapStaff($staffId, -1);
    }

    public function moveStaffDown(int $staffId): void
    {
        $this->swapStaff($staffId, 1);
    }

    protected function swapStaff(int $staffId, int $offset): void
    {
        $this->authorize('update', $this->sponsor);

        // Scoped to this sponsor, so an id from another one finds nothing to
        // move rather than reordering somebody else's list.
        if ($this->reorderWithin($this->sponsor->staff()->get(), $staffId, $offset)) {
            unset($this->staff);
        }
    }

    /**
     * Resolve a staff row **scoped to this sponsor**.
     *
     * Scoped rather than `SponsorStaff::find()`: the id arrives from the
     * browser, and without the scope a crafted one would edit or delete a
     * person listed under a different sponsor. The same rule the portal's
     * screens follow.
     */
    protected function staffMember(?int $staffId): ?SponsorStaff
    {
        if ($staffId === null || ! $this->isEditing()) {
            return null;
        }

        return $this->sponsor->staff()->find($staffId);
    }

    protected function resetStaffForm(): void
    {
        $this->editingStaffId = null;
        $this->staffName = '';
        $this->staffTitle = '';
        $this->resetValidation();
    }

    /**
     * The page heading is rendered inside the view, not passed to the layout.
     *
     * `#[Layout]`'s data is attribute arguments and therefore constant, and the
     * heading here is the sponsor's name. The portal's ShowRegistration has the
     * same shape and solves it the same way.
     */
    /**
     * The staff-row dialog's inputs are labelled "Name" and "Title"; the
     * properties carry a `staff` prefix to keep them apart from the sponsor's
     * own fields on the same component, and that prefix is not on screen.
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'staffName' => __('name'),
            'staffTitle' => __('title'),
        ];
    }

    public function render(): View
    {
        return view('livewire.staff.sponsors.edit', [
            'pageHeading' => $this->isEditing() ? $this->sponsor->name : __('Add a sponsor'),
        ]);
    }
}
