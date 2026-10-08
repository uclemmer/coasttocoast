<?php

/*
 * A published package config drifts silently: every read in the package
 * passes a default, so a key the host never received still works — and the
 * file stops saying what the package can do. Re-publishing with `--force` is
 * not the fix, because it rewrites the whole file; the postmaster upgrade did
 * exactly that and reset a master switch (docs/20).
 *
 * So new keys are written in by hand on each upgrade, and this test is what
 * notices when one was missed. It caught five on 2026-10-07, taking core to
 * 0.8: 0.7.3's `auth.remember`, the `auth.two_factor.code.*` trio from 0.7.0
 * (docs/22 wrote in only `channels` and `sms_sender`), and `admin.middleware`
 * from 0.4 — absent since this file was published before 0.4. Core's route
 * registrar falls back to the same list, so /admin stayed guarded; the gap
 * was that the guard could not be seen where it is set.
 *
 * Copied from projects/uclemmer's test of the same name.
 */

/**
 * @param  array<array-key, mixed>  $config
 * @return list<string>
 */
function dottedConfigKeys(array $config, string $prefix = ''): array
{
    $keys = [];

    foreach ($config as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

        if (is_array($value) && $value !== [] && ! array_is_list($value)) {
            $keys = [...$keys, ...dottedConfigKeys($value, $path)];
        } else {
            $keys[] = $path;
        }
    }

    return $keys;
}

it('carries every key the installed package config defines', function (string $package, string $file) {
    $shipped = dottedConfigKeys(require base_path("vendor/uclemmer/{$package}/config/{$file}"));
    $published = dottedConfigKeys(require config_path($file));

    expect(array_values(array_diff($shipped, $published)))->toBe([]);
})->with([
    'core' => ['laravel-core', 'core.php'],
    'postmaster' => ['laravel-postmaster', 'postmaster.php'],
]);

it('guards every admin route with authentication and the admin permission', function () {
    /*
     * The behaviour behind the `admin.middleware` key, asserted on the routes
     * themselves rather than on the config value, so it holds whether the
     * guard comes from this file or from core's fallback.
     */
    $admin = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'admin'));

    expect($admin)->not->toBeEmpty();

    foreach ($admin as $route) {
        expect($route->gatherMiddleware())
            ->toContain('core.auth')
            ->toContain('core.permission:admin.access');
    }
});
