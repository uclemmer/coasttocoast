# 29 — The 2027 fair, set up as the owner asked (2026-11-10)

The owner sent a list on 2026-11-10. This doc records what each item became.
Some were code, some are data the coordinator edits in `/staff`, and some can
only be done by the owner in production. Decisions made on the owner's behalf
are marked as such.

**What the owner said:**
- The next fair is April 6, 2027.
- The fee is $275 before 12/31 and $325 after.
- The new location is The Westin (801 Pine Street), at the same times as last year.
- A booking link goes in the registration confirmation email.
- The mailing address is Megan Cooper at McCallie School, 500 Dodds Ave, 37404.
- Megan and the client get all "contact@" emails.
- A monthly email report of all registrations.
- Last year's registrations, for inviting those reps back.
- Admin access, domain ownership, and Stripe questions.
- Make the map active: "we do not need to hide it for privacy".
- Upload sponsor logos.
- Create a favicon.
- Times are Chattanooga time, EST.

---

## 1. Chattanooga time, app-wide

`config/app.php` `timezone` moved from `UTC` to `America/New_York` (owner).
`America/New_York` covers EST and EDT, and the April fair falls in daylight time.

Fair datetimes were always typed and shown as local wall-clock times, but they
were compared against UTC. So a registration window, and the new late-fee
cutoff, turned over at 7 or 8 p.m. local time instead of midnight. Now they are
typed, stored, shown and compared in one zone.

The setting is not an env value, because a host that set it differently would
silently shift every deadline. The schedule (`routes/console.php`) runs on
local time too, so the 03:xx prunes and the 08:00 report are Chattanooga times.

**Safe only because the site has not launched** (doc 11's go-live checklist is
unchecked). A live database would hold `created_at` and `paid_at` values
written in UTC, and those would now read 4–5 hours off. If the app has been
deployed with real data since, that needs a look before this ships.

## 2. Early bird pricing

The early bird price is $275 through December 31, 2026. The regular price,
$325, applies from January 1.

**Vocabulary (owner, 2026-11-11).** December 31 marks a price increase, not a
deadline. The fair's one advertised deadline is when registration closes, two
weeks before the fair (March 23, 2027). Say "early bird" and "regular price",
never "late fee". The first version of this doc and of the code said "late
fee". The columns were renamed the next day and the copy reworded, so nothing
in the app tells a rep they are late in January.

Three decisions were made on the owner's behalf (owner accepted all three):
- The cutoff is the end of December 31, Chattanooga time.
- The price locks when the registration is created, not when it is paid. A
  check registration made on December 30 owes $275 in January.
- A percent-off grant discounts whichever price is current. A custom-price
  grant stays at its figure.

**Schema.** Two nullable columns on `events`:
- `regular_price_cents`
- `regular_price_starts_at`

They were added as `late_price_*` by
`add_late_price_and_hotel_booking_url_to_events_table` and renamed by
`rename_late_price_to_regular_price_on_events_table` (2026-11-11). Early bird
pricing applies only when both are set, so every existing fair keeps one
price. `price_cents` is the early bird price, or the only price.

**Code.**
- `Event::listPriceAt($at)` returns the fee before grants.
- `Event::priceFor($organization, $at)` applies grants on top of it. It is still
  the only price source. `RegistrationService` snapshots it into
  `registrations.price_cents`, and Stripe charges the snapshot, so nothing on
  the payment path changed.
- `hasRegularPrice()`, `isEarlyBirdAt()` and `earlyBirdLastDay()` answer the
  questions the copy asks.
- `Event::feeSummary()` gives the public wording: "$275.00 early bird through
  December 31, then $325.00", or the regular price alone once the early bird
  ends. It is used on the home page, the event page and the "registration is
  open" email. Those pages show the registration deadline separately, from
  `registration_closes_at`.
- The check form and the receipt print the list price as of the
  registration's `created_at`.

