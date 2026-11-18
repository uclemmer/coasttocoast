# 31 — Terms, Privacy, and the error pages the handoff did not draw (2026-11-17)

**What the owner asked for:** custom error pages, a "down for maintenance"
page, and a Terms of Service and Privacy Policy, with each legal page carrying
summaries in simple language.

**Decided with the owner the same day:**
- Use `uclemmer/laravel-legal` rather than plain pages.
- Fill the error-page gaps rather than redesign the approved pages.
- Leave the legal operator as a marked placeholder.

---

## 1. Error and maintenance pages: the gaps

The design was already done. Doc 16 §2 built 404, 403, 500 and the 503
maintenance page from the handoff, over one standalone shell
(`<x-errors.page>`), with copy the handoff marks final. They were not
redesigned.

**What was missing** was every other status, which Laravel served as its
stock grey page. Five views were added over the same shell:

| View | When a visitor sees it | Buttons |
| --- | --- | --- |
| `401` | Not signed in where an account is needed | Log in · Home |
| `419` | A form left open past the session (the CSRF token expired), most likely mid-registration | Go back · Home |
| `429` | The throttled forms (contact, interest, login) resubmitted too fast | Home |
| `4xx` | Any other client error, with the real code | Home |
| `5xx` | 502, 504 and the rest (500 and 503 have their own) | Home · Contact us |

**The handoff never drew these, so their copy was written here** in its voice
(a script line, an uppercase heading, one sentence). Each file says so. They
inherit the shell's rule from doc 16: no `@vite`, no `route()`, no database,
because an error page renders while something is already broken.

`ErrorPagesTest` drives each through the real exception handler with debug
off. The 419 test throws the `TokenMismatchException` the CSRF check throws,
because the check itself is skipped under test. The fallbacks are wired by
filename alone, so removing `4xx.blade.php` was checked to fail its test.

## 2. Terms of Service and Privacy Policy

**On `uclemmer/laravel-legal` `^0.7`**, the line that works with this app's
core `^0.8`. Golden rule 2 says a module a package provides is not rebuilt
here. What the package gives:
- documents drafted and published in `/admin` → Legal;
- published versions that are **immutable and dated**, with a public version
  history;
- placeholders such as `{{ company.email }}`, frozen into each version when it
  is published;
- an acceptance log and a re-consent screen. Both are wired but **off**: the
  site asks nobody to agree to anything yet.

**Wiring:**
- the `vcs` repository and `require`;
- the package's config and three migrations, published;
- `LegalScreens` in `core.admin.plugins`;
- `legal.manage` granted to the coordinator, because `RoleSeeder` grants every
  synced permission.

**`config/legal.php`:**
- `enabled` and `routes.enabled` are on.
- The `company` variables read `fair.contact`, so the policies and the footer
  cannot disagree.
- `legal_name` and `jurisdiction` stay null; they are the owner's.
- `starters` points at this fair's drafts.

**The live site's URLs are kept.** The package's default slugs are `privacy`
and `terms`; `LegalDocumentSeeder` sets `privacy-policy` and
`terms-of-service`. Those are the URLs the old site's contact form linked,
and the pages there were empty.

### The drafts

`resources/stubs/legal/privacy.md` and `terms.md` are written from what this
app actually does, read from its schema and config. They are not a template.
The structure is:
- **"The short version"** at the top;
- **"In plain words"** opening every section. This is the plain-language
  summary the owner asked for, and `LegalPagesTest` fails if a section lacks
  one.

On the page, each summary is a Markdown blockquote, styled as a green box
beside the full text.

**Facts the Privacy Policy relies on**, each checked against the code:
- what an account, a registration, a fee-assistance application and the
  contact form store;
- card numbers never reach the app (Stripe Checkout);
- no analytics or advertising cookies, and self-hosted fonts;
- the Google Maps embed loads with the home page, so Google sees the visit.
  That followed from doc 29 §5;
- text messages only to reps who opted in;
- the public rosters show college names and logos, never a rep's name;
- the retention periods: contact form 365 days, email log 400 days, campaign
  recipients 24 months, Stripe events 90 days, sessions 120 minutes;
- the service providers: Stripe, Postmark, Twilio, and the host.

Two claims were struck when checked. Two-factor sign-in is **off** in this
app, and the log is not rotated, so neither "two-factor is available" nor
"short-lived logs" is said.

**What is deliberately left open, as `TODO`s**, which the package's publish
screen warns about:
- **Both documents:** who the legal operator is.
- **Privacy:** which legal bases apply if visitors come from outside the US;
  the unsubscribe route for invitation emails; the hosting provider; how long
  accounts and registrations are kept; and whether any state privacy law
  applies.
- **Terms:** the cancellation and refund policy, still open since doc 01; the
  liability and "as is" section, which needs a lawyer; and the governing law.

### Nothing is published

`LegalDocumentSeeder`:
- runs in both `ProductionSeeder` and `DatabaseSeeder`;
- creates the two documents and a **draft** of each;
- leaves a document that already has any version alone (the package's
  `draft()` would overwrite one someone is writing);
- changes a slug only while it is still the package default;
- never publishes.

Publishing is permanent, so it is the owner's act after review. Until then:
- `/legal/privacy-policy` and `/legal/terms-of-service` return 404;
- the footer shows no links (`App\Support\LegalLinks` lists only documents
  with a version in force, and never writes, since it runs on every page);
- `core:doctor` warns "Nothing is published for: Privacy Policy, Terms of
  Service". That is a warning, so the doctor still exits 0.

**To publish:**
1. In `/admin` → Legal, open a document.
2. Resolve every TODO and delete the review notice.
3. Publish.

The footer links appear by themselves.

### The public pages

The package's views are bare HTML for a host to wrap. This app overrides the
public ones in `resources/views/vendor/legal`:
- the layout component, which the consent screen uses;
- `show`, `history` and `index`, in the site layout with the page header and
  breadcrumbs.

The body's own H1 is hidden, because the header carries the title. The admin
views are not overridden, so they keep tracking the package.

`LegalPagesTest` covers:
- the drafts, the slugs, the summaries and the review markers;
- no page and no footer link while unpublished;
- the seeder sparing an edited draft and a chosen slug;
- a published version rendering in the layout with the summary boxes and the
  frozen contact email;
- the history and index pages, and the footer links;
- the admin editor for the coordinator, and a redirect for a signed-out
  visitor;
- the summary box's classes in the built stylesheet.

Two existing tests changed with the behaviour:
- `CoreIntegrationTest` now expects two admin contributors, the message log
  and the legal editor.
- `PublishedConfigTest`'s key check now counts a filled-in section (this
  app's `starters`) as carrying the empty one the package ships.

The go-live checklist in doc 11 gained two lines: publish both documents
after review, and upload the W-9.
