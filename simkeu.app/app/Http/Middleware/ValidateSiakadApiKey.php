<?php

namespace App\Http\Middleware;

use App\Services\BsiSettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateSiakadApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $settings = app(BsiSettingsService::class)->settings();

        if (blank($settings->siakad_api_key_hash)) {
            return response()->json([
                'status' => false,
                'message' => 'API key SIAKAD belum dibuat di SIMKEU. Silakan buat API Key di menu Pengaturan -> API.',
            ], 500);
        }

        $provided = $request->header('x-siakad-api-key')
            ?? $request->header('apikey')
            ?? $request->header('x-api-key')
            ?? $request->bearerToken();

        if (! $provided || ! hash_equals($settings->siakad_api_key_hash, hash('sha256', $provided))) {
            return response()->json([
                'status' => false,
                'message' => 'API key SIAKAD tidak valid atau belum dikirimkan pada header (X-SIAKAD-API-KEY / apikey).',
            ], 401);
        }

        return $next($request);
    }
}
