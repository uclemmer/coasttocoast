<?php

/**
 * Facts about the fair itself that are not rows in a table.
 *
 * One source for anything that has to look the same in a Filament panel, a
 * public page and an email — email cannot read a Vite asset or a Tailwind
 * token, so the shared values live here as plain scalars and absolute URLs
 * (doc 07 §1, "Brand tokens").
 *
 * Content that the coordinator should be able to edit without a deploy does
 * NOT belong here — that is laravel-core's Content module (doc 03).
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Contact block
    |--------------------------------------------------------------------------
    |
    | Rendered in the public footer, in the email layout footer, and on the
    | printable check form. The postal address is also what keeps campaign mail
    | CAN-SPAM compliant, so it is not optional decoration.
    |
    */

    'contact' => [
        'name' => env('FAIR_CONTACT_NAME', 'Meg Conner'),
        'email' => env('FAIR_CONTACT_EMAIL', 'contact@coasttocoastcollegefair.com'),
        'phone' => env('FAIR_CONTACT_PHONE', '(423) 757-2845'),
        'address_line1' => env('FAIR_CONTACT_ADDRESS1', '171 Baylor School Road'),
        'address_line2' => env('FAIR_CONTACT_ADDRESS2', ''),
        'city' => env('FAIR_CONTACT_CITY', 'Chattanooga'),
        'state' => env('FAIR_CONTACT_STATE', 'TN'),
        'postal_code' => env('FAIR_CONTACT_POSTAL', '37405'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Coordinator account
    |--------------------------------------------------------------------------
    |
    | Who `CoordinatorSeeder` provisions. Outside local/testing the seeder sets
    | an unknown random password and expects a reset link to be sent, so these
    | values identify the account without granting access to it.
    |
    */

    // `?:` rather than an env() default: .env.example ships these keys blank,
    // and a blank value must fall through to the default rather than produce a
    // coordinator with no email address.
    'coordinator' => [
        'name' => env('COORDINATOR_NAME') ?: 'Fair Coordinator',
        'email' => env('COORDINATOR_EMAIL') ?: 'admin@example.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Brand
    |--------------------------------------------------------------------------
    |
    | Shared by the Filament theme and the email layout. The logo must be an
    | ABSOLUTE url served from public/ — a Vite-hashed asset path does not
    | resolve in a mail client (doc 07 §1). Card 6.0 fills the real values in
    | once Phase 5 has pulled the colours off the current site.
    |
    */

    'brand' => [
        'color_primary' => env('FAIR_BRAND_COLOR', '#1d4ed8'),
        'logo_url' => env('FAIR_BRAND_LOGO_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin alerts
    |--------------------------------------------------------------------------
    |
    | Where new-registration and payment-received notices go (card 6.2). SMS is
    | opt-in per channel: with no number configured, alerts stay email-only
    | rather than failing.
    |
    */

    'alerts' => [
        'enabled' => (bool) env('ADMIN_ALERTS_ENABLED', true),
        'email' => env('ADMIN_ALERT_EMAIL'),
        'phone' => env('ADMIN_ALERT_PHONE'),
    ],

];