**In `/staff/events/{slug}/edit`:**
- "Registration fee": the early bird price.
- "Regular price (after early bird)".
- "Early bird ends": for the 2027 fair, January 1 at 12:00 AM.

The last two each require the other. Registration closing stays its own
field, and it is the deadline.

## 3. Hotel booking link

The link is a new `events.hotel_booking_url` column (https only), edited in
`/staff`. It is not hard-coded, because the hotel issues a new link every year.
`emails/notifications/partials/hotel-booking.blade.php` renders a "Book your
hotel room" button. It appears in both:
- the payment receipt, the confirmation email;
- the pay-by-check instructions, so a check payer gets it on the day they
  register.

The copy says "the fair's group rate", not "at the venue": the hotel and the
venue are one building in 2027, and need not be next year.

## 4. Mailing address, contact@ and alert recipients

**Mailing address.** `config/fair.php` and `.env.example` now default to:
- Megan Cooper
- McCallie School
- 500 Dodds Avenue
- Chattanooga, TN 37404

Two views ignored `address_line2`, which had always been blank: the receipt
PDF footer and the home page's "Checks by post". With McCallie as line 1, they
would have printed no street. Both print it now.

**The phone number did not change.** `(423) 757-2845` may be Baylor's line.
Confirm with the owner.

**Recipients.** `ADMIN_ALERT_EMAIL` accepts a comma-separated list (owner:
keep the real addresses in production `.env`). Every address receives:
- core's contact-form alerts (`config/core.php` `contact.recipients`);
- the registration and payment alerts (`AdminAlerts::emails()`);
- the monthly report.

**What code cannot do: the contact@ mailbox.** Mail sent *to*
contact@coasttocoastcollegefair.com is delivered by whoever hosts that domain's
mail, not by this app. Forwarding it to Megan and the client is a mail-host
setting.

## 5. The map

