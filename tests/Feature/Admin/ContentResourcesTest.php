<?php

use UClemmer\LaravelCore\Admin\Admin;
use UClemmer\LaravelCore\Contact\Permissions as ContactPermissions;
use UClemmer\LaravelCore\Content\Permissions as ContentPermissions;

beforeEach(function () {
    $this->coordinator = coordinator();
    $this->actingAs($this->coordinator);
});

/*
 * Rewritten 2026-08-22 for core 0.4. `ContentResource` and
 * `ContactSubmissionResource` are gone with the panel; the claims they carried
 * are not, and they are asserted here against the screens that replaced them.
 * Card 2.4 still says configure and verify, do not rebuild.
 */
describe('the laravel-core screens this app configures rather than builds', function () {
    it('registers content and contact on the admin', function () {
        // Doc 03: page copy is core Content of type `block`, and the contact
        // inbox is core's.
        expect(Admin::has('content.index'))->toBeTrue()
            ->and(Admin::has('contact.index'))->toBeTrue();
    });

    it('lets the coordinator reach both', function () {
        /*
         * Through the routes rather than a `canAccess()` static. Core 0.4 gates
         * a screen with route middleware plus the component's own `mount()`,
         * and asking the real URL is the only way to exercise both.
         */
        $this->get(Admin::url('content.index'))->assertOk();
        $this->get(Admin::url('contact.index'))->assertOk();
    });

    it('holds those screens behind the core permissions, not a role name', function () {
        // The permissions come from core; this app grants them to coordinator
        // through RoleSeeder rather than inventing its own.
        expect($this->coordinator->hasPermission(ContentPermissions::VIEW))->toBeTrue()
            ->and($this->coordinator->hasPermission(ContactPermissions::VIEW))->toBeTrue();
    });

    it('does not build a parallel content or contact table of its own', function () {
        expect(class_exists('App\Models\ContentBlock'))->toBeFalse()
            ->and(class_exists('App\Models\ContactSubmission'))->toBeFalse();
    });
});
