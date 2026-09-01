<?php

use App\Livewire\Auth\Register;
use App\Livewire\ContactForm;
use App\Livewire\EventInterest;
use App\Livewire\Portal\CreateRegistration;
use App\Livewire\Portal\Grants as PortalGrants;
use App\Livewire\Portal\OrganizationProfile;
use App\Livewire\Portal\Profile as PortalProfile;
use App\Livewire\Staff\Events\Edit as EditFair;
use App\Livewire\Staff\Faq\Edit as EditFaq;
use App\Livewire\Staff\Grants\Show as ShowGrant;
use App\Livewire\Staff\Messages\Edit as EditCampaign;
use App\Livewire\Staff\Organizations\Edit as EditOrganization;
use App\Livewire\Staff\Organizations\Index as OrganizationIndex;
use App\Livewire\Staff\Organizations\Show as ShowOrganization;
use App\Livewire\Staff\Registrations\Create as CreateStaffRegistration;
use App\Livewire\Staff\Registrations\Index as StaffRegistrationIndex;
use App\Livewire\Staff\Registrations\Show as ShowStaffRegistration;
use App\Livewire\Staff\Sponsors\Edit as EditSponsor;
use App\Models\Event as Fair;
use App\Models\Grant;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\Sponsor;
use App\Models\User;

/*
 * Submit every form empty and check each message names its own input.
 *
 * Three consecutive hand-passes at validationAttributes() each found the last
 * one's leftovers (docs/13): the `_id` fields, then `rep_*`, then the campaign
 * form's channels and bodies. Reasoning about which fields "look wrong" is what
 * kept missing them; submitting a form and reading what comes back does not.
 *
 * The pairing is structural rather than guessed. uclemmer/laravel-ui derives a
 * field `id` from `name`, points `label[for]` at it, and renders the message as
 * `#{id}-error` — so every labelled error on a page can be matched to the label
 * above it without this test knowing anything about the form it is looking at.
 *
 * A form whose method throws or no-ops contributes no pairs rather than
 * failing. That is deliberate — half these methods are guarded — and it is why
 * the coverage test at the bottom exists: without it, a form that silently
 * stopped producing errors would look exactly like a form that passed.
 */

/**
 * Every label/error pair the rendered page actually shows.
 *
 * @return array<string, array{label: string, error: string}>
 */
function labelledErrors(string $html): array
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);

    $pairs = [];
    $nodes = $xpath->query('//*[@id]');

    if ($nodes === false) {
        return [];
    }

    foreach ($nodes as $node) {
        /** @var DOMElement $node */
        $id = $node->getAttribute('id');

        if (! str_ends_with($id, '-error')) {
            continue;
        }

        $field = substr($id, 0, -strlen('-error'));
        $labels = $xpath->query(sprintf('//label[@for=%s]', json_encode($field)));

        if ($labels === false || $labels->length === 0) {
            continue;
        }

        $pairs[$field] = [
            // A trailing "*" is the required marker, not part of the name.
            'label' => trim((string) preg_replace('/\s+/u', ' ', (string) $labels->item(0)?->textContent), " *\u{00a0}"),
            'error' => trim((string) preg_replace('/\s+/u', ' ', $node->textContent)),
        ];
    }

    return $pairs;
}

/**
 * Fields whose message deliberately does not repeat their label, and why.
 *
 * @return array<string, string>
 */
function deliberatelyUnlabelled(): array
{
    /*
     * Each of these is a label that is not a noun — a heading, an instruction,
     * a question, or a possessive. Reusing it would produce "the your name
     * field is required". The rule is that the MESSAGE has to be readable and
     * point at the right input; matching the label is just the usual way of
     * getting there. Listing the exceptions here keeps them decisions rather
     * than oversights, and keeps the sweep strict about everything else.
     */
    return [
        // Heading above a checkbox list. "The send by field" is not English.
        'channels' => 'delivery method',

        // Labelled "Your name" on signup and on the portal profile.
        'name' => 'name',

        // Labelled with the question "Why are you requesting fee assistance?".
        'justification' => 'justification',

        // EventInterest writes its own message — "We need an email address to
        // tell you." — which is better copy than any attribute name would give.
        'interest-email' => 'email address',

        // The fair's datetimes are labelled with clauses: "Fair opens",
        // "Fair closes", "Registration opens". Each takes the noun its label
        // implies, because "the fair opens field" is not English.
        'starts_at' => 'opening time',
        'ends_at' => 'closing time',
        'reception_starts_at' => 'reception start time',
        'registration_opens_at' => 'registration opening time',
        'registration_closes_at' => 'registration closing time',

        // The merge dialog's select is labelled with an instruction,
        // "Keep this organization".
        'keepId' => 'organization to keep',
    ];
}

/**
 * Does this message name exactly `$expected`, rather than merely containing it?
 *
 * Laravel puts the attribute immediately before "field" in most messages, so
 * that shape can be compared exactly — which is the difference between
 * accepting "the venue field" and accepting "the venue NAME field" under a
 * label reading "Venue". A substring test passes both, and passed the second
 * one for a while.
 *
 * Messages that do not take that shape — `exists` renders "The selected X is
 * invalid", and a hand-written message renders whatever it likes — fall back to
 * containment, because there is no attribute to isolate in them.
 */
function messageNames(string $error, string $expected): bool
{
    $expected = mb_strtolower($expected);

    if (preg_match('/^The (.+?) field /ui', $error, $matches) === 1) {
        return mb_strtolower($matches[1]) === $expected;
    }

    return str_contains(mb_strtolower($error), $expected);
}

beforeEach(function () {
    $this->coordinator = coordinator();
    $this->fair = Fair::factory()->published()->create();
    $this->organization = Organization::factory()->named('Kenyon College')->create();
    $this->rep = User::factory()->rep($this->organization)->create();
});

