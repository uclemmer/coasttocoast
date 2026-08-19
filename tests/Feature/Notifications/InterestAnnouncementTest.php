<?php

use App\Filament\Admin\Resources\EventResource\Pages\ViewEvent;
use App\Models\Event as Fair;
use App\Models\EventInterest;
use App\Notifications\RegistrationOpenAnnouncement;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    usingAdminPanel();
    $this->actingAs(coordinator());
    Notification::fake();

    $this->fair = Fair::factory()->registrationOpen()->create();
});

describe('announcing that registration is open', function () {
    it('mails everyone waiting and stamps them', function () {
        EventInterest::factory()->count(3)->for($this->fair)->create();

        livewire(ViewEvent::class, ['record' => $this->fair->getRouteKey()])
            ->callAction('announceRegistrationOpen');

        Notification::assertSentOnDemandTimes(RegistrationOpenAnnouncement::class, 3);

        expect($this->fair->interests()->unnotified()->count())->toBe(0);
    });

    it('skips anyone already told, so pressing twice is harmless', function () {
        // The realistic mistake is a coordinator unsure whether the first
        // press worked. The answer to that should be "press it again", not a
        // hundred duplicate emails.
        EventInterest::factory()->for($this->fair)->create();
        EventInterest::factory()->for($this->fair)->notified()->create();

        livewire(ViewEvent::class, ['record' => $this->fair->getRouteKey()])
            ->callAction('announceRegistrationOpen');

        Notification::assertSentOnDemandTimes(RegistrationOpenAnnouncement::class, 1);
    });

    it('does nothing at all on a second run', function () {
        EventInterest::factory()->count(2)->for($this->fair)->create();

        livewire(ViewEvent::class, ['record' => $this->fair->getRouteKey()])
            ->callAction('announceRegistrationOpen');

        // The action hides itself once nobody is left, which is the check the
        // coordinator sees; this proves the underlying set is empty too.
        expect($this->fair->interests()->unnotified()->count())->toBe(0);

        livewire(ViewEvent::class, ['record' => $this->fair->refresh()->getRouteKey()])
            ->assertActionHidden('announceRegistrationOpen');

        Notification::assertSentOnDemandTimes(RegistrationOpenAnnouncement::class, 2);
    });

    it('never touches another fair\'s list', function () {
        $other = Fair::factory()->registrationClosed()->create();
        EventInterest::factory()->for($this->fair)->create();
        $elsewhere = EventInterest::factory()->for($other)->create();

        livewire(ViewEvent::class, ['record' => $this->fair->getRouteKey()])
            ->callAction('announceRegistrationOpen');

        expect($elsewhere->refresh()->notified_at)->toBeNull();
    });

    it('is hidden when nobody is waiting', function () {
        livewire(ViewEvent::class, ['record' => $this->fair->getRouteKey()])
            ->assertActionHidden('announceRegistrationOpen');
    });

    it('is hidden on an unpublished fair', function () {
        // Announcing a draft would send a hundred people to a 404.
        $draft = Fair::factory()->create();
        EventInterest::factory()->for($draft)->create();

        livewire(ViewEvent::class, ['record' => $draft->getRouteKey()])
            ->assertActionHidden('announceRegistrationOpen');
    });
});
