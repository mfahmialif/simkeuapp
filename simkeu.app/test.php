<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$sample = DB::table('keuangan_pembayaran')
    ->join('keuangan_tagihan', 'keuangan_tagihan.id', '=', 'keuangan_pembayaran.tagihan_id')
    ->leftJoin('keuangan_jenis_pembayaran_detail', 'keuangan_jenis_pembayaran_detail.pembayaran_id', '=', 'keuangan_pembayaran.id')
    ->leftJoin('keuangan_jenis_pembayaran', 'keuangan_jenis_pembayaran.id', '=', 'keuangan_jenis_pembayaran_detail.jenis_pembayaran_id')
    ->select(
        DB::raw("keuangan_tagihan.nama as category_name"),
        DB::raw("COALESCE(UPPER(keuangan_jenis_pembayaran.nama), 'LAINNYA') as payment_method"),
        DB::raw('COALESCE(SUM(keuangan_pembayaran.jumlah), 0) as keseluruhan')
    )
    ->groupBy('keuangan_tagihan.nama', DB::raw("COALESCE(UPPER(keuangan_jenis_pembayaran.nama), 'LAINNYA')"))
    ->limit(5)
    ->get();
echo json_encode($sample);
