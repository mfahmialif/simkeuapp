<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::first();
\Illuminate\Support\Facades\Auth::login($user);

$request = Illuminate\Http\Request::create('/api/admin/pemasukan/mahasiswa/laporan/pemasukan-uii-dalwa', 'GET', ['mode'=>'bulanan', 'bulan'=>'2024-05', 'jenis_kelamin'=>'%']);
$controller = app()->make(\App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\LaporanController::class);
$response = $controller->pemasukanUiiDalwa($request);
$data = json_decode($response->getContent(), true);

echo "Status: " . ($data['status'] ? 'true' : 'false') . "\n";
if (!$data['status']) {
    echo "Message: " . $data['message'] . "\n";
} else {
    echo "Data count: " . count($data['data']) . "\n";
    $hasSp = false;
    foreach ($data['data'] as $row) {
        if ($row['kategori'] === 'SEMESTER PENDEK') {
            $hasSp = true;
            echo "SP Tunai: " . $row['tunai'] . "\n";
            echo "SP Transfer: " . $row['transfer'] . "\n";
            echo "SP Yayasan: " . $row['yayasan'] . "\n";
        }
    }
    echo "Has SP category in data? " . ($hasSp ? "Yes" : "No") . "\n";
}
