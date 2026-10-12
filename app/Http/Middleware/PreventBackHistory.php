<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tells the browser NOT to keep a copy of members-only pages. Without this, after signing out
 * the Back button can show a saved copy of /profile etc. even though the user is no longer signed in.
 *
 * Registered as 'no-history' in bootstrap/app.php; applied to the members-only route group in routes/web.php.
 * Pairs with resources/js/app.js ("pageshow" → reload), which re-checks the login if a page is restored anyway.
 */
class PreventBackHistory
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-cache, no-store, max-age=0, must-revalidate');  // ← never reuse a saved copy
        $response->headers->set('Pragma', 'no-cache');                                                 // ← same, for older browsers
        $response->headers->set('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');                           // ← already "expired"

        return $response;
    }
}
