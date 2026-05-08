<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::first();
\Illuminate\Support\Facades\Auth::login($user);

$request = Illuminate\Http\Request::create('/api/admin/pemasukan/mahasiswa/laporan/pemasukan-tunai-harian', 'GET', ['mode'=>'bulanan', 'bulan'=>'2024-05', 'jenis_kelamin'=>'%']);
$controller = app()->make(\App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\LaporanController::class);
$response = $controller->pemasukanTunaiHarian($request);
$data = json_decode($response->getContent(), true);

echo "Status: " . ($data['status'] ? 'true' : 'false') . "\n";
if (!$data['status']) {
    echo "Message: " . $data['message'] . "\n";
} else {
    echo "Data count: " . count($data['data']) . "\n";
    $hasSp = false;
    foreach ($data['data'][0] as $key => $val) {
        if ($key === 'sp') $hasSp = true;
    }
    echo "Has SP column in data? " . ($hasSp ? "Yes" : "No") . "\n";
    
    // Check total SP
    echo "Total SP: " . $data['totals']['sp'] . "\n";
}