The map now loads with the page (owner), reversing doc 10 D-9-f. Before, it was
pinned at "Chattanooga, TN" generally (the design handoff's placeholder). Now it
is pinned at the venue's name and address through
`https://www.google.com/maps?q=…&output=embed`. That form needs no API key and
follows the venue fields in `/staff`.

The page dropped the Convention Center lines, and they come back when the
Westin's details are known (owner: "drop it until we have details"):
- "Complimentary parking"
- "Volunteer drop-off service" on Carter Street
- the venue section's parking and drop-off rows

The hero button now reads "Venue & map". The fallback venue, used before any
fair is published, is the Westin.

**The rest of the old venue, swept 2026-11-13** (owner: "change all the old
references to the convention center"). Two pieces of seeded copy still named
it, and both live in the database once seeded:
- **The FAQ** "When and where is the fair?" said the Chattanooga Convention &
  Trade Center, 1150 Carter Street. It now says The Westin Chattanooga, 801
  Pine Street.
- **The `home.hero` content block** promised "complimentary parking, and
  student volunteers to carry your materials in". Those were the Convention
  Center's arrangements, so they went, like the homepage lines above.

The seeders never overwrite an existing row, so both were changed twice:
- in `FaqSeeder` and `ContentBlockSeeder`, for a fresh database;
- by the migration `move_venue_copy_to_the_westin`, for one already seeded.

The migration rewrites a row **only while it still holds the original seeded
text**. An answer already edited in `/staff/faq` or `/admin` is left alone. On
production, check both after deploying.

**Kept on purpose:** the 2022–2026 fair rows. Those fairs were at the
Convention Center, so `EventSeeder::VENUE_NAME` stays, with a docblock saying
not to update it. Until the 2027 fair is published, the home and event pages
show the most recent published fair, 2026, at its real venue.
`WestinVenueTest` covers:
- the fresh seed;
- the migration on old text, including CRLF;
- an edited answer, left alone;
- the rollback;
- the home page and the FAQ, once 2027 is published.

## 6. Monthly registration report

`php artisan fair:send-registration-report` runs at 08:00 on the 1st of each
month. It mails every address on `ADMIN_ALERT_EMAIL`:
- counts by status and the amount collected, in the themed layout;
- a CSV of every registration for the active fair, attached.

The CSV columns are the staff export's, through one shared class:
`App\Services\RegistrationCsv`.

**Last year's registrations for the invitations:** run the same command for
2026 on production:

```bash
php artisan fair:send-registration-report --event=college-fair-2026
```

Or filter `/staff/registrations` to College Fair 2026 and press Export. Either
gives every rep's name, email and phone.

## 7. Favicon

`public/favicon.ico` was a **zero-byte file**, so every page linked an icon
that did not exist. The new mark is "C2C" in white on the brand green
(`#188042`), as:
- `favicon.svg`;
- `favicon.ico` (16/32/48, PNGs inside an ICO);
- `apple-touch-icon.png` (180px).

`resources/views/partials/favicons.blade.php` links all three. It is included
by the app, auth, portal and staff layouts and by the error pages.
`core.admin.favicon` points `/admin` at the same SVG.

The 2023 logo is a photo collage and the wordmark is a wide script lockup.
Neither reads at 16 pixels, so neither was used. A designer's mark can replace
these three files later with no code change.

## 8. The 2027 fair's data

`EventSeeder`'s 2027 row now carries the confirmed facts:
- April 6, 2027: reception 5:00, fair 6:30–8:00 p.m.;
- The Westin Chattanooga, 801 Pine Street;
- early bird $275 through December 31, then the regular $325;
- registration closes March 23, 2027 at 11:59 p.m., two weeks before the fair
  (owner, 2026-11-11). This is the advertised deadline. End of day is an
  inference from "two weeks before the fair".
- the booking link.

It no longer carries the TODO-OWNER name. It stays **unpublished**, and its
opening date (December 1) is still a placeholder nobody has confirmed.

**The seeder is idempotent by slug.** On a database that already has a 2027
row, re-seeding changes nothing. That row has to be edited in
`/staff/events/college-fair-2027/edit`:
- name, date and venue;
- the early bird price, the regular price and when the early bird ends;
- registration closes March 23;
- the booking link.

Then confirm the opening date and publish.

## 9. Not code: the owner's to do

- **Sponsor logos.** Uploading already exists. Go to `/staff/sponsors`, open a
  sponsor, choose a logo under "Logo" (an image of 2 MB or less) and save. It
  appears on `/sponsors` and the home page. The files live on the server's
  public disk, so they are uploaded on production, not committed here.
- **Stripe payouts.** Whether Stripe deposits into the bank is set in the
  Stripe Dashboard (Settings → Payouts, and the bank account under Business
  settings). It is not in this code. The owner should check it there.
- **Stripe login.** This repository holds no Stripe credentials, and a
  password should not be passed around by email. Stripe's Team settings can
  invite Megan and the client with their own logins and the right role.
- **Admin access.** There is no invite screen. On production, set
  `COORDINATOR_EMAIL` and run `ProductionSeeder` (doc 11), then send that
  address a password reset. Further accounts and roles are managed in core's
  `/admin` → Users.
- **Domain ownership.** No registrar or account holder is recorded anywhere in
  these docs (`docs/01` open questions; `docs/11` "DNS cut over from ISPEUS").
  A WHOIS lookup on coasttocoastcollegefair.com names the registrar, and
  whoever pays that renewal holds the account.

## 10. Verification

- 1016 passed, 34 skipped: 977 before, plus 39 new tests.
- New test files: `EventLateFeeTest`, `HotelBookingLinkTest`,
  `SendRegistrationReportTest` and `FaviconTest`.
- New cases in `EventsTest`, `CommsMatrixTest`, `PublicSiteTest` and
  `SeederTest`.
- The two old map tests asserted click-to-load. They were rewritten to the
  owner's reversal: the iframe is present on load and pinned at the venue.
- Pint clean.
