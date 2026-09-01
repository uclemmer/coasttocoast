<?php

namespace App\Livewire\Portal;

use App\Livewire\Portal\Concerns\ActsForAnOrganization;
use App\Models\Organization;
use App\Support\Phone;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * An organization editing its own details (card 3.1) — the Livewire replacement for
 * the rep panel's OrganizationProfile page (docs/12).
 *
 * **Active reps only.** A pending or retired rep reaching this URL gets a 403
 * carrying the explanation, not a blank refusal — "not allowed" with no reason
 * is how somebody concludes the site is broken. That refusal happens in
 * `mount()`, before anything renders.
 *
 * The admissions contact is worth the helper text it gets: it is what campaigns
 * fall back to when an organization has no active rep, which is exactly the situation
 * an organization in the middle of a staff change is about to be in.
 *
 * `name` is editable on purpose. Organizations rebrand, and the model re-derives
 * `normalized_name` on save, so the duplicate check and the roster import keep
 * working afterwards.
 */
#[Layout('components.layouts.portal', ['title' => 'Your organization'])]
class OrganizationProfile extends Component
{
    use ActsForAnOrganization;
    use WithFileUploads;

    public string $name = '';

    public string $website = '';

    public string $admissions_office = '';

    public string $admissions_email = '';

    public string $admissions_phone = '';

    public string $address_line1 = '';

    public string $address_line2 = '';

    public string $city = '';

    public string $state = '';

    public string $postal_code = '';

    /** A newly chosen logo, before it is saved. */
    public $logo = null;

    public function mount(): void
    {
        $this->abortUnlessActingForOrganization();

        $organization = $this->currentOrganization();

        foreach ([
            'name', 'website', 'admissions_office', 'admissions_email', 'admissions_phone',
            'address_line1', 'address_line2', 'city', 'state', 'postal_code',
        ] as $field) {
            $this->{$field} = (string) ($organization?->{$field} ?? '');
        }
    }

    public function save(): void
    {
        $this->abortUnlessActingForOrganization();

        $organization = $this->currentOrganization();

        if (! $organization instanceof Organization) {
            return;
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'admissions_office' => ['nullable', 'string', 'max:255'],
            'admissions_email' => ['nullable', 'email', 'max:255'],
            'admissions_phone' => [
                'nullable',
                'string',
                'max:20',
                function (string $attribute, mixed $value, callable $fail): void {
                    if (! Phone::isValid(is_string($value) ? $value : null)) {
                        $fail(__('Enter a phone number we can actually dial, e.g. (423) 757-2845.'));
                    }
                },
            ],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $validated['admissions_phone'] = Phone::normalize($this->admissions_phone ?: null);
        unset($validated['logo']);

        if ($this->logo !== null) {
            $organization->logo_path = $this->logo->store('organization-logos', 'public');
        }

        $organization->fill($validated)->save();

        $this->logo = null;
        $this->toast(__('Saved.'));
    }

    public function render(): View
    {
        return view('livewire.portal.organization-profile', [
            'organization' => $this->currentOrganization(),
        ]);
    }
}
