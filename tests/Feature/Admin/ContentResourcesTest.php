<?php

use Filament\Facades\Filament;
use UClemmer\LaravelCore\Admin\Resources\ContactSubmissionResource;
use UClemmer\LaravelCore\Admin\Resources\ContentResource;

beforeEach(function () {
    usingAdminPanel();
    $this->coordinator = coordinator();
    $this->actingAs($this->coordinator);
});

describe('the laravel-core resources this app configures rather than builds', function () {
    it('registers content and contact on the admin panel', function () {
        // Doc 03: page copy is core Content of type `block`, and the contact
        // inbox is core's. Card 2.4 says configure and verify, do not rebuild.
        $resources = Filament::getPanel('core')->getResources();

        expect($resources)->toContain(ContentResource::class)
            ->toContain(ContactSubmissionResource::class);
    });

    it('lets the coordinator reach both', function () {
        expect(ContentResource::canAccess())->toBeTrue()
            ->and(ContactSubmissionResource::canAccess())->toBeTrue();
    });

    it('does not build a parallel content or contact table of its own', function () {
        expect(class_exists('App\\Models\\ContentBlock'))->toBeFalse()
            ->and(class_exists('App\\Models\\ContactSubmission'))->toBeFalse();
    });
});
