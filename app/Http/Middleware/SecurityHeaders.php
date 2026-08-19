<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Response headers every page gets (card 7.1).
 *
 * Deliberately conservative. This application takes card payments through a
 * hosted Stripe page and runs three Filament panels, and Filament ships inline
 * styles and Alpine expressions — a Content-Security-Policy tight enough to be
 * worth having would break the admin panel, and one loose enough not to would
 * be decoration. That is a real decision, recorded in doc 10 (D-7.1-a), not an
 * omission.
 *
 * What is here is the set that costs nothing and closes something:
 *
 *  - **HSTS**, so a returning visitor never makes a plaintext request that
 *    could be intercepted. Only over HTTPS: sending it over http is
 *    meaningless, and sending it in local development would pin
 *    `coasttocoastcollegefair.test` to https in the developer's browser for a
 *    year, which is a genuinely annoying thing to do to somebody.
 *  - **X-Content-Type-Options**, so an uploaded logo cannot be sniffed into
 *    something executable.
 *  - **X-Frame-Options**, so the admin panel and the portal cannot be framed
 *    for clickjacking.
 *  - **Referrer-Policy**, so a rep clicking a link from their registration
 *    page does not leak the URL to a third party.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
            );
        }

        return $response;
    }
}
