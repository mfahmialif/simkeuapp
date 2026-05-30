<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateSimkeuv2ApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = config('simkeu.simkeuv2_api_key');

        if (blank($configuredKey)) {
            return response()->json([
                'status'  => false,
                'message' => 'SIMKEUV2_API_KEY belum dikonfigurasi.',
            ], 500);
        }

        $providedKey = $request->header('apikey')
            ?? $request->header('x-api-key')
            ?? $request->header('x-simkeuv2-api-key')
            ?? $request->header('simkeuv2-api-key');

        if (! $providedKey || ! hash_equals((string) $configuredKey, (string) $providedKey)) {
            return response()->json([
                'status'  => false,
                'message' => 'API key tidak valid.',
            ], 401);
        }

        return $next($request);
    }
}
