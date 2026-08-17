# 01 — Project Overview & Requirements

> **Purpose of this document:** The what and why of the rebuild. Read [00-current-site-review.md](00-current-site-review.md)
> first for context on the existing site. Implementation details live in 02 (architecture), 03 (data model),
> 04 (integrations), 05 (roadmap/tasks), and 06 (testing).

## Vision

Replace the third-party-hosted site with a Laravel application the fair owns and controls, where:

- **College reps** register and pay for the fair online, manage their registration from an account, and get timely logistics communication.
- **The organizer** runs the entire fair from an admin panel — events, pricing, registrations, check reconciliation, rosters, content, and communications — without touching code.
- **Students & parents** get clear, current information about the next fair.

## Confirmed decisions

These were decided with the project owner on 2026-08-15. Future sessions should treat them as settled unless the owner says otherwise.

| # | Decision | Choice |
|---|---|---|
| D1 | Payments | **Stripe (card) + pay-by-check.** Check registrations are held as `pending_payment`; admin marks checks received. |
| D2 | Admin scope | **Full admin panel** — events, pricing, registrations, payments, sponsors, FAQs, pages, and communications are all admin-manageable. |
| D3 | Rep identity | **Accounts with login.** Reps register an account, can view/edit registrations, download receipts, and re-register in later years with prefilled info. |
| D4 | SMS (Twilio) | **Rep event reminders** (logistics before the fair) and **admin alerts** (new registration/payment). No SMS registration confirmations — email covers that. |
| D5 | Stack | Laravel 13, Pest for tests, **Filament v5** for all UI (admin panel, rep portal, and public pages), Postmark (email), Twilio (SMS), Stripe (payments). *(Owner corrected 2026-08-16: earlier "Fluent UI" references meant Filament; no hand-built Blade/Tailwind/Livewire/Flowbite UI.)* |
| D6 | Foundation package | Build on the owner's **`uclemmer/laravel-core`** package (2026-08-16): its Filament panel shell + roles/permissions replace a hand-rolled admin gate; its contact, content-block, and email-logging modules replace the equivalents we'd have built. Requires **PHP 8.4**. See docs 02 and 07. |
| D7 | Email system | One **themed HTML email template** for all mail; coordinator can email **cross-year audiences** (this year's reps, last year's, lapsed, any-previous, interest list); **send tracking via laravel-core's EmailLog** module (not open/click tracking). Full design in [07-email-design.md](07-email-design.md). |
| D8 | Organizations | Reps belong to an **organization** (the college/university), which carries its own profile: website, admissions office, admissions email/phone, logo, address. **One org per rep**; an org has many reps over time. Reps can **retire/close out** (deactivated, kept for history). *(Owner, 2026-08-16.)* |
| D9 | Joining an org | **Claim + coordinator approval:** a new rep picks their org from the directory (pending until the coordinator approves) or creates a new org (active immediately, coordinator alerted). No self-serve invites in v1. |
| D10 | Grants | Organizations can **apply for a grant per event** (free or discounted registration). On approval the **coordinator sets the benefit per grant**: free, a custom price, or a percent off. Approved grants apply automatically at registration; free registrations skip payment entirely. |

## Actors

1. **Visitor** — student, parent, or counselor browsing public pages. No account.
2. **Organization** — the college/university being represented. Owns a profile (website, admissions office, admissions email/phone, logo, address), registration history, and grant applications. Persists across rep turnover (D8).
3. **College rep** — authenticated user belonging to exactly one organization (membership: pending → active → retired). Registers and pays for fairs on the organization's behalf.
4. **Admin/organizer** — the fair coordinator (and sponsor-school counselors as needed); full admin access.

## Functional requirements

### R1 — Public site

- R1.1 Home page: next fair's date/venue/time, register CTA, sponsor logos.
- R1.2 About page.
- R1.3 Representatives page: live roster of organizations registered (and paid or check-pending → admin decides what displays; default = confirmed only) for the **current** fair. **Shows the organization's logo** when one is set (owner, 2026-08-16), with a neutral initial-letter placeholder otherwise so the grid stays even.
- R1.4 Last Year page: roster of the previous fair, generated from that event's data (fixes the staleness bug in the current site).
- R1.5 Sponsors page: sponsor schools + counseling staff, admin-editable.
- R1.6 FAQ page: admin-editable Q&A, Google Maps embed, downloadable W-9.
- R1.7 Contact page: form (first/last name, organization, email, phone, message, privacy consent) → emails the organizer via Postmark, stores the submission, honeypot + rate limiting for spam. **Implemented with laravel-core's contact module** (`core.contact.*`), which provides the component, storage, receipt, admin alert, honeypot, and throttle (D6).
- R1.8 Event pages at `/events/{slug}` showing date, schedule, venue, price, and registration status (open / closed / not yet open), with open/close windows controlled by the admin.

