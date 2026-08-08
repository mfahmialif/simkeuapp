<?php

namespace Tests\Feature;

use Tests\TestCase;

class BsiEndpointBrowserAccessTest extends TestCase
{
    public function test_browser_get_to_payment_endpoint_returns_bi_snap_unauthorized_json(): void
    {
        $this->get('/api/bpi-bi-snap/payment')
            ->assertUnauthorized()
            ->assertExactJson([
                'responseCode' => '4017300',
                'responseMessage' => 'Unauthorized Client',
            ]);
    }
}
