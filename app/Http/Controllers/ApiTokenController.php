<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The browser-side API console: a session-authed page that mints a personal
 * access token for the logged-in user and drives the v1 REST API over HTTP.
 *
 * This is deliberately distinct from the credentials-based POST /api/v1/tokens
 * endpoint. That one re-authenticates with email + password for headless
 * clients; this one trusts the existing web session, so the demo's "Generate
 * token" button issues a token without a second login.
 */
class ApiTokenController extends Controller
{
    /**
     * Render the API console, seeded with the campaigns the user can target.
     *
     * The page needs tenant context because the demo's API calls hit
     * /api/v1/campaigns/{slug}/contacts, so each campaign the user belongs to is
     * passed (with its role) for the page's campaign picker.
     */
    public function create(Request $request): Response
    {
        $campaigns = $request->user()->campaigns()
            ->orderBy('name')
            ->get()
            ->map(fn (Campaign $campaign): array => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'slug' => $campaign->slug,
                'role' => $campaign->pivot->role,
            ]);

        return Inertia::render('ApiDemo/Index', [
            'campaigns' => $campaigns,
        ]);
    }

    /**
     * Issue a personal access token for the already-authenticated web user.
     *
     * The plaintext token is returned once, as JSON, so the page can use it as a
     * Bearer credential. v1 tokens carry no granular abilities, so this token has
     * the user's full API access — acceptable for a demo console.
     */
    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'token' => $request->user()->createToken('api-demo')->plainTextToken,
            'token_type' => 'Bearer',
        ]);
    }
}
