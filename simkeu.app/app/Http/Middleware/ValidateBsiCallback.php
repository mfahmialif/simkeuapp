<?php

namespace App\Http\Middleware;

use App\Services\BsiPaymentService;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateBsiCallback
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('simkeu.bsi_callback_secret');
        if ($secret === '') {
            return response()->json([
                'status' => false,
                'message' => 'BSI_CALLBACK_SECRET belum dikonfigurasi.',
            ], 500);
        }

        $timestamp = $request->header('x-bsi-timestamp')
            ?? $request->header('x-timestamp')
            ?? $request->header('timestamp');
        $signature = $request->header('x-bsi-signature')
            ?? $request->header('x-signature')
            ?? $request->header('signature');

        if (! $timestamp || ! $signature) {
            return response()->json([
                'status' => false,
                'message' => 'Header timestamp dan signature BSI wajib diisi.',
            ], 401);
        }

        try {
            $sentAt = is_numeric($timestamp)
                ? Carbon::createFromTimestamp((int) $timestamp)
                : Carbon::parse($timestamp);
        } catch (\Throwable) {
            return response()->json([
                'status' => false,
                'message' => 'Timestamp callback BSI tidak valid.',
            ], 401);
        }

        $tolerance = max(0, (int) config('simkeu.bsi_callback_tolerance', 300));
        if (abs(now()->diffInSeconds($sentAt, false)) > $tolerance) {
            return response()->json([
                'status' => false,
                'message' => 'Timestamp callback BSI sudah kedaluwarsa.',
            ], 401);
        }

        $signature = preg_replace('/^sha256=/i', '', trim((string) $signature));
        if (! BsiPaymentService::verifySignature(
            (string) $timestamp,
            $request->getContent(),
            $signature,
            $secret
        )) {
            return response()->json([
                'status' => false,
                'message' => 'Signature callback BSI tidak valid.',
            ], 401);
        }

        return $next($request);
    }
}
