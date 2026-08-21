<?php

use App\Models\User;
use Illuminate\Support\Facades\Schema;

it('stores a phone number and an sms opt-in', function () {
    $user = User::factory()->smsOptedIn()->create();

    expect($user->sms_opt_in)->toBeTrue()
        ->and($user->phone)->toStartWith('+1');
});

it('defaults new users to no phone and no sms', function () {
    $user = User::factory()->create();

    expect($user->sms_opt_in)->toBeFalse()
        ->and($user->phone)->toBeNull();
});

it('finds only opted-in users with a number as sms reachable', function () {
    User::factory()->smsOptedIn()->create();
    User::factory()->create(['sms_opt_in' => true, 'phone' => null]);
    User::factory()->create(['sms_opt_in' => false, 'phone' => '+15551234567']);

    expect(User::query()->smsReachable()->count())->toBe(1);
});

it('has no is_admin column — roles come from laravel-core', function () {
    expect(Schema::hasColumn('users', 'is_admin'))->toBeFalse();
});
