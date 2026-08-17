<?php

namespace App\Support;

use UClemmer\LaravelCore\Support\Contracts\ProvidesPermissions;

/**
 * The application's own permissions, registered with laravel-core through
 * `core.permission_providers` and upserted by `php artisan core:sync-permissions`.
 *
 * Core declares its own (`admin.access`, `users.manage`, `roles.manage`, …) —
 * never redeclare those here. One name per thing a coordinator can do; policies
 * and Filament resources check these by name.
 */
class Permissions implements ProvidesPermissions
{
    public const EVENTS_MANAGE = 'events.manage';

    public const ORGANIZATIONS_MANAGE = 'organizations.manage';

    public const REGISTRATIONS_MANAGE = 'registrations.manage';

    public const GRANTS_MANAGE = 'grants.manage';

    public const PAYMENTS_MANAGE = 'payments.manage';

    public const SPONSORS_MANAGE = 'sponsors.manage';

    public const FAQ_MANAGE = 'faq.manage';

    public const MESSAGES_SEND = 'messages.send';

    /**
     * @return array<string, string>
     */
    public static function permissions(): array
    {
        return [
            self::EVENTS_MANAGE => 'Create and edit fair events',
            self::ORGANIZATIONS_MANAGE => 'Manage organizations, rep membership and merges',
            self::REGISTRATIONS_MANAGE => 'Manage registrations, cancellations and exports',
            self::GRANTS_MANAGE => 'Review, approve, deny and revoke grants',
            self::PAYMENTS_MANAGE => 'Record check payments and issue refunds',
            self::SPONSORS_MANAGE => 'Manage sponsors and sponsor staff',
            self::FAQ_MANAGE => 'Manage FAQ items',
            self::MESSAGES_SEND => 'Compose and send campaigns to reps',
        ];
    }

    public static function group(): string
    {
        return 'College Fair';
    }
}
