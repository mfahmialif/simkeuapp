<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$caseExpr = "CASE
                WHEN keuangan_tagihan.nama LIKE '%SPP%' THEN 'SPP'
                WHEN keuangan_tagihan.nama LIKE '%regis%' OR keuangan_tagihan.nama LIKE '%daftar%' THEN 'Registrasi'
                WHEN keuangan_tagihan.nama LIKE '%UAS%' THEN 'UAS'
                WHEN keuangan_tagihan.nama LIKE '%KKN%' OR keuangan_tagihan.nama LIKE '%PPL%' OR keuangan_tagihan.nama LIKE '%PKL%' THEN 'KKN / PPL / PKL'
                ELSE keuangan_tagihan.nama
            END";

$sample = DB::table('keuangan_pembayaran')
    ->join('keuangan_tagihan', 'keuangan_tagihan.id', '=', 'keuangan_pembayaran.tagihan_id')
    ->leftJoin('keuangan_jenis_pembayaran_detail', 'keuangan_jenis_pembayaran_detail.pembayaran_id', '=', 'keuangan_pembayaran.id')
    ->leftJoin('keuangan_jenis_pembayaran', 'keuangan_jenis_pembayaran.id', '=', 'keuangan_jenis_pembayaran_detail.jenis_pembayaran_id')
    ->select(
        DB::raw("{$caseExpr} as category_name"),
        DB::raw("COALESCE(UPPER(keuangan_jenis_pembayaran.nama), 'LAINNYA') as payment_method"),
        DB::raw('COALESCE(SUM(keuangan_pembayaran.jumlah), 0) as keseluruhan')
    )
    ->groupBy(DB::raw("category_name"), DB::raw("payment_method"))
    ->limit(5)
    ->get();
echo json_encode($sample);
