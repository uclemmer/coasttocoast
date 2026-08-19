<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The public site, as a third Filament panel (cards 5.1–5.4).
 *
 * The owner's directive is that all UI is Filament — no hand-built Blade,
 * Tailwind, Livewire or Flowbite (doc 02). Doc 02 offers two readings of that
 * for public pages, and this is the stricter one: Filament custom pages
 * exposed publicly, rather than Blade views that happen to use Filament's
 * components. Nothing here is hand-built markup.
 *
 * **Flagged for the owner** (doc 10, D-5.1-a): a Filament panel is an
 * application shell, and a public marketing site rendered in one is unusual —
 * top navigation and a full-width content area get it close, but the visual
 * design is his call and this is the piece most likely to want revisiting.
 *
 * There is deliberately no `->login()` and no `Authenticate` middleware. The
 * two authenticated panels are `/admin` and `/portal`; a visitor here is
 * nobody, and the panel must never ask them to become somebody.
 */
class SitePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('site')
            // The site root. `/admin` and `/portal` register their own
            // prefixes, and Filament adds no catch-all here, so they win on
            // their own paths regardless of order.
            ->path('')
            ->brandName((string) config('core.admin.brand', config('app.name')))
            ->colors(static::colors())
            // Reads as a website rather than an admin sidebar.
            ->topNavigation()
            ->maxContentWidth(Width::FiveExtraLarge)
            ->discoverPages(app_path('Filament/Site/Pages'), 'App\\Filament\\Site\\Pages')
            ->discoverWidgets(app_path('Filament/Site/Widgets'), 'App\\Filament\\Site\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                // Session and errors are needed even without auth: the contact
                // form and the interest form both flash validation back.
                StartSession::class,
                ShareErrorsFromSession::class,
                ConvertEmptyStringsToNull::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function colors(): array
    {
        /** @var array<string, mixed> $colors */
        $colors = (array) config('core.admin.colors', []);

        return array_map(
            fn (mixed $color): mixed => is_string($color) && str_starts_with($color, '#')
                ? Color::hex($color)
                : $color,
            $colors,
        );
    }
}
