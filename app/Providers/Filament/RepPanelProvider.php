<?php

namespace App\Providers\Filament;

use App\Filament\Rep\Pages\Auth\EditProfile;
use App\Filament\Rep\Pages\Auth\Register;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The representative portal at /portal — the app's own Filament panel.
 *
 * Unlike /admin (laravel-core's prebuilt panel, gated on `admin.access`), this
 * one is open to any user who has verified their email; what a rep may actually
 * DO is gated on organization membership status, which arrives with card 3.0.
 *
 * Filament owns the whole auth surface here: login, self-service registration,
 * password reset and email verification. There is no Fortify/Breeze/Jetstream.
 */
class RepPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('rep')
            ->path('portal')
            ->brandName((string) config('core.admin.brand', config('app.name')))
            ->colors(static::colors())
            ->login()
            // Our own registration page: signing up here also creates or
            // claims a school, and which of those it is decides whether the
            // account is active immediately or waits on the coordinator (D9).
            ->registration(Register::class)
            ->passwordReset()
            ->emailVerification()
            // Our own profile page: phone, SMS opt-in and self-retire (R2.10).
            ->profile(EditProfile::class)
            ->pages([Dashboard::class])
            ->discoverResources(app_path('Filament/Rep/Resources'), 'App\\Filament\\Rep\\Resources')
            ->discoverPages(app_path('Filament/Rep/Pages'), 'App\\Filament\\Rep\\Pages')
            ->discoverWidgets(app_path('Filament/Rep/Widgets'), 'App\\Filament\\Rep\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                ConvertEmptyStringsToNull::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /**
     * Share the admin panel's palette so both panels look like one product.
     *
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
