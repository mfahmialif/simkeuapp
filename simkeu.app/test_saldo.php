<?php
try {
    $controller = app()->make(\App\Http\Controllers\Api\Admin\SaldoController::class);
    $request = request();
    echo json_encode($controller->index($request)->getData(true)['data'][0] ?? null, JSON_PRETTY_PRINT);
} catch(\Exception $e) {
    echo $e->getMessage();
}
