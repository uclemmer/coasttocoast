<?php

namespace App\Livewire\Staff\Organizations;

use App\Livewire\Staff\Concerns\ActsForStaff;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * One school, and its representatives (docs/13) — replaces the admin panel's
 * ViewOrganization page and its RepresentativesRelationManager.
 *
 * Four membership decisions live here: approve a claim, deny one, retire a rep,
 * reinstate one. **Every one goes through `OrganizationService`** rather than
 * writing the columns directly, because each has consequences beyond the row —
 * approving sends mail, retiring changes who a campaign reaches. The Filament
 * relation manager made the same choice for the same reason.
 */
#[Layout('components.layouts.staff', ['title' => 'School'])]
class Show extends Component
{
    use ActsForStaff;

    public Organization $organization;

    /** The rep a dialog is open for. */
    public ?int $acting = null;

    public string $reason = '';

    public function mount(Organization $organization): void
    {
        $this->abortUnlessStaff();
        $this->authorize('view', $organization);

        $this->organization = $organization;
    }

    /** Re-read: every action below changes what this page shows. */
    #[Computed]
    public function record(): Organization
    {
        return Organization::query()
            ->withCount(['activeReps', 'registrations'])
            ->findOrFail($this->organization->getKey());
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function representatives(): Collection
    {
        return $this->record->users()->with('retiredBy')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Organization>
     */
    #[Computed]
    public function possibleDuplicates(): Collection
    {
        return $this->record->possibleDuplicates()->get();
    }

    public function approveClaim(int $userId, OrganizationService $service): void
    {
        $this->runMembership($userId, fn (User $rep) => $service->approveClaim($rep, $this->currentUser()),
            __('Claim approved.'));
    }

    public function reinstate(int $userId, OrganizationService $service): void
    {
        $this->runMembership($userId, fn (User $rep) => $service->reinstate($rep, $this->currentUser()),
            __('Representative reinstated.'));
    }

    public function startDeny(int $userId): void
    {
        $this->openDialog($userId, 'deny-claim');
    }

    public function startRetire(int $userId): void
    {
        $this->openDialog($userId, 'retire-rep');
    }

    protected function openDialog(int $userId, string $modal): void
    {
        $this->acting = $userId;
        $this->reason = '';
        $this->resetValidation();
        $this->dispatch('ui-modal-open', id: $modal);
    }

    public function denyClaim(OrganizationService $service): void
    {
        $this->runMembership(
            $this->acting,
            fn (User $rep) => $service->denyClaim($rep, $this->currentUser(), $this->reason === '' ? null : $this->reason),
            __('Claim denied.'),
            'deny-claim',
        );
    }

    public function retire(OrganizationService $service): void
    {
        $this->runMembership(
            $this->acting,
            fn (User $rep) => $service->retire($rep, $this->currentUser()),
            __('Representative retired.'),
            'retire-rep',
        );
    }

    /**
     * Resolve the rep **within this school**, authorise, call the service.
     *
     * Scoped rather than `User::find()`: the id arrives from the browser, and
     * without the scope a crafted one would retire somebody at a different
     * school. The relation manager scoped to its owner for us.
     */
    protected function runMembership(?int $userId, callable $operation, string $success, ?string $modal = null): void
    {
        $this->authorize('update', $this->record);

        $rep = $userId === null ? null : $this->record->users()->find($userId);

        if ($rep === null) {
            $this->toast(__('That representative could not be found at this school.'), 'danger');

            return;
        }

        try {
            $operation($rep);
        } catch (Throwable $e) {
            // The service's messages are written to be read.
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->acting = null;
        $this->reason = '';
        unset($this->record, $this->representatives);

        if ($modal !== null) {
            $this->dispatch('ui-modal-close', id: $modal);
        }

        $this->toast($success);
    }

    public function render(): View
    {
        return view('livewire.staff.organizations.show');
    }
}
