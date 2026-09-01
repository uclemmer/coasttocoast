<?php

namespace App\Listeners;

use App\Events\MembershipApproved;
use App\Events\MembershipClaimed;
use App\Events\MembershipDenied;
use App\Events\OrganizationCreated;
use App\Notifications\Admin\AdminAlert;
use App\Notifications\MembershipDecided;

/**
 * The comms matrix for signup and membership (D9, R4, card 6.1).
 *
 * The two coordinator alerts here are the load-bearing ones. A claim sitting
 * unapproved is somebody staring at "awaiting approval" with no idea whether
 * anyone has seen it; a new organization created without one is a duplicate nobody
 * spots until two invoices go out.
 */
class SendMembershipNotifications
{
    public function organizationCreated(OrganizationCreated $event): void
    {
        AdminAlerts::send(new AdminAlert(
            subject: __('New organization added: :organization', ['organization' => $event->organization->name]),
            headline: __('A representative added an organization we did not have'),
            rows: [
                __('Organization') => $event->organization->name,
                __('Added by') => $event->founder->name.' <'.$event->founder->email.'>',
                __('Website') => $event->organization->website,
                // The warning the rep saw and pressed past (R2.7). They are
                // allowed to; somebody should still look.
                __('Possible duplicates') => $event->possibleDuplicates === []
                    ? null
                    : implode(', ', $event->possibleDuplicates),
            ],
            url: url('/admin/organizations'),
            linkLabel: __('Open the directory'),
        ));
    }

    public function claimed(MembershipClaimed $event): void
    {
        AdminAlerts::send(new AdminAlert(
            subject: __('Approval needed: :name at :organization', [
                'name' => $event->rep->name,
                'organization' => $event->organization->name,
            ]),
            headline: __('Somebody is waiting to be confirmed'),
            rows: [
                __('Person') => $event->rep->name.' <'.$event->rep->email.'>',
                __('Organization') => $event->organization->name,
            ],
            url: url('/admin/organizations'),
            linkLabel: __('Approve or deny'),
        ));
    }

    public function approved(MembershipApproved $event): void
    {
        $event->rep->notify(new MembershipDecided(
            approved: true,
            organization: $event->rep->organization,
        ));
    }

    public function denied(MembershipDenied $event): void
    {
        $event->rep->notify(new MembershipDecided(
            approved: false,
            organization: $event->organization,
            reason: $event->reason,
        ));
    }
}
