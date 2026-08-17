# 00 — Current Site Review

> **Purpose of this document:** A snapshot of the existing site at
> [coasttocoastcollegefair.com](https://www.coasttocoastcollegefair.com), reviewed **August 15, 2026**.
> This is the baseline for the rebuild. Any Claude session (or human) working on this project should read
> this first to understand what exists today and what we are replacing.

## What the site is

The Coast to Coast College Fair is an **annual college fair held in Chattanooga, TN** that brings together
100+ colleges and universities to meet local high school sophomores, juniors, and their parents. The fair is
organized by the college counseling offices of four sponsoring prep schools and managed by a fair coordinator
(currently Meg Conner, based at Baylor School).

**College representatives are the paying customers.** They register for a table at the fair in advance and pay
a registration fee ($215 for the 2026 fair). Students and parents attend free — the public-facing pages are
informational only.

## Key facts pulled from the live site

| Item | Value |
|---|---|
| Next fair | Tuesday, April 21, 2026 (already past at review time — 2027 event is next) |
| Venue | Chattanooga Convention & Trade Center, 1150 Carter Street, Chattanooga, TN 37402 |
| Schedule | Counselor reception 5:00–6:30 PM, general admission 6:30–8:00 PM |
| Registration fee (2026) | $215 per institution |
| Payment methods today | Pay online **or** print the registration form and mail a check |
| Contact | Meg Conner, 171 Baylor School Road, Chattanooga, TN 37405 · (423) 757-2845 · contact@coasttocoastcollegefair.com |
| Sponsors | Baylor School, Girls Preparatory School, McCallie School, St. Andrews-Sewanee School |
| Copyright | 2007–2026 (fair has ~20 years of history) |
| Current platform | "Powered by ISPEUS" (third-party event platform — this is what we're replacing) |

## Current site map

| Page | URL | Content |
|---|---|---|
| Home | `/` | Event pitch, next fair date, CTAs |
| About | `/about` | Fair description, audience, financial-aid workshop mention |
| Representatives | `/representatives` | Alphabetical list of registered colleges for the current fair, plus a "not on the list? register" CTA |
| Last Year | `/last-year` | Roster of the prior year's participating colleges |
| Sponsors | `/sponsors` | The four sponsor schools with their college counseling staff listed |
| FAQ | `/faq` | Date/time/venue, directions (Google Map embed), registration & payment how-to, parking, hotels, W-9 download, fair conduct guidelines |
| Contact | `/contact` | Contact form (first/last name, organization, email, phone, country, message, privacy consent checkbox) |
| Event page | `/events/college-fair-2026` | Event details + $215 price + register CTA |
| Registration | `/events/college-fair-2026/register` | Currently shows "Registration is currently closed." |

## Features the current site has (must be reproduced)

1. **Event-scoped registration** — registration lives under a per-year event (`/events/college-fair-2026/register`) and can be opened/closed. It is closed between fairs.
2. **Public roster of registered institutions** — the Representatives page is populated from registrations and doubles as social proof and a duplicate-check for reps.
3. **Prior-year roster** ("Last Year" page).
4. **Two payment paths** — online payment or mail-in check with a printable form.
5. **Contact form** with privacy consent.
6. **Static info pages** — About, FAQ (with map embed and W-9 download), Sponsors.
7. **Mailing-list touch** — FAQ says registering colleges "will be added to the mailing list" for further details.

## Weaknesses / gaps in the current site (opportunities for the rebuild)

- Registration is a dead end most of the year ("Registration is currently closed") with no waitlist or interest capture.
- No rep accounts: no way to view/edit a registration, download a receipt, or re-register next year without re-entering everything.
- No visible admin tooling for the organizer (check reconciliation, roster management, refunds appear manual).
- Pricing, deadlines, and what the fee includes are scattered or missing entirely from the event page.
- No automated reminders/logistics communication (parking, check-in, shipping materials).
- Stale content risk: "Last Year" page was showing the current (2025) roster when reviewed.

## What the rebuild is

A from-scratch **Laravel 13** application (repo already scaffolded at `C:\Users\uriah\Herd\coasttocoastcollegefair`) that reproduces all public pages, adds rep accounts, Stripe + check payments, a **Filament**-based admin panel and rep portal, Postmark transactional email, and Twilio SMS for rep event reminders and admin alerts. See [01-requirements.md](01-requirements.md) for the full requirements and the decisions already made.

## Sources

- https://www.coasttocoastcollegefair.com/about
- https://www.coasttocoastcollegefair.com/representatives
- https://www.coasttocoastcollegefair.com/sponsors
- https://www.coasttocoastcollegefair.com/faq
- https://www.coasttocoastcollegefair.com/contact
- https://www.coasttocoastcollegefair.com/last-year
- https://www.coasttocoastcollegefair.com/events/college-fair-2026
- https://www.coasttocoastcollegefair.com/events/college-fair-2026/register
