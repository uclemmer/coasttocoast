<?php

use Database\Seeders\LegalDocumentSeeder;
use UClemmer\LaravelLegal\Documents;
use UClemmer\LaravelLegal\LegalDocument;
use UClemmer\LaravelLegal\LegalService;

/*
 * The Terms of Service and Privacy Policy, on uclemmer/laravel-legal (docs/31).
 *
 * The seeder writes DRAFTS from this fair's own starters and never publishes:
 * a published version is permanent, and the drafts carry TODOs and a review
 * notice on purpose. These tests pin that, the plain-language summary the
 * owner asked for on every section, the live site's URLs, and what a published
 * version looks like in the site's layout.
 */

beforeEach(fn () => $this->seed(LegalDocumentSeeder::class));

function legalDraftBody(string $key): string
{
    return (string) LegalDocument::query()->where('key', $key)->firstOrFail()->draft()?->body;
}

describe('the drafts', function () {
    it('creates both documents at the live site\'s URLs, as drafts', function () {
        $documents = LegalDocument::query()->pluck('slug', 'key');

        expect($documents[Documents::PRIVACY])->toBe('privacy-policy')
            ->and($documents[Documents::TERMS])->toBe('terms-of-service')
            ->and(app(LegalService::class)->current(Documents::PRIVACY))->toBeNull()
            ->and(app(LegalService::class)->current(Documents::TERMS))->toBeNull();
    });

    it('gives every section of both documents a plain-language summary', function (string $key) {
        $body = legalDraftBody($key);

        preg_match_all('/^## /m', $body, $sections);
        preg_match_all('/^## .+\n\n> \*\*In plain words:\*\*/m', $body, $summarised);

        expect(count($sections[0]))->toBeGreaterThan(8)
            ->and(count($summarised[0]))->toBe(count($sections[0]))
            // And a summary of the whole document up front.
            ->and($body)->toContain('> **The short version.**');
    })->with([Documents::PRIVACY, Documents::TERMS]);

    it('cannot be mistaken for a finished policy', function (string $key) {
        // The package warns at publish while these remain. They are deliberate:
        // the operator, the governing law, refunds and liability are the
        // owner's and a lawyer's to settle.
        expect(app(LegalService::class)->unreviewedMarkers(legalDraftBody($key)))->toHaveCount(2);
    })->with([Documents::PRIVACY, Documents::TERMS]);

    it('serves no public page and no footer link while unpublished', function () {
        $this->get('/legal/privacy-policy')->assertNotFound();
        $this->get('/legal/terms-of-service')->assertNotFound();

        $this->get('/')->assertOk()
            ->assertDontSee('/legal/privacy-policy', escape: false)
            ->assertDontSee('/legal/terms-of-service', escape: false);
    });

    it('never overwrites a draft someone is writing, or a slug someone chose', function () {
        $legal = app(LegalService::class);
        $legal->draft(Documents::PRIVACY, 'Our own words.');
        LegalDocument::query()->where('key', Documents::TERMS)->update(['slug' => 'conditions']);

        $this->seed(LegalDocumentSeeder::class);

        expect(legalDraftBody(Documents::PRIVACY))->toBe('Our own words.')
            ->and(LegalDocument::query()->where('key', Documents::TERMS)->value('slug'))->toBe('conditions');
    });
});

describe('once published', function () {
    beforeEach(function () {
        $legal = app(LegalService::class);

        foreach ([Documents::PRIVACY, Documents::TERMS] as $key) {
            $legal->publish(LegalDocument::query()->where('key', $key)->firstOrFail()->draft(), now()->subMinute());
        }
    });

    it('renders in the site\'s layout, with the summaries as highlighted boxes', function () {
        $this->get('/legal/privacy-policy')->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('Version 1')
            ->assertSee('legal-body', escape: false)
            ->assertSee('<blockquote>', escape: false)
            ->assertSee('In plain words:')
            // The fair's contact details, frozen into the version at publish.
            ->assertSee(config('fair.contact.email'))
            // The site's own header and footer.
            ->assertSee(config('app.name'));
    });

    it('serves the terms, the history and the index', function () {
        $this->get('/legal/terms-of-service')->assertOk()->assertSee('Terms of Service');
        $this->get('/legal/terms-of-service/history')->assertOk()->assertSee('Version history');
        $this->get('/legal')->assertOk()->assertSee('Privacy Policy')->assertSee('Terms of Service');
    });

    it('links both from the footer', function () {
        $this->get('/')->assertOk()
            ->assertSee(route('legal.show', ['slug' => 'terms-of-service']), escape: false)
            ->assertSee(route('legal.show', ['slug' => 'privacy-policy']), escape: false);
    });
});

it('gives the coordinator the legal editor in /admin, and nobody else', function () {
    // Registration is not rendering: mount the screen, and open a draft.
    $this->actingAs(coordinator())
        ->get(route('core.admin.legal.index'))
        ->assertOk()
        ->assertSee('Privacy Policy');

    $document = LegalDocument::query()->where('key', Documents::PRIVACY)->firstOrFail();

    $this->get(route('core.admin.legal.edit', $document))->assertOk();

    auth()->logout();

    $this->get(route('core.admin.legal.index'))->assertRedirect();
});

it('compiles the summary box\'s classes into the built stylesheet', function () {
    $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true);
    $css = (string) file_get_contents(public_path('build/'.$manifest['resources/css/app.css']['file']));

    expect($css)->toContain('.\[\&_blockquote\]\:border-l-4')
        ->toContain('.\[\&_blockquote\]\:bg-brand-50')
        ->toContain('.\[\&_h1\]\:hidden');
});
