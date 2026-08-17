<?php

namespace App\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * The application's contribution to laravel-core's prebuilt admin panel.
 *
 * Registered as a class-string in `core.admin.plugins` (config/core.php) — the
 * same seam uclemmer/laravel-tickets uses. Core owns the panel shell, its auth,
 * and its own modules (users, roles, email log, content, contact, queue,
 * settings); everything fair-shaped is contributed from here.
 *
 * It contributes by DISCOVERY: drop a resource in app/Filament/Admin/Resources,
 * a page in .../Pages or a widget in .../Widgets and it appears. Cards 2.1–2.6
 * fill those directories; today they are empty and the plugin is a no-op.
 */
class FairPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'fair';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->discoverResources(app_path('Filament/Admin/Resources'), 'App\\Filament\\Admin\\Resources')
            ->discoverPages(app_path('Filament/Admin/Pages'), 'App\\Filament\\Admin\\Pages')
            ->discoverWidgets(app_path('Filament/Admin/Widgets'), 'App\\Filament\\Admin\\Widgets');
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
