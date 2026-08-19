<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\RepPanelProvider;
use App\Providers\Filament\SitePanelProvider;

/*
 * The admin panel is not listed here: laravel-core registers its own
 * `CorePanelProvider` when `core.admin.enabled` is true (doc 08).
 *
 * `SitePanelProvider` is last on purpose. It owns the site root, so anything
 * registering a literal prefix — /admin, /portal — should get its routes in
 * first.
 */
return [
    AppServiceProvider::class,
    RepPanelProvider::class,
    SitePanelProvider::class,
];