### R2 — Organizations, rep accounts & registration

- R2.1 Account registration + email verification, login, logout, password reset (all via Postmark).
- R2.2 On signup a rep either **creates a new organization** (profile: name, website, admissions office, admissions email/phone, address, optional logo → membership active immediately, coordinator alerted, normalized-name dedupe warning) or **claims an existing one** from the directory → membership `pending` until the coordinator approves (D9). Pending reps can browse but not register or apply for grants.
- R2.3 Registration flow for an open event (active reps only): confirm/update org profile → rep contact details + optional SMS opt-in → grant status shown if any → choose payment method. Price = `Event::priceFor(organization)` (grant-aware, D10), computed server-side.
- R2.4 **Stripe path:** Stripe Checkout session at the grant-aware price; on webhook confirmation the registration becomes `confirmed`, a receipt email goes out, the organization appears on the roster. **Free (100% grant) registrations skip payment entirely** and confirm immediately.
- R2.5 **Check path:** registration saved as `pending_payment`; confirmation email includes a printable/PDF registration form (grant-aware amount) with mailing instructions; admin marks the check received → `confirmed` + receipt email.
- R2.6 Rep dashboard: the organization's current and past registrations (any rep's), statuses, receipts, edit org profile + own contact info, cancel (per admin-set policy).
- R2.7 Duplicate protection: one active registration per organization per event (hard check) + normalized-name warning when creating a lookalike org.
- R2.8 When registration is closed, event page offers a "notify me when registration opens" interest form (fixes the dead-end gap).
- R2.9 **Grant application** (D10): an active rep applies per event with a short justification; sees status (pending/approved/denied) and the approved benefit; coordinator alerted on application, rep emailed on decision. Form and copy: **Appendix A** below (owner approved keeping it simple, 2026-08-16).
- R2.10 **Rep lifecycle:** a rep can retire/close out their own membership; the coordinator can retire any rep. Retired reps keep history but lose portal org rights and drop out of campaign audiences (doc 07).

### R3 — Admin panel

- R3.1 Admin auth via laravel-core roles/permissions (D6): coordinator role with app permissions; core's panel access check gates `/admin`.
- R3.2 Event CRUD: name, slug, date, times, venue, price (cents), registration open/close datetimes, capacity (optional), published flag.
- R3.3 Registrations: list/filter/search per event, view detail, mark check received, refund (Stripe refund via API or manual note for checks), cancel, resend confirmation, export CSV.
- R3.3a Organizations: directory CRUD (profile incl. logo), merge-duplicates action, rep list per org with membership status; approve/deny pending rep claims; retire reps.
- R3.3b Grants: review queue per event; approve (choosing benefit: free / custom price / percent off), deny with reason; see grant usage on registrations; revoke before use.
- R3.4 Roster management: control which registrations display publicly; add manual entries if needed.
- R3.5 Content: sponsors (school, staff list, URL, logo), FAQ items (question, answer, order), page copy blocks for Home/About.
- R3.6 Communications: compose an email (Postmark) and/or SMS (Twilio, to opted-in reps) to a chosen **audience**, rendered in the themed template, sent now or scheduled. Audiences span years (D7): this year's confirmed / pending-check / all, last year's reps, last-year-but-not-this-year (lapsed), any-previous-year reps, any-previous-not-this-year, and the interest list — with preview counts before sending. Full definitions in [07-email-design.md](07-email-design.md).
- R3.6a Delivery tracking: every send is captured by laravel-core's EmailLog; campaign recipients link to their log rows so the coordinator sees per-recipient sent/failed status and can resend (D7).
- R3.7 Admin alerts: email + SMS to the organizer on new registration and on payment received (configurable toggle).
- R3.8 Dashboard: registrations count, revenue collected vs. pending checks, recent activity for the active event.
- R3.9 Contact-form inbox: view submissions, mark handled.

### R4 — Communications summary

