<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the public email webhook: the request body must carry a valid HMAC-SHA256
 * signature (in the {@see self::SIGNATURE_HEADER} header) computed with the shared
 * secret. Without a configured secret, a signature header, or a matching digest the
 * request is rejected with a 403 — the endpoint fails closed, so an unsigned or
 * forged payload never reaches the ingestion controller.
 */
class VerifyEmailWebhookSignature
{
    /**
     * The header carrying the payload's hex HMAC-SHA256 signature.
     */
    protected const SIGNATURE_HEADER = 'X-Signature';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.email_webhook.secret');
        $signature = (string) $request->header(self::SIGNATURE_HEADER, '');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        abort_if(
            $secret === '' || $signature === '' || ! hash_equals($expected, $signature),
            403,
            'Invalid webhook signature.',
        );

        return $next($request);
    }
}
