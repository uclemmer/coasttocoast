<?php

/*
|--------------------------------------------------------------------------
| No env() calls in this file, deliberately
|--------------------------------------------------------------------------
|
| Two reasons, and the second is the load-bearing one.
|
| Larastan flags `env()` outside an application's own config directory, and a
| package's config is outside it by construction — the rule resolves the
| directory against the host app root, not the package. laravel-core's config
| carries no `env()` for the same reason.
|
| More importantly, a package inventing env var names commits every host to
| them. A host that wants `POSTMASTER_ENABLED` publishes this file and writes
| that line itself, which is the normal Laravel package contract: the values
| here are defaults, and the published copy is where deployment concerns go.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | Off until the host turns it on. Installing this package must change
    | nothing about an application that has not asked for it.
    |
    | READ AT BOOT. The capture listeners run on every message sent, so a host
    | who switched this off should stop paying for it entirely rather than pay
    | for a flag check per send. Changing it in a running process changes
    | nothing — restart the web tier AND the queue workers. A worker that has
    | been up since before the change is still running the old decision, and
    | mail is queued more often than not, so this package is more exposed to
    | that than most. `core:doctor` reports the mismatch when laravel-core is
    | installed. See docs/00-architecture.md §7.
    |
    */

    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | The message log
    |--------------------------------------------------------------------------
    */

    'log' => [

        /*
         * Whether to store rendered message bodies.
         *
         * A storage and a privacy decision, and the host's to make: bodies are
         * the bulk of the table and the part most likely to contain something
         * you would rather not keep. Off means the row still records that the
         * message was sent, to whom, and what happened to it — a resend from
         * such a row sends an empty body, and the admin screen says so.
         */
        'store_body' => true,

        /*
         * Delete rows older than this many days. Null keeps them forever —
         * an explicit choice, not a missing value.
         */
        // > 13 months — a cross-year campaign audit trail
        // (docs/07-email-design.md).
        'prune_after_days' => 400,

        /*
         * A row still `sending` this long after it was created never reached
         * the MessageSent event, which is what a failed send looks like:
         * transports do not reliably report failures synchronously. Null
         * disables the promotion, and `postmaster:prune` marks the rest failed.
         */
        'stale_after_minutes' => 15,

        /*
         * Mailables and notifications never to log, by class name. Matching is
         * `is_a()`, so a base class covers its subclasses.
         *
         * The usual reason is a message whose body should not exist twice —
         * a password reset, a one-time code.
         */
        'except_mailables' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin screens
    |--------------------------------------------------------------------------
    |
    | Two ways to reach the same two Livewire components.
    |
    | A host running laravel-core should leave `enabled` FALSE and let core's
    | admin mount them through Integration\Core\AdminScreens — they then appear
    | inside core's shell, with core's navigation, at core's admin path. Turning
    | this on as well gives you the same screens at a second parallel URL, which
    | is almost never what you want.
    |
    | A host without core turns this on. `layout` then has to name a Blade
    | layout that yields a slot; the package ships a minimal one so the screens
    | work out of the box, but a real application should point this at its own.
    |
    */

    'admin' => [

        'enabled' => false,

        'path' => 'admin/messages',

        'middleware' => ['web'],

        /*
         * Null uses the package's own minimal shell. Set to
         * 'core::admin.layout' under laravel-core, or to your own layout.
         */
        // This host runs laravel-core, so the screens render inside core's
        // admin shell at /admin rather than the package's minimal fallback.
        'layout' => 'core::admin.layout',
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider
    |--------------------------------------------------------------------------
    |
    | Which ESP's vocabulary the webhook endpoint speaks. The map exists so
    | that adding a second is a class, not a rewrite of the ingestion path.
    |
    | Not yet wired — ingestion is the next feature. See
    | docs/00-architecture.md §8.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Message streams
    |--------------------------------------------------------------------------
    |
    | Bound to the SAME env vars this app's own mail stamps with
    | (`services.postmark.*`, applied by Notifications\Concerns\RendersThemedMail).
    |
    | These have to agree, and nothing else makes them. postmaster classifies a
    | message by its `X-PM-Message-Stream` header: the broadcast stream counts as
    | marketing, everything else as transactional — and since 0.2.0 an unsubscribe
    | only refuses marketing. So if this app stamped `campaigns` while postmaster
    | still expected `broadcast`, every campaign would classify as transactional
    | and go to people who had unsubscribed. Silently: no error, nothing red.
    |
    | Reading both from one env var is what stops that, rather than two defaults
    | that happen to match. See docs/15 and SuppressionTest.
    |
    */

    'streams' => [
        'transactional' => env('POSTMARK_MESSAGE_STREAM', 'outbound'),
        'broadcast' => env('POSTMARK_BROADCAST_STREAM', 'broadcast'),
    ],

    'driver' => 'postmark',

    'drivers' => [
        //
    ],

];
