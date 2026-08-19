<?php

namespace App\Providers;

use App\Events\GrantApplied;
use App\Events\GrantApproved;
use App\Events\GrantDenied;
use App\Events\GrantRevoked;
use App\Events\MembershipApproved;
use App\Events\MembershipClaimed;
use App\Events\MembershipDenied;
use App\Events\OrganizationCreated;
use App\Events\RegistrationConfirmed;
use App\Events\RegistrationCreated;
use App\Listeners\LinkEmailLogToRecipient;
use App\Listeners\SendGrantNotifications;
use App\Listeners\SendMembershipNotifications;
use App\Listeners\SendRegistrationNotifications;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use UClemmer\LaravelCore\Events\EmailLogged;

/**
 * The seam between the domain services and the comms matrix (doc 10, D-2.3-a).
 *
 * `RegistrationService`, `GrantService` and `OrganizationService` send no mail.
 * They fire events; this is where those events become email and SMS. The
 * mapping is written out by hand rather than discovered, because a comms
 * matrix that can be read in one place is a comms matrix somebody can check
 * against doc 01's R4 table.
 */
class EventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(RegistrationCreated::class, [SendRegistrationNotifications::class, 'created']);
        Event::listen(RegistrationConfirmed::class, [SendRegistrationNotifications::class, 'confirmed']);

        Event::listen(GrantApplied::class, [SendGrantNotifications::class, 'applied']);
        Event::listen(GrantApproved::class, [SendGrantNotifications::class, 'approved']);
        Event::listen(GrantDenied::class, [SendGrantNotifications::class, 'denied']);
        Event::listen(GrantRevoked::class, [SendGrantNotifications::class, 'revoked']);

        Event::listen(OrganizationCreated::class, [SendMembershipNotifications::class, 'organizationCreated']);
        Event::listen(MembershipClaimed::class, [SendMembershipNotifications::class, 'claimed']);
        Event::listen(MembershipApproved::class, [SendMembershipNotifications::class, 'approved']);
        Event::listen(MembershipDenied::class, [SendMembershipNotifications::class, 'denied']);

        // laravel-core's, not ours: fired after every send is logged. Links a
        // campaign's recipient row to its log row (doc 07 §4).
        Event::listen(EmailLogged::class, LinkEmailLogToRecipient::class);
    }
}
