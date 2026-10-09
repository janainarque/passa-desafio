<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\Response;

final class VerifyNetworkSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $timestamp = $request->header('X-Network-Timestamp');
        $signature = $request->header('X-Network-Signature');

        if ($timestamp === null || $signature === null) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        if (! ctype_digit($timestamp)) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        if (abs(Date::now()->getTimestamp() - (int) $timestamp) > 300) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $secret = config('services.network.secret');

        if (! is_string($secret) || $secret === '') {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $payload = $timestamp.'.'.$request->getContent();

        $expectedSignature = 'sha256='.hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expectedSignature, $signature)) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        return $next($request);
    }
}
