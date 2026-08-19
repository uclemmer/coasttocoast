<?php

use App\Providers\AppServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\Filament\RepPanelProvider;

/*
 * The admin panel is not listed here: laravel-core registers its own
 * `CorePanelProvider` when `core.admin.enabled` is true (doc 08).
 *
 * The public site is not a panel at all any more — it is Blade and Livewire
 * behind routes/web.php (Phase 8). The registration-order note that used to
 * live here went with it.
 */
return [
    AppServiceProvider::class,
    EventServiceProvider::class,
    RepPanelProvider::class,
];