dataset('every form in the app', [
    'signup' => ['register', fn () => livewire(Register::class)],
    'contact' => ['submit', fn () => livewire(ContactForm::class)],
    'event interest' => ['submit', fn () => livewire(EventInterest::class, ['event' => test()->fair])],

    'portal: register for a fair' => ['submit', function () {
        test()->actingAs(test()->rep);

        // mount() fills these from the signed-in rep, so they must be cleared
        // or the required rule never fires and the form proves nothing.
        return livewire(CreateRegistration::class)->set('rep_name', '')->set('rep_email', '');
    }],
    'portal: apply for a grant' => ['apply', function () {
        test()->actingAs(test()->rep);

        return livewire(PortalGrants::class);
    }],
    'portal: organization profile' => ['save', function () {
        test()->actingAs(test()->rep);

        return livewire(OrganizationProfile::class)->set('name', '');
    }],
    'portal: your details' => ['save', function () {
        test()->actingAs(test()->rep);

        return livewire(PortalProfile::class)->set('name', '')->set('email', '');
    }],

    'staff: new fair' => ['save', function () {
        test()->actingAs(test()->coordinator);

        return livewire(EditFair::class);
    }],
    'staff: new FAQ item' => ['save', function () {
        test()->actingAs(test()->coordinator);

        return livewire(EditFaq::class);
    }],
    'staff: new campaign' => ['save', function () {
        test()->actingAs(test()->coordinator);

        return livewire(EditCampaign::class)->set('channels', []);
    }],
    'staff: campaign with both channels' => ['save', function () {
        test()->actingAs(test()->coordinator);

        return livewire(EditCampaign::class)->set('channels', ['email', 'sms']);
    }],
    'staff: new organization' => ['save', function () {
        test()->actingAs(test()->coordinator);

        return livewire(EditOrganization::class);
    }],
    'staff: manual registration' => ['save', function () {
        test()->actingAs(test()->coordinator);

        return livewire(CreateStaffRegistration::class);
    }],
    'staff: new sponsor' => ['save', function () {
        test()->actingAs(test()->coordinator);

        return livewire(EditSponsor::class);
    }],
    'staff: sponsor staff row' => ['saveStaff', function () {
        test()->actingAs(test()->coordinator);

        return livewire(EditSponsor::class, ['sponsor' => Sponsor::factory()->create()]);
    }],

    'staff: merge organizations' => ['merge', function () {
        test()->actingAs(test()->coordinator);

        return livewire(OrganizationIndex::class)->call('startMerge', test()->organization->id);
    }],
    'staff: deny a claim' => ['denyClaim', function () {
        test()->actingAs(test()->coordinator);

        return livewire(ShowOrganization::class, ['organization' => test()->organization]);
    }],
    'staff: deny a grant' => ['deny', function () {
        test()->actingAs(test()->coordinator);

        $grant = Grant::factory()->for(test()->fair)->for(test()->organization)->create();

        return livewire(ShowGrant::class, ['grant' => $grant]);
    }],
    'staff: cancel a registration' => ['cancel', function () {
        test()->actingAs(test()->coordinator);

        return livewire(StaffRegistrationIndex::class);
    }],
    'staff: registration details' => ['saveDetails', function () {
        test()->actingAs(test()->coordinator);

        $registration = Registration::factory()
            ->forEvent(test()->fair)
            ->forOrganization(test()->organization)
            ->create();

        return livewire(ShowStaffRegistration::class, ['registration' => $registration])
            ->set('rep_name', '')
            ->set('rep_email', '');
    }],
]);

it('names every field after the input it sits under', function (string $method, Closure $make) {
    $component = $make();

    try {
        $component->call($method);
    } catch (Throwable) {
        // A guard fired before validation. Nothing to compare, and not a
        // failure of this test — the coverage test below is what notices.
    }

    $mismatches = [];

    foreach (labelledErrors($component->html()) as $field => $pair) {
        $expected = deliberatelyUnlabelled()[$field] ?? $pair['label'];

        if ($expected === '') {
            continue;
        }

        if (! messageNames($pair['error'], $expected)) {
            $mismatches[] = sprintf(
                '%s: labelled "%s" but says "%s"',
                $field,
                $pair['label'],
                $pair['error'],
            );
        }
    }

    expect($mismatches)->toBe([], "\n".implode("\n", $mismatches));
})->with('every form in the app');

/**
 * Actions that return before validating, so they contribute nothing to check.
 *
 * All three are modal actions that need a target picked first — `denyClaim`
 * a representative, `deny` a decision, `cancel` a registration. Called cold
 * they hit their guard and never reach the validator, which is correct
 * behaviour and not something to work around.
 *
 * @return array<int, string>
 */
function guardedBeforeValidating(): array
{
    return ['denyClaim', 'deny', 'cancel'];
}

it('produced something to check on every form that validates', function (string $method, Closure $make) {
    /*
     * The sweep above passes trivially for a form that renders no errors at
     * all, so a component that quietly stopped validating — a guard added in
     * the wrong place, a renamed method, a rule dropped — would read as green.
     * This is the half that notices.
     */
    $component = $make();

    try {
        $component->call($method);
    } catch (Throwable) {
    }

    $pairs = labelledErrors($component->html());

    if (in_array($method, guardedBeforeValidating(), true)) {
        expect($pairs)->toBe([], sprintf(
            '%s now reaches the validator; move it out of guardedBeforeValidating() so the sweep checks it.',
            $method,
        ));

        return;
    }

    expect($pairs)->not->toBeEmpty(sprintf(
        '%s produced no labelled errors, so the sweep checked nothing on it.',
        $method,
    ));
})->with('every form in the app');
