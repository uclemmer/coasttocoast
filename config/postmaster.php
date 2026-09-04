<?php

use UClemmer\LaravelPostmaster\Ingestion\Drivers\PostmarkDriver;

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

    /*
     * ON for this application. Re-applied after the 0.6.0 config refresh,
     * which reset it to the package default and silently took the
     * suppression guard down with it — see docs/16.
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
        // 400, not 90: > 13 months, so a cross-year campaign audit trail
        // survives (docs/07-email-design.md). Re-applied after the 0.6.0
        // config refresh.
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
    | Suppression
    |--------------------------------------------------------------------------
    |
    | The do-not-send list. Global, and above lists: unsubscribing is about one
    | list, suppression is about an address and applies to transactional mail
    | too.
    |
    */

    'suppression' => [

        /*
         * The send-time guard. On whenever the package is on.
         *
         * READ AT BOOT, like the capture listeners, and for the same reason:
         * this runs on every message the application sends.
         *
         * Turning it off leaves the list being written and never enforced,
         * which is a report rather than a control. There is one honest use for
         * that -- watching what WOULD be blocked before trusting it -- and it
         * is not a state to stay in.
         */
        'enabled' => true,

        /*
         * May transactional mail still reach a suppressed address?
         *
         * ON, and this is the one default in this package chosen to CHANGE
         * behaviour on upgrade rather than preserve it -- because the old
         * behaviour was a bug. An unsubscribe and a spam complaint are
         * statements about commercial mail; refusing a password reset on the
         * strength of one locks somebody out of their own account for a reason
         * they will never work out. CAN-SPAM and the GDPR both exempt mail that
         * serves an existing relationship.
         *
         * Which reasons still refuse everything is not configurable, because it
         * is not a preference: `SuppressionReason::refusesTransactional()` says
         * hard bounces and manual entries do. A hard-bounced mailbox does not
         * exist, so the send reaches nobody and only adds to the bounce rate
         * that gets a sending domain throttled -- tell the person in the
         * interface instead.
         *
         * Setting this false restores the old behaviour: every suppression
         * refuses every message. Defensible for an application whose mail is
         * all marketing; a lockout for one with accounts in it.
         *
         * READ THIS IF YOU SEND CAMPAIGNS OUTSIDE THIS PACKAGE. Classification
         * is by message stream, and only this package's broadcast path stamps
         * one -- so unstamped mail counts as transactional and WILL be
         * delivered to people who unsubscribed. Stamp your own campaigns with
         * `Streams::stamp($message, Streams::broadcast())`, or list their
         * streams under `streams.marketing` below.
         */
        'transactional_exempt' => true,

        /*
         * How long a SOFT suppression lasts before it lapses.
         *
         * Only soft bounces get an expiry; hard bounces and complaints never
         * do. A full mailbox in March must not silence somebody forever.
         *
         * Null means soft entries never expire either -- defensible for a host
         * that would rather re-add by hand than re-mail a broken address.
         */
        'soft_expires_after_days' => 30,

        /*
         * How many soft bounces for ONE address, inside the window, before it
         * is suppressed at all.
         *
         * 1 means suppress on the first, which is what this package did before
         * the option existed and stays the default -- nobody's behaviour
         * changes by upgrading.
         *
         * Raise it to tolerate transient failures. A mailbox that is full for
         * an afternoon, a DNS blip, a greylisting server: each produces a soft
         * bounce and none of them means the address has stopped working.
         * Suppressing on one costs you a correspondent for
         * `soft_expires_after_days`; counting to three costs you nothing and
         * still catches the address that has genuinely gone.
         *
         * The evidence is `postmaster_message_events` -- ingestion records
         * every event with its recipient whether or not it correlated to a
         * logged message, so the count is already there and no second table is
         * needed. Duplicate webhook deliveries are fingerprinted and never
         * reach the counter twice.
         *
         * Only soft bounces are counted. Hard bounces, complaints and
         * unsubscribes suppress on the first event at any threshold, because
         * none of them is a maybe.
         */
        'soft_bounce_threshold' => 1,

        /*
         * The window the threshold counts over, ending now.
         *
         * It is what stops three soft bounces eighteen months apart from adding
         * up to a suppression. Ignored while the threshold is 1.
         */
        'soft_bounce_window_days' => 30,

        /*
         * When this application releases an address, ask the provider to
         * release it too.
         *
         * OFF by default because it needs `ingestion.server_token`, and a host
         * that has not set one would otherwise queue a job per release that can
         * only fail.
         *
         * Leaving it off is not neutral, though: the provider keeps its own
         * list, so a released address stays blocked THERE. This application
         * hands the message over, the API accepts it, the provider drops it,
         * and the message log records a send that never arrived. That is the
         * silent half of the divergence -- see docs/06-reconciliation.md.
         */
        'push_releases' => false,
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

        /*
         * A path per area rather than one prefix. A suppression is not a
         * message and a list is not a message, so nesting them under the
         * message log would give each a URL that says something untrue.
         */
        'suppressions_path' => 'admin/suppressions',

        'lists_path' => 'admin/mailing-lists',

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
    | Lists and subscription
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Message streams
    |--------------------------------------------------------------------------
    |
    | Keeping bulk and transactional apart is the single highest-value thing a
    | sender can do about deliverability, and it costs one header: a badly
    | received campaign then damages the reputation of the broadcast stream and
    | leaves receipts and password resets alone.
    |
    | These are Postmark's default stream names. A host with its own streams
    | changes them here; a provider that names the concept differently is a
    | change to Mail\Streams and nothing else.
    |
    */

    'streams' => [
        'transactional' => env('POSTMARK_MESSAGE_STREAM', 'outbound'),
        'broadcast' => env('POSTMARK_BROADCAST_STREAM', 'broadcast'),

        /*
         * Any FURTHER streams that carry marketing, for a host running more
         * than one campaign stream. The broadcast stream above is always
         * treated as marketing whether or not it is repeated here, so this
         * cannot be mis-edited into delivering campaigns to people who left.
         */
        'marketing' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | The public subscription surface
    |--------------------------------------------------------------------------
    |
    | This package's ONLY public routes. Off by default like everything else --
    | a host that only wants a message log should not gain a signup form by
    | installing it.
    |
    */

    'subscription' => [

        'enabled' => false,

        /*
         * These render pages and flash to the session, so `web` is the sensible
         * default. Throttling is applied on top regardless.
         */
        'middleware' => ['web'],

        // Laravel's throttle syntax: attempts,minutes.
        'throttle' => '10,1',

        /*
         * The time-trap. Nobody reads a form, types an address and submits in
         * under this many seconds; anything faster is not a person.
         *
         * Zero disables it. The honeypot is separate and always on.
         */
        'minimum_seconds' => 2,
    ],

    'lists' => [

        /*
         * How long a confirmation link stays valid. Single-use regardless.
         *
         * The UNSUBSCRIBE link is a different thing entirely and has no expiry
         * at all -- it lives in every message ever sent to somebody and has to
         * keep working from an archive years later. See the memberships
         * migration for why that asymmetry is deliberate.
         */
        'confirmation_expires_after_hours' => 48,

        /*
         * One confirmation email per this many minutes, per membership.
         *
         * A public form that emails an arbitrary address on submit is a mail
         * amplifier pointed at anyone; this is what makes hammering it useless.
         */
        'confirmation_throttle_minutes' => 5,

        /*
         * How long an unconfirmed signup is kept before
         * `postmaster:prune-memberships` discards it.
         *
         * A DIFFERENT question from the token expiry above, which is about a
         * bearer credential's blast radius. This is retention: a pending row is
         * an unproven claim about somebody's address made by a form anyone can
         * submit for anyone, and keeping those forever means holding addresses
         * nobody agreed to give you — the exact thing double opt-in is for.
         *
         * Long enough for a holiday, and long enough that a late clicker still
         * meets a page that explains itself rather than the signup form with no
         * memory of them.
         *
         * Zero or less turns pruning OFF rather than pruning everything. A
         * misread config must not empty the pending list.
         */
        'prune_unconfirmed_after_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Ingestion — provider webhooks
    |--------------------------------------------------------------------------
    |
    | What the provider tells us happened after a message left: delivered,
    | bounced, complained, opened, clicked, unsubscribed.
    |
    */

    'ingestion' => [

        /*
         * How long ingested events are kept, for `postmaster:cycle-suppressions
         * --events`. Nothing prunes them without that flag.
         *
         * These rows are what the soft-bounce threshold counts, so shortening
         * this shortens everybody's tally -- an address that bounced twice last
         * month starts again from one. Zero disables pruning entirely.
         */
        'retain_events_days' => 180,

        'enabled' => false,

        /*
         * The shared secret. Postmark does not sign its payloads the way Stripe
         * does, so credentials are the whole check -- presented either as HTTP
         * basic auth on the webhook URL (Postmark's own recommendation) or as
         * an X-Postmark-Token header.
         *
         * EMPTY MEANS THE ENDPOINT REFUSES EVERYTHING. That is deliberate: an
         * open bounce endpoint lets anybody suppress an arbitrary address,
         * which is a denial of service against this application's own password
         * resets. Publish this file and set it from the environment.
         */
        'secret' => '',

        /*
         * A Postmark SERVER token, which is NOT the webhook secret above.
         *
         * Only reconciliation needs it -- reading and writing the provider's
         * own suppression list over its API. A host that only receives webhooks
         * leaves this empty, and `postmaster:reconcile-suppressions` says so
         * rather than half-working.
         */
        'server_token' => '',

        /*
         * Where the endpoint lives. Registered outside the `web` group -- a
         * machine POST has no session and no CSRF token.
         */
        'path' => 'postmaster/webhook',

        'driver' => 'postmark',

        /*
         * Name => driver class. A host adds its own ESP here rather than
         * editing the ingestion path; the endpoint also accepts the name as a
         * URL segment, so two providers can run at once during a migration.
         */
        'drivers' => [
            'postmark' => PostmarkDriver::class,
        ],
    ],

];
