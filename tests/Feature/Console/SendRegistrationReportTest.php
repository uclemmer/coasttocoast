<?php

use App\Enums\RegistrationStatus;
use App\Models\Event as Fair;
use App\Models\Organization;
use App\Models\Registration;
use App\Notifications\RegistrationReport;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

/*
 * The monthly registration report to the coordinator and the client
 * (docs/29), and the same command run by hand for last year's fair.
 */

beforeEach(function () {
    config()->set('fair.alerts.email', 'megan@mccallie.example,client@example.edu');

    $this->fair = Fair::factory()->published()->create(['name' => 'College Fair 2027', 'slug' => 'college-fair-2027']);
    $this->lastYear = Fair::factory()->published()->past()->create(['name' => 'College Fair 2026', 'slug' => 'college-fair-2026']);

    Registration::factory()->forEvent($this->fair)
        ->forOrganization(Organization::factory()->named('Kenyon College')->create())
        ->create(['rep_email' => 'dana@kenyon.example', 'status' => RegistrationStatus::Confirmed, 'price_cents' => 27500]);
    Registration::factory()->forEvent($this->lastYear)
        ->forOrganization(Organization::factory()->named('Sewanee')->create())
        ->create(['rep_email' => 'pat@sewanee.example']);
});

it('is scheduled for the first of every month', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'fair:send-registration-report'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 8 1 * *');
});

it('reports the active fair to everyone on the alert list', function () {
    Notification::fake();

    $this->artisan('fair:send-registration-report')->assertSuccessful();

    Notification::assertSentOnDemand(
        RegistrationReport::class,
        fn (RegistrationReport $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notification->event->is($this->fair)
            && $notifiable->routes['mail'] === ['megan@mccallie.example', 'client@example.edu'],
    );
});

it('reports any fair named by slug, for last year\'s invitation list', function () {
    Notification::fake();

    $this->artisan('fair:send-registration-report', ['--event' => 'college-fair-2026'])->assertSuccessful();

    Notification::assertSentOnDemand(
        RegistrationReport::class,
        fn (RegistrationReport $notification): bool => $notification->event->is($this->lastYear),
    );
});

it('attaches a CSV of that fair\'s registrations and nobody else\'s', function () {
    $message = (new RegistrationReport($this->fair))->toMail(new AnonymousNotifiable);

    $csv = $message->rawAttachments[0]['data'];

    expect($message->rawAttachments)->toHaveCount(1)
        ->and($message->rawAttachments[0]['options']['mime'])->toBe('text/csv')
        ->and($csv)->toStartWith('Organization,Fair,Status')
        ->toContain('dana@kenyon.example')
        ->not->toContain('pat@sewanee.example')
        ->and((string) $message->render())->toContain('$275.00');
});

it('fails on an unknown slug rather than mailing an empty report', function () {
    Notification::fake();

    $this->artisan('fair:send-registration-report', ['--event' => 'no-such-fair'])->assertFailed();

    Notification::assertNothingSent();
});
