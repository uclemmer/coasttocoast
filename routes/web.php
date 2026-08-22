<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\EventInterestController;
use App\Http\Controllers\SiteController;
use App\Livewire\Auth\Register;
use App\Livewire\LastYearRoster;
use App\Livewire\Portal\CreateRegistration;
use App\Livewire\Portal\Dashboard;
use App\Livewire\Portal\Grants;
use App\Livewire\Portal\OrganizationProfile;
use App\Livewire\Portal\Profile;
use App\Livewire\Portal\Registrations;
use App\Livewire\Portal\ShowRegistration;
use App\Livewire\RepresentativesRoster;
use App\Livewire\Staff\Events\Edit as EditEvent;
use App\Livewire\Staff\Events\Index as EventIndex;
use App\Livewire\Staff\Events\Show as ShowEvent;
use App\Livewire\Staff\Faq\Edit as EditFaqItem;
use App\Livewire\Staff\Faq\Index as FaqIndex;
use App\Livewire\Staff\Grants\Index as GrantIndex;
use App\Livewire\Staff\Grants\Show as ShowGrant;
use App\Livewire\Staff\Organizations\Edit as EditOrganization;
use App\Livewire\Staff\Organizations\Index as OrganizationIndex;
use App\Livewire\Staff\Organizations\Show as ShowOrganization;
use App\Livewire\Staff\Sponsors\Edit as EditSponsor;
use App\Livewire\Staff\Sponsors\Index as SponsorIndex;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| The public site
|--------------------------------------------------------------------------
|
| Blade and Livewire (owner directive, 2026-08-19). Filament keeps the two
| authenticated surfaces — /admin (laravel-core's Filament panel) and /portal
| (Livewire, see docs/12) —
| and registers their routes itself.
|
| Route names are prefixed `site.` so the navigation can highlight the current
| page without matching on URLs.
|
*/

/*
 * Representative sign-up (D9), replacing the Filament rep panel's Register
 * page. Guest-only: a signed-in rep landing here could otherwise create a
 * second account and a second school membership.
 *
 * Outside the `site.` name group on purpose. This is an authentication screen,
 * not a site page, and the conventional route name is what packages and the
 * framework look for - laravel-core's own login view checks
 * Route::has('register') before offering a sign-up link.
 *
 * Login, logout and password reset are laravel-core's, registered by the
 * package from config/core.php `auth.routes`. Registration is ours because
 * signing up claims or creates a school, and which of those it is decides
 * whether the account is active immediately. See docs/12.
 */
Route::get('/register', Register::class)->middleware('guest')->name('register');

/*
 * The representative portal (docs/12).
 *
 * Was a Filament panel until 2026-08-21; these are the Livewire components that
 * replaced it, at the same URLs so every bookmark and emailed link still lands.
 *
 * `verified` sits alongside `auth` because the panel enforced it and dropping
 * it here would quietly widen who can register a school. Membership - pending,
 * active, retired - is enforced per screen instead, since browsing is allowed
 * and acting is not; see Portal\Concerns\ActsForAnOrganization.
 */
Route::middleware(['auth', 'verified'])->prefix('portal')->name('portal.')->group(function (): void {
    Route::get('/', Dashboard::class)->name('dashboard');

    Route::get('/registrations', Registrations::class)->name('registrations');
    Route::get('/registrations/create', CreateRegistration::class)->name('registrations.create');
    Route::get('/registrations/{registration}', ShowRegistration::class)->name('registrations.show');

    Route::get('/grants', Grants::class)->name('grants');
    // 'organization-profile', not the tidier 'organization': this is the URL
    // Filament's panel served, and the route comment above promises bookmarks
    // still land. A nicer path is not worth breaking that.
    Route::get('/organization-profile', OrganizationProfile::class)->name('organization');
    Route::get('/profile', Profile::class)->name('profile');
});

/*
 * The staff area (docs/13).
 *
 * The fair's own admin screens, rebuilt off Filament under the 2026-08-20
 * directive. NOT the only staff surface yet: laravel-core keeps its Filament
 * panel at /admin for users, roles, the email log, content and settings until
 * step 4 of the workspace removal, and the two agree on who is staff because
 * both ask the same `admin.access` permission.
 *
 * A new prefix rather than taking /admin, which core still owns. Nothing was
 * bookmarkable here before - these screens lived inside the panel's own URLs -
 * so unlike /portal there is no promise to keep about paths.
 *
 * `verified` alongside `auth` matches every other authenticated surface here.
 * Per-screen permission is the policies' job, asked with `$this->authorize()`
 * in each component's mount and again in each action.
 */
Route::middleware(['auth', 'verified'])->prefix('staff')->name('staff.')->group(function (): void {
    Route::get('/events', EventIndex::class)->name('events');
    Route::get('/events/create', EditEvent::class)->name('events.create');
    // The show route is last of the three static-vs-dynamic pair so `/create`
    // is never swallowed by `{event}`.
    Route::get('/events/{event}', ShowEvent::class)->name('events.show');
    Route::get('/events/{event}/edit', EditEvent::class)->name('events.edit');

    Route::get('/organizations', OrganizationIndex::class)->name('organizations');
    Route::get('/organizations/create', EditOrganization::class)->name('organizations.create');
    Route::get('/organizations/{organization}', ShowOrganization::class)->name('organizations.show');
    Route::get('/organizations/{organization}/edit', EditOrganization::class)->name('organizations.edit');

    Route::get('/sponsors', SponsorIndex::class)->name('sponsors');
    Route::get('/sponsors/create', EditSponsor::class)->name('sponsors.create');
    Route::get('/sponsors/{sponsor}/edit', EditSponsor::class)->name('sponsors.edit');

    Route::get('/faq', FaqIndex::class)->name('faq');
    Route::get('/faq/create', EditFaqItem::class)->name('faq.create');
    Route::get('/faq/{item}/edit', EditFaqItem::class)->name('faq.edit');

    // No create or edit, deliberately: a grant is applied for through the
    // portal and decided here, through GrantService. See docs/13.
    Route::get('/grants', GrantIndex::class)->name('grants');
    Route::get('/grants/{grant}', ShowGrant::class)->name('grants.show');
});

/*
 * Email verification, app-owned.
 *
 * The Filament rep panel supplied this and neither laravel-core version has
 * it, so these three routes are the whole of it - Laravel provides the
 * notification, the signed URL, the MustVerifyEmail contract and the
 * `verified` middleware. See App\Http\Controllers\Auth\EmailVerificationController.
 *
 * `signed` on the verify route is what makes the link unforgeable, and the
 * throttle on the resend is Laravel's own default for it.
 */
Route::middleware('auth')->group(function (): void {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

Route::name('site.')->group(function (): void {
    Route::get('/', [SiteController::class, 'home'])->name('home');
    Route::get('/about', [SiteController::class, 'about'])->name('about');
    Route::get('/sponsors', [SiteController::class, 'sponsors'])->name('sponsors');
    Route::get('/faq', [SiteController::class, 'faq'])->name('faq');

    /*
     * The rosters are Livewire because they want search and pagination. One
     * component behind both, differing only in which fair it reads — the live
     * site's Last Year page was showing the *current* roster (doc 00), and
     * that is what happens when they are two pieces of code.
     */
    Route::get('/representatives', RepresentativesRoster::class)->name('representatives');
    Route::get('/last-year', LastYearRoster::class)->name('last-year');

    Route::get('/contact', [SiteController::class, 'contact'])->name('contact');

    /*
     * `{event:slug}` rather than an id: the slug is in every link the fair has
     * ever sent out.
     */
    Route::get('/events/{event:slug}', [SiteController::class, 'event'])->name('event');
});

/*
 * "Tell me when registration opens", as a plain POST.
 *
 * The event page offers the same capture as a Livewire form; this is the
 * non-JavaScript path, and the only place an IP throttle can hang. Both
 * lowercase the address before writing, so they cannot diverge in the way that
 * matters (doc 10, D-5.4-b).
 */
Route::post('/events/{event}/interest', [EventInterestController::class, 'store'])
    ->middleware('throttle:5,60')
    ->name('events.interest');
