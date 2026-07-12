<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates every POST /api/integrations/retell/tools/* endpoint. These have no
 * acting user (Retell's infrastructure calls them mid-conversation, not a
 * logged-in staff member) so a shared secret — configured as a custom
 * header on each tool in the Retell agent dashboard — is the entire
 * authentication boundary. Refuses rather than allows when unconfigured,
 * since these endpoints can read seller/buyer PII and trigger real actions.
 */
class VerifyRetellToolSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.retell.tools_secret');
        if ($secret === '') {
            abort(503, 'Retell tools are not configured.');
        }

        $provided = (string) $request->header('X-Retell-Tools-Secret', '');
        if ($provided === '' || ! hash_equals($secret, $provided)) {
            abort(401, 'Unauthorized');
        }

        return $next($request);
    }
}
