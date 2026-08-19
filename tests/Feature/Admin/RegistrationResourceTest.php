<?php

use App\Enums\PaymentMethod;
use App\Enums\RegistrationStatus;
use App\Filament\Admin\Resources\RegistrationResource\Pages\CreateRegistration;
use App\Filament\Admin\Resources\RegistrationResource\Pages\EditRegistration;
use App\Filament\Admin\Resources\RegistrationResource\Pages\ListRegistrations;
use App\Models\Event as Fair;
use App\Models\Grant;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;

beforeEach(function () {
    usingAdminPanel();
    $this->coordinator = coordinator();
    $this->actingAs($this->coordinator);
    $this->fair = Fair::factory()->registrationOpen()->priced(21500)->create();
    $this->school = Organization::factory()->named('Kenyon College')->create();
});

describe('the list', function () {
    it('filters by fair, status and payment method', function () {
        $otherFair = Fair::factory()->create();

        $confirmed = Registration::factory()->forEvent($this->fair)->create();
        $pendingCheck = Registration::factory()->pendingCheck()->forEvent($this->fair)->create();
        $elsewhere = Registration::factory()->forEvent($otherFair)->create();

        livewire(ListRegistrations::class)
            ->filterTable('event_id', $this->fair->id)
            ->assertCanSeeTableRecords([$confirmed, $pendingCheck])
            ->assertCanNotSeeTableRecords([$elsewhere]);

        livewire(ListRegistrations::class)
            ->filterTable('status', RegistrationStatus::PendingPayment->value)
            ->assertCanSeeTableRecords([$pendingCheck])
            ->assertCanNotSeeTableRecords([$confirmed]);

        livewire(ListRegistrations::class)
            ->filterTable('payment_method', PaymentMethod::Check->value)
            ->assertCanSeeTableRecords([$pendingCheck])
            ->assertCanNotSeeTableRecords([$confirmed]);
    });

    it('searches by school and by contact', function () {
        $kenyon = Registration::factory()->forOrganization($this->school)
            ->create(['rep_name' => 'Dana Whitfield', 'rep_email' => 'dw@kenyon.example']);
        $other = Registration::factory()->create();

        livewire(ListRegistrations::class)
            ->searchTable('Kenyon')
            ->assertCanSeeTableRecords([$kenyon])
            ->assertCanNotSeeTableRecords([$other]);

        livewire(ListRegistrations::class)
            ->searchTable('dw@kenyon.example')
            ->assertCanSeeTableRecords([$kenyon])
            ->assertCanNotSeeTableRecords([$other]);
    });

    it('filters to registrations carrying a grant', function () {
        $grant = Grant::factory()->free()->for($this->fair)->for($this->school)->create();
        $granted = Registration::factory()->free()->forEvent($this->fair)->forOrganization($this->school)
            ->create(['grant_id' => $grant->id]);
        $paying = Registration::factory()->forEvent($this->fair)->create();

        livewire(ListRegistrations::class)
            ->filterTable('grant_id', true)
            ->assertCanSeeTableRecords([$granted])
            ->assertCanNotSeeTableRecords([$paying]);
    });

    it('keeps a user without the permission out', function () {
        $this->actingAs(User::factory()->rep()->create());

        livewire(ListRegistrations::class)->assertForbidden();
    });
});

describe('the CSV export', function () {
    it('exports the rows currently filtered, with the columns the coordinator needs', function () {
        // Streamed rather than queued precisely so the filter applies — an
        // export that ignored it would not be the same feature.
        $grant = Grant::factory()->percentOff(50)->for($this->fair)->for($this->school)->create();
        Registration::factory()->forEvent($this->fair)->forOrganization($this->school)->create([
            'grant_id' => $grant->id,
            'price_cents' => 10750,
            'rep_name' => 'Dana Whitfield',
            'rep_email' => 'dw@kenyon.example',
        ]);
        Registration::factory()->create(); // another fair — must not appear

        $response = livewire(ListRegistrations::class)
            ->filterTable('event_id', $this->fair->id)
            ->callAction('export');

        $csv = downloadedContent($response);

        expect($csv)->toContain('Kenyon College')
            ->toContain('Dana Whitfield')
            ->toContain('$107.50')
            ->toContain('50% off registration')
            ->and(substr_count($csv, "\n"))->toBe(2); // header + one row
    });
});

