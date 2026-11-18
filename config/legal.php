<?php

/*
|--------------------------------------------------------------------------
| Legal documents (docs/legal.md)
|--------------------------------------------------------------------------
|
| Versioned, publishable legal pages — privacy policy, terms of service, and
| whatever other packages declare (a store package might add a refund policy).
| Published versions are IMMUTABLE and dated, so you can point at exactly what
| was in force on a given day and who agreed to it.
|
| The package ships STARTER SKELETONS for privacy and terms — real section
| headings, placeholders, and a `TODO` everywhere a decision is yours rather
| than ours. They are a structure to work through, not policies: each opens
| with a notice saying so, and the admin warns if that notice is still there
| when you publish. Have a lawyer read what you end up with.
|
| The user model, RBAC, admin panel and diagnostics come from
| uclemmer/laravel-core; see that package's config for `core.auth.user_model`
| and friends. Everything here is off by default — installing the package
| changes nothing until `enabled` is true.
|
*/

return [
    // On (docs/31): the Terms of Service and Privacy Policy, drafted in /admin
    // and published as dated, immutable versions.
    'enabled' => true,

    'routes' => [
        // /legal/privacy-policy and /legal/terms-of-service, the live site's
        // URLs. The slugs are set on the document rows by LegalDocumentSeeder.
        'enabled' => true,
        'prefix' => 'legal',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Re-consent
    |--------------------------------------------------------------------------
    |
    | When a document is republished, ask the people who agreed to the previous
    | version to agree again. Switching this on registers the consent screen;
    | it does NOT start blocking anybody until you attach the middleware:
    |
    |     // bootstrap/app.php
    |     ->withMiddleware(function (Middleware $middleware) {
    |         $middleware->web(append: [
    |             UClemmer\LaravelLegal\Middleware\RequireLegalAcceptance::class,
    |         ]);
    |     })
    |
    | or per route group with the `legal` alias. `php artisan core:doctor`
    | reports the half-finished state where this is enabled and the middleware
    | is attached nowhere.
    |
    | Only people who accepted an EARLIER version are stopped. Somebody who
    | never accepted anything has nothing to re-consent to, and catching them
    | here would turn this into a retroactive signup gate.
    |
    */
    'reconsent' => [
        'enabled' => false,

        // The consent screen lives here. Registered whenever re-consent is on,
        // independently of `routes.enabled` above — blocking people with
        // nowhere to consent would lock them out.
        'prefix' => 'legal',
        'middleware' => ['web'],

        /*
         * Paths or route names that stay reachable while somebody is blocked.
         * The consent screen and logout are ALWAYS exempt and do not need
         * listing — a person who cannot reach either has been locked out of
         * their own account.
         *
         * Add anything else that must keep working: a support page, a health
         * check, an account-deletion route.
         */
        'except' => [
            //
        ],
    ],

    /*
     * Values substituted into `{{ placeholders }}` in your documents.
     *
     * Whatever shape you like — dot notation reaches into it, so
     * `{{ company.address.city }}` works if you nest it that way. These are
     * SNAPSHOTTED into each version at publish: change your address and
     * previously published versions keep showing the old one, because that is
     * what people agreed to. Publishing a new version picks up the new values.
     */
    'variables' => [
        /*
         * Nulls here are not "unset" — they fall back to `app.name` and
         * `app.url` when the version is published. No `env()` calls: this file
         * is published into a host application that may cache its config, at
         * which point every env() in it silently returns null.
         */
        /*
         * The fair's public contact block, read from config/fair.php (loaded
         * before this file, alphabetically), so the policies and the footer
         * cannot disagree. Frozen into each version when it is published.
         * `legal_name` and `jurisdiction` stay null: who the legal operator is
         * and which law governs are the owner's to settle (docs/31), and the
         * drafts carry a TODO there instead.
         */
        'company' => [
            'name' => 'Coast to Coast College Fair',
            'legal_name' => null,
            'address' => trim(implode(', ', array_filter([
                config('fair.contact.address_line1'),
                config('fair.contact.address_line2'),
                config('fair.contact.city'),
                trim(config('fair.contact.state').' '.config('fair.contact.postal_code')),
            ]))),
            'email' => config('fair.contact.email'),
            'phone' => config('fair.contact.phone'),
            'jurisdiction' => null,
        ],
        'site' => [
            'name' => null,             // defaults to app.name
            'url' => null,              // defaults to app.url
        ],
    ],

    /*
     * Classes implementing
     * UClemmer\LaravelLegal\Contracts\ProvidesLegalDocuments.
     * The package declares privacy and terms; add your own here, and other
     * packages add theirs the same way.
     */
    'document_providers' => [
        //
    ],

    /*
     * Starter skeletons, keyed by document key — absolute paths to markdown
     * files used to prefill a new draft.
     *
     * The package ships starters for `privacy` and `terms`; publish them with
     * `vendor:publish --tag=laravel-legal-stubs` and point at your edited
     * copies, or add entries for document types other packages declare. A path
     * that does not exist simply means no starter, so a missing file leaves an
     * empty editor rather than an error.
     */
    // This fair's own drafts (docs/31), each section with a plain-language
    // summary, in place of the package's generic skeletons.
    'starters' => [
        'privacy' => resource_path('stubs/legal/privacy.md'),
        'terms' => resource_path('stubs/legal/terms.md'),
    ],
];
