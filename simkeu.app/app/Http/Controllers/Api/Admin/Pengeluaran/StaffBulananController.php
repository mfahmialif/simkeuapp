<?php

namespace App\Http\Controllers\Api\Admin\Pengeluaran;

use App\Models\KeuanganPengeluaranStaffBulananRekap;

class StaffBulananController extends DosenBulananController
{
    protected const PEGAWAI_TIPE = 'staff';
    protected const MODULE_NAME = 'Barokah Staff Bulanan';
    protected const PEGAWAI_LABEL = 'Staff';
    protected const REQUIRE_PERIODE = true;
    protected const REKAP_MODEL = KeuanganPengeluaranStaffBulananRekap::class;
}
