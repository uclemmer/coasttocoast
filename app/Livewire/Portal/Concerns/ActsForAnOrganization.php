<?php

namespace App\Livewire\Portal\Concerns;

use App\Models\Organization;
use App\Models\User;

/**
 * The membership gate every portal page that *does* something shares (D9).
 *
 * Ported from the Filament rep panel's trait of the same name (docs/12). The
 * rules are unchanged, because they are product rules rather than panel
 * mechanics: pending and retired reps can log in and browse — their school's
 * history is theirs to look at — but they cannot register, apply for a grant or
 * edit the profile.
 *
 * What changed is only how the refusal is delivered. Filament pages called
 * `abortUnless...()` in `mount()` and raised a Filament notification; a
 * Livewire component aborts the same way, and surfaces the explanation as an
 * alert the page renders plus a `ui-toast` for anything that happens after
 * load.
 *
 * `membershipNotice()` is the part worth keeping intact. "Awaiting approval"
 * with no further word is how somebody concludes the site is broken.
 */
trait ActsForAnOrganization
{
    public function currentUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    public function currentOrganization(): ?Organization
    {
        return $this->currentUser()->organization;
    }

    public function actsForOrganization(): bool
    {
        return $this->currentUser()->actsForOrganization();
    }

    /**
     * Refuse a user who is attached to no school at all.
     *
     * Distinct from the membership gate below, and a different question. A
     * pending or retired rep HAS a school and may browse its history; somebody
     * with no school has nothing on these pages to see, and the queries behind
     * them would return an empty set rather than a refusal. The Filament
     * resources drew the same line in `canViewAny()`.
     */
    protected function abortUnlessAttachedToOrganization(): void
    {
        abort_unless(
            $this->currentUser()->organization_id !== null,
            403,
            $this->membershipNotice() ?? __('Not allowed.'),
        );
    }

    /**
     * Refuse the page outright. For anything that writes.
     *
     * Called from `mount()` so the refusal happens before anything renders,
     * exactly as the Filament pages did.
     */
    protected function abortUnlessActingForOrganization(): void
    {
        abort_unless($this->actsForOrganization(), 403, $this->membershipNotice() ?? __('Not allowed.'));
    }

    /**
     * A plain-English explanation of the current membership state, or null when
     * there is nothing to explain.
     *
     * Copy carried over verbatim. It was written to be read by somebody who has
     * just been told they cannot do the thing they came to do.
     */
    public function membershipNotice(): ?string
    {
        $user = $this->currentUser();

        return match (true) {
            $user->organization_id === null => __(
                'Your account is not attached to a school. Contact the fair coordinator to be added.',
            ),
            $user->isPendingApproval() => __(
                'The fair coordinator is confirming that you work at :school. You can look around in the '
                .'meantime; registering and applying for a grant unlock once that is done.',
                ['school' => $user->organization?->name],
            ),
            $user->isRetired() => __(
                'You have retired as a representative of :school, so you can no longer register or edit '
                .'its details. Your history is still here. Contact the coordinator if this was a mistake.',
                ['school' => $user->organization?->name],
            ),
            default => null,
        };
    }

    /**
     * Explain a refusal that happened after the page loaded — a button that
     * should not have been reachable, or a membership that changed mid-session.
     */
    protected function notifyMembershipRefusal(): void
    {
        $this->toast($this->membershipNotice() ?? __('Not allowed.'), 'warning');
    }

    /**
     * Raise a toast through laravel-ui's live region.
     *
     * One method rather than a dispatch scattered through every action, because
     * the event name and payload shape are the package's contract and worth
     * naming in exactly one place.
     */
    protected function toast(string $message, string $variant = 'success'): void
    {
        $this->dispatch('ui-toast', message: $message, variant: $variant);
    }
}
