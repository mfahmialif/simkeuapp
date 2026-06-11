<?php

namespace Tests\Feature;

use App\Http\Middleware\ValidateBsiCallback;
use App\Services\BsiPaymentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BsiCallbackMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'simkeu.bsi_callback_secret' => 'callback-secret',
            'simkeu.bsi_callback_tolerance' => 300,
        ]);

        Carbon::setTestNow('2026-06-11 10:00:00');

        Route::post('/_test/bsi-callback', fn () => response()->json(['status' => true]))
            ->middleware(ValidateBsiCallback::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_accepts_a_valid_signed_callback(): void
    {
        $timestamp = (string) now()->timestamp;
        $body = json_encode([
            'callback_id' => 'CB-1',
            'request_id' => 'REQ-1',
            'status' => 'paid',
        ], JSON_UNESCAPED_SLASHES);

        $this->call(
            'POST',
            '/_test/bsi-callback',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_BSI_TIMESTAMP' => $timestamp,
                'HTTP_X_BSI_SIGNATURE' => BsiPaymentService::signature(
                    $timestamp,
                    $body,
                    'callback-secret'
                ),
            ],
            $body
        )->assertOk()->assertJson(['status' => true]);
    }

    public function test_it_rejects_an_expired_callback_timestamp(): void
    {
        $timestamp = (string) now()->subMinutes(6)->timestamp;
        $body = '{"callback_id":"CB-1","status":"paid"}';

        $this->call(
            'POST',
            '/_test/bsi-callback',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_BSI_TIMESTAMP' => $timestamp,
                'HTTP_X_BSI_SIGNATURE' => BsiPaymentService::signature(
                    $timestamp,
                    $body,
                    'callback-secret'
                ),
            ],
            $body
        )->assertUnauthorized();
    }

    public function test_it_rejects_a_signature_for_a_different_body(): void
    {
        $timestamp = (string) now()->timestamp;
        $body = '{"callback_id":"CB-1","status":"paid"}';

        $this->call(
            'POST',
            '/_test/bsi-callback',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_BSI_TIMESTAMP' => $timestamp,
                'HTTP_X_BSI_SIGNATURE' => BsiPaymentService::signature(
                    $timestamp,
                    '{"callback_id":"CB-1","status":"failed"}',
                    'callback-secret'
                ),
            ],
            $body
        )->assertUnauthorized();
    }
}
