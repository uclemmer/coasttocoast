<?php

namespace App\Filament\Rep\Concerns;

use App\Models\Organization;
use App\Models\User;
use Filament\Notifications\Notification;

/**
 * The membership gate every portal page that *does* something shares (D9).
 *
 * Pending and retired reps can log in and browse — their school's history is
 * theirs to look at — but they cannot register, apply for a grant or edit the
 * profile. Pages call `abortUnlessActingForOrganization()` in `mount()` so the
 * refusal happens before anything renders, and `membershipNotice()` explains
 * why the buttons are missing rather than leaving a dead-looking page.
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
     * Refuse the page outright. For anything that writes.
     */
    protected function abortUnlessActingForOrganization(): void
    {
        abort_unless($this->actsForOrganization(), 403, $this->membershipNotice() ?? __('Not allowed.'));
    }

    /**
     * A plain-English explanation of the current membership state, or null
     * when there is nothing to explain.
     *
     * Shown as a banner on the browsable pages. "Awaiting approval" with no
     * further word is how somebody concludes the site is broken.
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

    protected function notifyMembershipRefusal(): void
    {
        Notification::make()
            ->title($this->membershipNotice() ?? __('Not allowed.'))
            ->warning()
            ->send();
    }
}
