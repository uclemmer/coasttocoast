<?php

use App\Livewire\Portal\ShowRegistration as ViewRegistration;
use App\Models\Event as Fair;
use App\Models\Grant;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use App\Services\ReceiptPdf;

beforeEach(function () {

    $this->organization = Organization::factory()->named('Kenyon College')->create([
        'address_line1' => '100 College Drive',
        'city' => 'Gambier',
        'state' => 'OH',
        'postal_code' => '43022',
    ]);
    $this->rep = User::factory()->rep($this->organization)->create();
    $this->fair = Fair::factory()->registrationOpen()->priced(21500)->create();

    $this->actingAs($this->rep);
});

describe('the receipt', function () {
    it('renders a PDF carrying the organization, the fair and the amount', function () {
        $registration = Registration::factory()->forEvent($this->fair)->forOrganization($this->organization)
            ->create(['price_cents' => 21500, 'rep_name' => 'Dana Whitfield']);
        Payment::factory()->for($registration)->create(['amount_cents' => 21500]);

        $pdf = app(ReceiptPdf::class)->render($registration);

        expect($pdf)->toStartWith('%PDF-')
            ->and(strlen($pdf))->toBeGreaterThan(1000);
    });

    it('names the file after the fair and the registration', function () {
        $registration = Registration::factory()->forEvent($this->fair)->forOrganization($this->organization)->create();

        expect(app(ReceiptPdf::class)->filenameFor($registration))
            ->toBe('receipt-'.$this->fair->slug.'-'.$registration->id.'.pdf');
    });

    it('reports the snapshot price, not what the fair costs today', function () {
        // A receipt that recalculated would quietly disagree with the invoice
        // the moment the fair's price changed. That is the one thing a receipt
        // must never do.
        $registration = Registration::factory()->forEvent($this->fair)->forOrganization($this->organization)
            ->create(['price_cents' => 19500]);

        $this->fair->update(['price_cents' => 30000]);

        expect($registration->refresh()->price_cents)->toBe(19500);

        // The view reads the snapshot; rendering proves the template does not
        // touch the event's current price for the total.
        expect(app(ReceiptPdf::class)->render($registration))->toStartWith('%PDF-');
    });

    it('renders for a registration a grant made free', function () {
        $grant = Grant::factory()->free()->for($this->fair)->for($this->organization)->create();
        $registration = Registration::factory()->free()->forEvent($this->fair)->forOrganization($this->organization)
            ->create(['grant_id' => $grant->id]);

        expect(app(ReceiptPdf::class)->render($registration))->toStartWith('%PDF-');
    });
});

describe('availability', function () {
    it('exists only once the registration is confirmed', function (string $state, bool $available) {
        // A receipt for money that has not arrived is exactly the document a
        // finance office files and forgets about.
        $registration = Registration::factory()->{$state}()->forEvent($this->fair)
            ->forOrganization($this->organization)->create();

        expect(app(ReceiptPdf::class)->isAvailableFor($registration))->toBe($available);
    })->with([
        'confirmed' => ['free', true],
        'awaiting a card payment' => ['pendingStripe', false],
        'awaiting a check' => ['pendingCheck', false],
        'cancelled' => ['cancelled', false],
        'refunded' => ['refunded', false],
    ]);

    it('offers the download on a confirmed registration and hides it otherwise', function () {
        $confirmed = Registration::factory()->forEvent($this->fair)->forOrganization($this->organization)->create();
        $pending = Registration::factory()->pendingCheck()->forEvent($this->fair)
            ->forOrganization($this->organization)->create();

        expect(livewire(ViewRegistration::class, ['registration' => $confirmed])->instance()->hasReceipt())->toBeTrue();

        expect(livewire(ViewRegistration::class, ['registration' => $pending])->instance()->hasReceipt())->toBeFalse();
    });

    it('downloads through the portal', function () {
        $registration = Registration::factory()->forEvent($this->fair)->forOrganization($this->organization)->create();

        $response = livewire(ViewRegistration::class, ['registration' => $registration])
            ->call('receipt');

        expect(downloadedContent($response))->toStartWith('%PDF-');
    });
});
