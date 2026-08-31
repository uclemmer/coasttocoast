<?php

use App\Support\Permissions;
use UClemmer\LaravelPostmaster\Integration\Core\AdminScreens;

/*
 * Published from uclemmer/laravel-core's config/core.php and edited for Coast to
 * Coast College Fair — see docs/02-architecture.md, "laravel-core integration
 * checklist". When the package's config gains keys, publish a scratch copy with
 * --force and diff it against this file.
 */

// config for UClemmer/LaravelCore
return [

    /*
    |--------------------------------------------------------------------------
    | Authentication, roles & permissions (docs/01-authentication.md)
    |--------------------------------------------------------------------------
    */
    'auth' => [
        'enabled' => true,

        /*
         * The host application's user model. Everything in the package resolves
         * the users table, primary key type and foreign keys from this class —
         * nothing is hardcoded.
         *
         * Written as a string rather than `App\Models\User::class` so that
         * static analysis of the package itself doesn't require a class that
         * only exists in the host application.
         */
        'user_model' => 'App\\Models\\User',

        /*
         * Users holding this role bypass every permission check (Gate::before).
         * Set to null to disable the bypass entirely.
         */
        'super_admin_role' => 'super-admin',

        /*
         * The package's own login / logout / password-reset / two-factor
         * routes. ON here, and that is the whole reason the rep portal can
         * stop being a Filament panel: Filament owned every authenticated
         * surface in this app, with no Fortify or Breeze behind it (docs/12).
         *
         * No prefix, so these land at /login, /forgot-password and so on. The
         * package's default 'core' prefix is meant for an app that already has
         * its own auth and needs the package's to sit out of the way; this app
         * has none, so these ARE its auth.
         *
         * The route NAMES are still prefixed - `core.login`, `core.logout` -
         * because the package sets that and it is not configurable. Anything
         * linking to them uses those names; see the `login` alias in
         * routes/web.php for the one place that matters.
         */
        'routes' => [
            'enabled' => true,
            'prefix' => '',
            'middleware' => ['web'],

            // Reps land in the portal; logging out returns them to the site.
            'redirect_to' => '/portal',
            'redirect_after_logout' => '/',
        ],

        /*
         * Core's own registration stays OFF, deliberately. Signing up here is
         * not "create a user": it claims or creates a school, and which of
         * those it is decides whether the account is active immediately or
         * waits on a coordinator (D9). That logic is this application's, so
         * registration is app-owned - see routes/web.php and docs/12.
         */
        'registration' => [
            'enabled' => false,
        ],

        /*
         * TOTP two-factor authentication (RFC 6238), implemented in-house — no
         * third-party auth package, nothing to install. The host user model
         * needs `use UClemmer\LaravelCore\Auth\HasTwoFactorAuth;` and the
         * published add_core_two_factor_columns_to_users_table migration.
         *
         * The defaults below are what every authenticator app expects; change
         * them only if you know why. `window` is how many 30-second steps
         * either side of now are accepted, to absorb clock drift.
         */
        'two_factor' => [
            'enabled' => false,
            'digits' => 6,
            'period' => 30,
            'window' => 1,
        ],
    ],

    /*
    | Email logging left core in 0.5.0 for `uclemmer/laravel-postmaster`. Its
    | settings live in `config/postmaster.php` now, including the 400-day
    | retention this app needs for a cross-year campaign audit trail. Removed
    | rather than emptied: a leftover key here reads as configuration and is
    | actually inert.
    */

    /*
    |--------------------------------------------------------------------------
    | Content management (docs/05-content-management.md)
    |--------------------------------------------------------------------------
    |
    | Pages, posts and blocks. Content lives in the database; a Blade file may
    | override how any given item RENDERS, but never whether it exists — the
    | database still owns slug, status, scheduling, meta and permissions.
    |
    */
    'content' => [
        'enabled' => true,

        /*
         * A snapshot of the previous version is taken whenever the title,
         * body, format or meta of an item changes. Publishing, scheduling and
         * archiving are not revisioned — they change no authored text.
         *
         * `keep` trims to the newest N per item; zero or less keeps every
         * revision forever, which is what you want if this is your audit log.
         */
        'revisions' => [
            /*
             * Read at USE, not at boot: the observer is always attached and
             * re-reads this every time an item is saved, so a change takes
             * effect immediately. That is the package's default; the boot-time
             * exceptions are marked as such (email log, queue metrics), and the
             * rule is written down in docs/00-architecture.md.
             */
            'enabled' => true,
            'keep' => 20,
        ],

        /*
         * File overrides. A view at `{path}.{slug}` replaces the stored body
         * for that item, e.g. resources/views/core/pages/about-us.blade.php
         * for the page with slug "about-us". The view receives $content.
         *
         * A file with no published database row renders nothing.
         */
        'overrides' => [
            'enabled' => true,
            'paths' => [
                'page' => 'core.pages',
                'post' => 'core.posts',
                'block' => 'core.blocks',
            ],
        ],

        // Optional catch-all route: GET /{prefix}/{slug}. Prefixed on purpose.
        'routes' => [
            'enabled' => false,
            'prefix' => 'p',
            'middleware' => ['web'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue / job maintenance (docs/04-queue-maintenance.md)
    |--------------------------------------------------------------------------
    |
    | Maintenance tooling over Laravel's own queue tables — not a Horizon
    | replacement. If you run Horizon, this module still covers non-Redis
    | connections, but consider turning it off to avoid duplicating its UI.
    |
    */
    'queue' => [
        /*
         * READ AT BOOT for the metrics listeners below; read at use for the
         * admin pages, the failed-job tooling and the command. In practice that
         * means turning metrics on or off needs a **queue worker restart** —
         * the worker is the process holding the decision, and it is the one
         * nobody remembers to cycle. `core:doctor` says so when they disagree.
         */
        'enabled' => true,

        'metrics' => [
            // Separate flag: this one costs an insert on EVERY job.
            // Also read at boot — see the note on `enabled` above.
            'enabled' => true,
            'prune_after_days' => 14,   // null ⇒ keep forever
        ],

        // Failures older than this are called out as "nobody noticed".
        'stale_failed_alert_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | User profiles & settings (docs/06-user-profiles-settings.md)
    |--------------------------------------------------------------------------
    |
    | Extended profile data for host-app users, and a typed settings store at
    | app and user scope. The package never touches your users table — profiles
    | live alongside it, keyed on the configured user model.
    |
    */
    'profile' => [
        'enabled' => true,

        'avatars' => [
            'disk' => 'public',
            'directory' => 'core/avatars',
            'max_kb' => 2048,

            /*
             * An allowlist, checked against the mime finfo SNIFFS from the file
             * contents — never the one the browser claimed and never the
             * extension. Anything not on this list is a rejection.
             */
            'mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],

            // Rejects decompression-bomb sized images before anything is stored.
            'max_dimensions' => [4000, 4000],

            /*
             * What avatarUrl() returns when the user has uploaded nothing:
             *
             *   'initials'  a deterministic inline SVG data URI — no network
             *               call, no dependency, never a broken image
             *   'gravatar'  the standard hashed gravatar URL with d=mp
             *   null        nothing; your views decide. A null return is a
             *               valid state, not an error.
             */
            'fallback' => 'initials',
        ],

        /*
         * Registered settings defaults.
         *
         * Keys are FLAT strings that happen to contain dots by convention —
         * 'notifications.digest' is one key, not a path into a 'notifications'
         * array. The service reads them with array_key_exists for exactly that
         * reason.
         *
         * A key listed here is the documented, typed surface; unknown keys are
         * still allowed, they just have no default to fall back to.
         */
        'settings' => [
            'defaults' => [
                // 'notifications.digest' => true,
            ],

            'cache_key' => 'core.settings.app',
        ],

        /*
         * Self-service pages at /{prefix}/profile and /{prefix}/settings.
         * Off by default: most host apps have their own account area.
         *
         * The middleware is yours. The default assumes Laravel's own `auth`
         * alias and therefore a route named `login`; if you use the package's
         * auth scaffolding instead, swap `auth` for `core.auth`, which
         * redirects to `core.login`.
         */
        'routes' => [
            'enabled' => false,
            'prefix' => 'account',
            'middleware' => ['web', 'auth'],
        ],

        /*
         * Read-only public profile pages at /{prefix}/{user}.
         *
         * Opt-in twice over: the route only exists when this is true, and it
         * only renders profiles whose owner set `is_public`. Email addresses
         * are never shown.
         */
        'public' => [
            'enabled' => false,
            'prefix' => 'u',
            'middleware' => ['web'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Contact form (docs/08-contact.md)
    |--------------------------------------------------------------------------
    |
    | A public contact form: `<x-core::contact-form />` anywhere, or a
    | ready-made page at /contact. Submissions are stored first and mailed
    | second — a receipt to the sender, an alert to whoever is listed below.
    |
    | With uclemmer/laravel-tickets installed, each submission also opens a
    | ticket. That happens through the `ContactSubmitted` event; this package
    | knows nothing about tickets.
    |
    */
    'contact' => [
        'enabled' => true,

        'routes' => [
            // The POST endpoint. Needed even if you embed the component in
            // your own page.
            'enabled' => true,
            'path' => 'contact',
            // A bare-bones /contact page. Turn off if you have your own.
            // Off: our public Contact page embeds <x-core::contact-form /> (card 5.4).
            'page' => false,
            'middleware' => ['web'],
            // Laravel's throttle syntax: attempts,minutes — per IP.
            'throttle' => '5,1',
        ],

        // Who is alerted. Empty falls back to `mail.from.address`, so a host
        // who forgets still finds the messages somewhere obvious.
        'recipients' => array_values(array_filter([env('ADMIN_ALERT_EMAIL')])),

        // Prepended to both mail subjects, e.g. '[Acme]'.
        'subject_prefix' => '[Coast to Coast College Fair]',

        // The confirmation to the sender. Off means they are told nothing by
        // email — the form still confirms on screen.
        'send_receipt' => true,

        /*
         * Cheap spam defences; neither is a CAPTCHA. The honeypot field is
         * rendered visually hidden (not `type=hidden`, which bots skip), and
         * `min_seconds` rejects submissions faster than a person can type.
         * Set `min_seconds` to 0 to disable the time-trap entirely.
         */
        'honeypot' => [
            'field' => 'website',
            'min_seconds' => 2,
        ],

        'user' => [
            /*
             * Attach submissions to a user account with the same email — and,
             * when this is true, CREATE that account if none exists.
             *
             * Creating accounts from an unauthenticated public form has real
             * consequences: the address is unverified, so anyone can mint an
             * account for anyone; spam that beats the honeypot becomes users;
             * and the account holds a random password nobody can use. It is
             * the right choice when every contact becomes a ticket somebody
             * answers, and the wrong one for a plain "email us" box.
             *
             * **It needs a claim path to be safe.** Core's own registration
             * ignores provisional accounts in its uniqueness check and claims
             * them instead — but core cannot patch a signup it does not own, so
             * on Breeze, Fortify, Jetstream or your own controller you must
             * wire that yourself. Four lines; see docs/08-contact.md, "Wiring
             * the claim path into your own registration". Without it, anybody
             * can block a stranger's signup by typing their address into your
             * contact form.
             *
             * See docs/08-contact.md before switching it on.
             */
            'auto_create' => false,

            /*
             * Caps on CREATION only — never on accepting a message. Accepting
             * an enquiry is cheap; minting a row in your users table is not,
             * and the two decisions deserve different limits. Over a cap, the
             * submission is stored and mailed as usual, just unattributed.
             *
             * 0 disables a cap.
             */
            'max_per_ip_per_day' => 3,
            'max_per_domain_per_day' => 10,

            // A message with this many links is spam often enough that it is
            // not worth an account. It is still stored and still mailed.
            'max_links' => 3,

            /*
             * Delete provisional accounts nobody ever claimed, after this many
             * days. Null (the default) never deletes.
             *
             * Off by default on purpose: deleting a user can cascade into host
             * tables this package cannot see — orders, subscriptions, anything
             * with a user FK. Turn it on once you know what a user delete does
             * in YOUR schema.
             */
            'prune_provisional_after_days' => null,

            /*
             * Your own rule instead. Any class implementing
             * UClemmer\LaravelCore\Contact\Contracts\ResolvesContactUsers —
             * the place to put "only for verified domains", or to satisfy the
             * required columns on your own user model.
             */
            'resolver' => null,
        ],

        // Submissions older than this are deleted by
        // `core:prune-contact-submissions`. Null keeps them forever.
        'prune_after_days' => 365,
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin panel (docs/02-admin-dashboard.md)
    |--------------------------------------------------------------------------
    |
    | `enabled` mounts the package's admin — ordinary routes over Livewire
    | components as of core 0.4, where it was a Filament panel before. Leave it
    | false to have no /admin at all; there is no longer a "bring your own
    | panel" alternative, because there is no panel.
    |
    | A module shows up only when its toggle here AND the owning feature's own
    | `enabled` flag are both true.
    |
    */
    'admin' => [
        'enabled' => true,
        'path' => 'admin',
        'brand' => 'Coast to Coast College Fair',

        /*
         * `colors` and `vite_theme` were removed in core 0.4 and are gone from
         * here with it. Both were Filament's: a hex string expanded into a
         * Filament palette, and a path to a compiled Filament theme. The admin
         * is Livewire on uclemmer/laravel-ui now and takes its colours from
         * that package's design tokens, which this app's own stylesheet
         * already imports.
         */

        // Asset path or absolute URL. Rendered by the core::admin.brand view,
        // which you can publish and rewrite entirely.
        'logo' => null,
        'logo_height' => '1.5rem',
        'favicon' => null,

        /*
         * Screen providers attached to the admin, as class-strings. This is how
         * add-on packages (e.g. uclemmer/laravel-tickets) and host apps
         * contribute without owning it:
         *
         *     'plugins' => [UClemmer\LaravelTickets\Admin\TicketsScreens::class],
         *
         * Same key it was under Filament; the contract is core's
         * `ProvidesAdminScreens` since 0.4, and a leftover Filament plugin
         * class here throws on boot with a message naming the replacement.
         */
        /*
         * The fair's own resources left Filament for the Livewire screens at
         * /staff on 2026-08-21 (docs/13), and core went headless on 2026-08-22.
         * /admin serves users, roles, content and settings as Livewire screens
         * rather than a panel.
         *
         * The message log used to be in that list and is not any more: core
         * 0.5.0 gave it up, and `uclemmer/laravel-postmaster` contributes it
         * back through this key. That is the only entry here — the fair's own
         * screens still live at /staff and are not contributed to /admin.
         */
        'plugins' => [
            AdminScreens::class,
        ],

        'modules' => [
            'users' => true,
            'queue' => true,
            'content' => true,
            'settings' => true,
            'contact' => true,
        ],

        /*
         * Classes implementing
         * UClemmer\LaravelCore\Support\Contracts\ProvidesAdminWidgets.
         * Add host application widget providers here; the package registers
         * its own.
         */
        'widget_providers' => [
            //
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Diagnostics (php artisan core:doctor)
    |--------------------------------------------------------------------------
    |
    | `core:doctor` checks for settings that are individually reasonable and
    | jointly wrong — a contact form creating accounts nothing can claim, a
    | panel nobody holds the permission for, a queue worker still running a
    | flag you switched off days ago.
    |
    | It is read-only and safe to run in production. Add your own checks with
    | classes implementing
    | UClemmer\LaravelCore\Support\Contracts\ProvidesDoctorChecks; other
    | packages add theirs the same way.
    |
    */
    'doctor' => [
        'check_providers' => [
            //
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Additional permission providers
    |--------------------------------------------------------------------------
    |
    | Classes implementing UClemmer\LaravelCore\Support\Contracts\ProvidesPermissions.
    | The package registers its own; add host application ones here and they are
    | picked up by `php artisan core:sync-permissions`.
    |
    */
    'permission_providers' => [
        Permissions::class,
    ],

];
