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
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Who may tell this application the visitor's IP address. Read in
    | bootstrap/app.php.
    |
    | Both public forms throttle on `request()->ip()`. Behind a load balancer or
    | a CDN that is the proxy's address until the proxy is trusted, so every
    | visitor shares one throttle bucket.
    |
    | Blank means trust nothing, which is correct on a plain VPS where the web
    | server is the only thing in front of PHP. `*` trusts whatever is in front
    | of us and is correct when a load balancer is the ONLY route in - but on a
    | directly reachable host it lets anyone forge `X-Forwarded-For` and mint a
    | fresh throttle bucket per request, which removes the limit silently. A
    | comma-separated CIDR list is the middle ground.
    |
    | It lives in config rather than being read with env() at the call site
    | because `config:cache` stops loading .env entirely, and an env() call in
    | bootstrap/app.php would quietly become null in production - the exact
    | environment this setting exists for.
    |
    */

    'trusted_proxies' => env('TRUSTED_PROXIES'),

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
