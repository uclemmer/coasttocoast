<?php

namespace App\Livewire\Staff\Organizations;

use App\Livewire\Staff\Concerns\ActsForStaff;
use App\Models\Organization;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Add or edit an organization (R3.3a) — replaces the admin panel's CreateOrganization
 * and EditOrganization pages (docs/13).
 *
 * The duplicate warning is **surfaced, not blocking** (R2.7). "Boston
 * University" and "Boston College" normalize differently on purpose, so a match
 * is worth a second look rather than a veto — the coordinator knows which
 * organizations are genuinely distinct and the normaliser does not.
 */
#[Layout('components.layouts.staff', ['title' => 'Organization'])]
class Edit extends Component
{
    use ActsForStaff;
    use WithFileUploads;

    public ?Organization $organization = null;

    public string $name = '';

    public string $website = '';

    public $logo = null;

    public bool $removeLogo = false;

    public string $admissions_office = '';

    public string $admissions_email = '';

    public string $admissions_phone = '';

    public string $address_line1 = '';

    public string $address_line2 = '';

    public string $city = '';

    public string $state = '';

    public string $postal_code = '';

    public function mount(?Organization $organization = null): void
    {
        $this->abortUnlessStaff();

        if (! $organization?->exists) {
            $this->authorize('create', Organization::class);

            return;
        }

        $this->authorize('update', $organization);

        $this->organization = $organization;

        foreach ([
            'name', 'website', 'admissions_office', 'admissions_email', 'admissions_phone',
            'address_line1', 'address_line2', 'city', 'state', 'postal_code',
        ] as $field) {
            $this->{$field} = (string) $organization->{$field};
        }
    }

    public function isEditing(): bool
    {
        return $this->organization?->exists === true;
    }

    /**
     * Organizations whose normalised name matches this one.
     *
     * Recomputed as the name is typed, so the warning appears while there is
     * still a chance to stop rather than after saving a second "The Baylor
     * Organization".
     *
     * @return Collection<int, Organization>
     */
    #[Computed]
    public function possibleDuplicates(): Collection
    {
        if (trim($this->name) === '') {
            return collect();
        }

        $candidate = $this->organization ?? new Organization;
        $candidate->name = $this->name;

        // `possibleDuplicates()` hands back a Builder, not a Collection.
        return $candidate->possibleDuplicates()->get();
    }

    public function updatedName(): void
    {
        unset($this->possibleDuplicates);
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'admissions_office' => ['nullable', 'string', 'max:255'],
            'admissions_email' => ['nullable', 'email', 'max:255'],
            'admissions_phone' => ['nullable', 'string', 'max:20'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
        ]);

        $organization = $this->organization;

        if ($organization?->exists) {
            $this->authorize('update', $organization);
        } else {
            $this->authorize('create', Organization::class);
            $organization = new Organization;
        }

        $organization->name = $validated['name'];

        foreach ([
            'website', 'admissions_office', 'admissions_email', 'admissions_phone',
            'address_line1', 'address_line2', 'city', 'state', 'postal_code',
        ] as $field) {
            $organization->{$field} = $this->{$field} === '' ? null : $this->{$field};
        }

        if ($this->logo instanceof TemporaryUploadedFile) {
            $this->deleteStoredLogo($organization);
            $organization->logo_path = $this->logo->store('organization-logos', 'public');
        } elseif ($this->removeLogo) {
            $this->deleteStoredLogo($organization);
            $organization->logo_path = null;
        }

        $organization->save();

        $this->organization = $organization;
        $this->logo = null;
        $this->removeLogo = false;

        session()->flash('status', __('Organization saved.'));

        $this->redirect(route('staff.organizations.show', $organization), navigate: false);
    }

    /** Nothing else references a replaced logo, so nothing else will delete it. */
    protected function deleteStoredLogo(Organization $organization): void
    {
        if ($organization->logo_path !== null && $organization->logo_path !== '') {
            Storage::disk('public')->delete($organization->logo_path);
        }
    }

    public function render(): View
    {
        return view('livewire.staff.organizations.edit', [
            'pageHeading' => $this->isEditing() ? $this->organization->name : __('Add an organization'),
        ]);
    }
}
