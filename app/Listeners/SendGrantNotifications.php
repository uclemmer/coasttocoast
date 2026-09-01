<?php

namespace App\Listeners;

use App\Events\GrantApplied;
use App\Events\GrantApproved;
use App\Events\GrantDenied;
use App\Events\GrantRevoked;
use App\Models\Grant;
use App\Notifications\Admin\AdminAlert;
use App\Notifications\GrantDecided;
use Illuminate\Support\Facades\Notification;

/**
 * The comms matrix for fee assistance (R4, card 6.1).
 *
 * Decisions are mailed to the rep who applied, and to the organization's other
 * active reps as well: the applicant may have left by the time a decision
 * lands, and a grant nobody knows about is a discount nobody claims.
 */
class SendGrantNotifications
{
    public function applied(GrantApplied $event): void
    {
        $grant = $event->grant->loadMissing(['event', 'organization', 'requester']);

        AdminAlerts::send(new AdminAlert(
            subject: __('Fee assistance request: :organization', [
                'organization' => (string) $grant->organization?->name,
            ]),
            headline: __('An organization has asked for fee assistance'),
            rows: [
                __('Organization') => $grant->organization?->name,
                __('Fair') => $grant->event?->name,
                __('Asked by') => $grant->requester?->name,
                __('Reason given') => $grant->justification,
            ],
            url: url('/admin/grants'),
            linkLabel: __('Review the request'),
        ));
    }

    public function approved(GrantApproved $event): void
    {
        $this->tellTheOrganization($event->grant);
    }

    public function denied(GrantDenied $event): void
    {
        $this->tellTheOrganization($event->grant);
    }

    public function revoked(GrantRevoked $event): void
    {
        $this->tellTheOrganization($event->grant);
    }

    protected function tellTheOrganization(Grant $grant): void
    {
        $grant->loadMissing(['event', 'organization', 'requester']);

        $recipients = $grant->organization
            ?->activeReps()
            ->pluck('email')
            ->push($grant->requester?->email)
            ->filter()
            // The applicant may already be one of the active reps.
            ->unique()
            ->values() ?? collect();

        foreach ($recipients as $email) {
            Notification::route('mail', $email)->notify(new GrantDecided($grant));
        }
    }
}
