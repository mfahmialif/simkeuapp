<?php

return [
    'simkeu_api_key' => env('SIMKEU_API_KEY'),
    'simkeu_url' => env('SIMKEU_URL'),
    'simkeuv2_api_key' => env('SIMKEUV2_API_KEY'),
    'bsi_callback_secret' => env('BSI_CALLBACK_SECRET'),
    'bsi_callback_tolerance' => (int) env('BSI_CALLBACK_TOLERANCE', 300),
    'wisuda_api_key' => env('WISUDA_API_KEY'),
    'wisuda_url' => env('WISUDA_URL', 'https://wisuda.uiidalwa.web.id/api/'),
];