| Trigger | Email (Postmark) | SMS (Twilio) |
|---|---|---|
| Account verification / password reset | ✅ | — |
| Registration created (check path) | ✅ w/ printable form | — |
| Payment confirmed (Stripe or check) | ✅ receipt | — |
| New registration / payment received | ✅ admin | ✅ admin alert (D4) |
| Pre-fair logistics reminders | ✅ | ✅ opted-in reps (D4) |
| Registration-open announcement to interest list | ✅ | — |
| Coordinator campaigns to cross-year audiences (R3.6) | ✅ themed template | ✅ opted-in only, logistics scope |
| Rep claims existing org / new org created | ✅ admin | — |
| Claim approved or denied | ✅ rep | — |
| Grant application received | ✅ admin | — |
| Grant approved (with benefit) / denied | ✅ rep | — |

All email renders in the **themed HTML template** (D7); all sends are logged in `core_email_logs` (R3.6a).

## Non-functional requirements

- **N1 Money handling:** store all amounts as integer cents; never trust client-side price; price comes from `Event::priceFor(organization)` (event price minus any approved grant, D10) at checkout-session creation and is snapshotted on the registration; webhook is the source of truth for payment state; webhook signatures verified; handlers idempotent.
- **N2 Security:** CSRF everywhere, hashed passwords, rate-limited auth and contact endpoints, admin role checks in middleware *and* policies, no card data ever touches the server (Stripe Checkout hosted page).
- **N3 Privacy:** contact submissions used only to respond (site promise today); SMS strictly opt-in with STOP handling delegated to Twilio; delete/anonymize on request.
- **N4 Testing:** every model, service, job, and controller ships with Pest tests (project instruction). Details in [06-testing-strategy.md](06-testing-strategy.md).
- **N5 Documentation:** every work session updates `/docs` (project instruction).
- **N6 Accessibility & responsiveness:** WCAG AA-minded, mobile-first — parents will open this on phones.
- **N7 Ops:** queue-backed email/SMS sending; all external calls (Stripe, Postmark, Twilio) wrapped in services that are fake-able in tests and log failures.

## Appendix A — Grant application form & copy (v1)

Owner directive 2026-08-16: keep it simple. This is the approved v1 copy — implementers use it verbatim
(card 3.5); tweaks go through the owner.

**Portal page intro** (shown on the grant application page for an event):

> **Fee assistance grants**
> If the registration fee is a barrier for your institution, you can request a reduced or waived fee for
> this year's fair. Requests are reviewed by the fair coordinator, and you'll hear back by email —
> usually within a week. Applying doesn't register you for the fair; once your request is decided,
> register as usual and any approved discount is applied automatically.

**Form fields** (one screen, no wizard):

| Field | Type | Notes |
|---|---|---|
| Organization / event | display only | from the rep's org + the chosen event |
| Why are you requesting fee assistance? | textarea, required, max 1,000 chars | helper text: "A couple of sentences is plenty — e.g., budget constraints, first time attending, non-profit or community program." |
| Submit button | "Submit request" | confirmation toast: "Request submitted — we'll email you when it's been reviewed." |

**Status copy** (portal):

- Pending: "Your request is being reviewed. We'll email you as soon as there's a decision."
- Approved: "Good news — your registration fee for {event} is {waived | ${price} | {percent}% off}. The discount is applied automatically when you register."
- Denied: "We weren't able to approve fee assistance this year. {reason}" + note that standard registration remains open.

**Decision emails** (themed template, doc 07): mirror the approved/denied copy above, from the coordinator,
with a "Register now" button on approvals. Admin alert on new applications: org name, event, justification,
link to the review queue.

## Out of scope (for v1)

Student/parent registration or ticketing, multi-fair/multi-city support beyond the event model, online W-9 workflows beyond a downloadable file, sponsor self-service, and payment plans. The data model doesn't preclude any of these.

## Open questions for the owner

- Exact 2027 fair date/price (seed with placeholders).
- Refund/cancellation policy text.
- Should check-pending organizations appear on the public roster? (Default: no — confirmed only.)
- Whether grant totals need reporting for the sponsors. *(Application form/wording: settled — Appendix A. Roster logos: settled — yes, R1.3.)*
- Domain/DNS + Postmark sender domain verification timing.
- **Historical roster export** (2025/2026 institutions + rep contact emails) from the current site/ISPEUS, so cross-year audiences work from day one (see doc 07 §2).
- Does the fair need the counselor-reception RSVP handled on-site too? (Not in current site; out of scope unless requested.)
