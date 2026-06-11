<?php

namespace App\Http\Middleware;

use App\Models\Campaign;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The tenant boundary chokepoint: a request reaches the campaign-scoped routes only if the
 * authenticated user is a member of the bound {@see Campaign}; everyone else gets a 403.
 *
 * Authentication is composed separately (the `auth` middleware runs first and redirects guests
 * to login / 401s the API), so this middleware is stateless and identical for web and API. The
 * resolved campaign and the user's role are stashed on the request for downstream use.
 */
class EnsureCampaignAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $campaign = $request->route('campaign');

        abort_unless($campaign instanceof Campaign, 404);

        $role = $request->user()?->roleIn($campaign);

        abort_if($role === null, 403);

        $request->attributes->set('campaign', $campaign);
        $request->attributes->set('role', $role);

        return $next($request);
    }
}
