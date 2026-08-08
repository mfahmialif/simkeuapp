<?php

namespace App\Http\Middleware;

use App\Services\BsiSettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateBsiSiakadApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $settings = app(BsiSettingsService::class)->settings();

        if (! $settings->enabled) {
            return response()->json([
                'status' => false,
                'message' => 'Integrasi BSI belum diaktifkan.',
            ], 503);
        }

        if (blank($settings->siakad_api_key_hash)) {
            return response()->json([
                'status' => false,
                'message' => 'API key SIAKAD belum dibuat.',
            ], 500);
        }

        $provided = $request->header('x-siakad-api-key');

        if (! $provided || ! hash_equals($settings->siakad_api_key_hash, hash('sha256', $provided))) {
            return response()->json([
                'status' => false,
                'message' => 'API key SIAKAD tidak valid.',
            ], 401);
        }

        return $next($request);
    }
}
