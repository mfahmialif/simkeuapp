<?php

namespace Tests\Unit;

use App\Services\BsiSnapService;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class BsiSnapServiceTest extends TestCase
{
    public function test_it_generates_the_bi_snap_transaction_signature(): void
    {
        $body = '{"customerNo":"123456"}';
        $bodyHash = strtolower(hash('sha256', $body));
        $expected = base64_encode(hash_hmac(
            'sha512',
            'POST:/api/bpi-bi-snap/inquiry:token-123:'.$bodyHash.':2026-08-08T10:00:00+07:00',
            'secret-456',
            true
        ));

        $signature = BsiSnapService::generateTransactionSignature(
            'post',
            '/api/bpi-bi-snap/inquiry',
            'token-123',
            $body,
            '2026-08-08T10:00:00+07:00',
            'secret-456'
        );

        $this->assertSame($expected, $signature);
    }

    public function test_it_minifies_json_without_changing_value_types(): void
    {
        $request = Request::create(
            '/api/bpi-bi-snap/inquiry',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            "{\n  \"customerNo\": \"001234\",\n  \"amount\": { \"value\": 1000.00 }\n}"
        );
        $service = (new ReflectionClass(BsiSnapService::class))->newInstanceWithoutConstructor();

        $this->assertSame(
            '{"customerNo":"001234","amount":{"value":1000}}',
            $service->minifiedBody($request)
        );
    }

    public function test_it_rejects_invalid_json_for_signature_input(): void
    {
        $request = Request::create('/', 'POST', [], [], [], [], '{invalid-json}');
        $service = (new ReflectionClass(BsiSnapService::class))->newInstanceWithoutConstructor();

        $this->assertSame('', $service->minifiedBody($request));
    }
}