describe('manual entry', function () {
    it('creates a registration with no account behind it, priced from the fair', function () {
        livewire(CreateRegistration::class)
            ->fillForm([
                'event_id' => $this->fair->id,
                'organization_id' => $this->school->id,
                'payment_method' => PaymentMethod::Check->value,
                'rep_name' => 'Kim Alvarado',
                'rep_email' => 'kim@kenyon.example',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $registration = Registration::query()->latest('id')->first();

        expect($registration->user_id)->toBeNull()
            ->and($registration->price_cents)->toBe(21500)
            ->and($registration->status)->toBe(RegistrationStatus::PendingPayment);
    });

    it('applies an approved grant even though the coordinator entered it', function () {
        Grant::factory()->customPrice(5000)->for($this->fair)->for($this->school)->create();

        livewire(CreateRegistration::class)
            ->fillForm([
                'event_id' => $this->fair->id,
                'organization_id' => $this->school->id,
                'payment_method' => PaymentMethod::Check->value,
                'rep_name' => 'Kim',
                'rep_email' => 'kim@kenyon.example',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Registration::query()->latest('id')->first()->price_cents)->toBe(5000);
    });

    it('reports a duplicate on the form instead of failing generically', function () {
        Registration::factory()->forEvent($this->fair)->forOrganization($this->school)->create();

        livewire(CreateRegistration::class)
            ->fillForm([
                'event_id' => $this->fair->id,
                'organization_id' => $this->school->id,
                'payment_method' => PaymentMethod::Check->value,
                'rep_name' => 'Kim',
                'rep_email' => 'kim@kenyon.example',
            ])
            ->call('create')
            ->assertHasFormErrors(['organization_id']);
    });

    it('works on a fair whose registration has closed', function () {
        $closed = Fair::factory()->registrationClosed()->create();

        livewire(CreateRegistration::class)
            ->fillForm([
                'event_id' => $closed->id,
                'organization_id' => $this->school->id,
                'payment_method' => PaymentMethod::Check->value,
                'rep_name' => 'Kim',
                'rep_email' => 'kim@kenyon.example',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Registration::query()->where('event_id', $closed->id)->count())->toBe(1);
    });
});

describe('editing', function () {
    it('changes roster visibility and notes', function () {
        $registration = Registration::factory()->create();

        livewire(EditRegistration::class, ['record' => $registration->getRouteKey()])
            ->fillForm(['show_on_roster' => false, 'notes' => 'Asked not to be listed.'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($registration->refresh()->show_on_roster)->toBeFalse()
            ->and($registration->notes)->toBe('Asked not to be listed.');
    });

    it('does not expose status or price as editable fields', function () {
        // Editing either by hand would skip the events that send receipts, and
        // break the snapshot that proves what a school agreed to pay (N1).
        $registration = Registration::factory()->create();

        livewire(EditRegistration::class, ['record' => $registration->getRouteKey()])
            ->assertFormFieldDoesNotExist('status')
            ->assertFormFieldDoesNotExist('price_cents')
            ->assertFormFieldDoesNotExist('event_id')
            ->assertFormFieldDoesNotExist('organization_id');
    });
});

describe('the cancel action', function () {
    it('cancels, records the reason, and keeps the row', function () {
        $registration = Registration::factory()->forEvent($this->fair)->create();

        livewire(ListRegistrations::class)
            ->callTableAction('cancel', $registration, ['reason' => 'Travel budget cut.']);

        expect($registration->refresh()->status)->toBe(RegistrationStatus::Cancelled)
            ->and($registration->notes)->toContain('Travel budget cut.')
            ->and(Registration::query()->find($registration->id))->not->toBeNull();
    });

    it('disappears once there is nothing left to cancel', function () {
        $live = Registration::factory()->forEvent($this->fair)->create();
        $cancelled = Registration::factory()->cancelled()->forEvent($this->fair)->create();

        livewire(ListRegistrations::class)
            ->assertTableActionVisible('cancel', $live)
            ->assertTableActionHidden('cancel', $cancelled);
    });

    it('is never a delete', function () {
        $registration = Registration::factory()->create();

        expect($this->coordinator->can('delete', $registration))->toBeFalse();
    });
});
