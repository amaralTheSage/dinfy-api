<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAssistantSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedSecret = (string) config('assistant.secret', '');
        $headerName = (string) config('assistant.secret_header', 'X-Assistant-Secret');

        if ($expectedSecret === '') {
            return response()->json([
                'message' => 'Assistant webhook secret is not configured.',
            ], 500);
        }

        $providedSecret = (string) $request->header($headerName, '');

        if ($providedSecret === '' || !hash_equals($expectedSecret, $providedSecret)) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 401);
        }

        return $next($request);
    }
}
