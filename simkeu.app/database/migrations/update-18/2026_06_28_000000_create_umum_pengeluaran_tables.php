<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('keuangan_pengeluaran_umum_rekap')) {
            Schema::create('keuangan_pengeluaran_umum_rekap', function (Blueprint $table) {
                $table->id();
                $table->string('nama')->unique();
                $table->date('bulan_tahun')->nullable()->index('idx_umum_rekap_bulan_tahun');
                $table->date('tanggal_rekap')->nullable()->index('idx_umum_rekap_tanggal');
                $table->date('tanggal_pencairan')->nullable()->index('idx_umum_rekap_tgl_cair');
                $table->boolean('cetak_rab')->default(false)->index('idx_umum_rekap_cetak_rab');
                $table->unsignedBigInteger('jumlah_sementara')->nullable();
                $table->foreignId('petugas_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('keterangan')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('keuangan_pengeluaran_umum')) {
            Schema::create('keuangan_pengeluaran_umum', function (Blueprint $table) {
                $table->id();
                $table->date('tanggal')->index('idx_pengeluaran_umum_tanggal');
                $table->unsignedBigInteger('rekap_id')->nullable();
                $table->foreignId('petugas_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('nama_kegiatan');
                $table->unsignedBigInteger('nominal')->default(0);
                $table->unsignedBigInteger('total')->default(0);
                $table->string('jenis_pembayaran', 50);
                $table->string('bukti_transfer')->nullable();
                $table->json('lampiran')->nullable();
                $table->text('keterangan')->nullable();
                $table->timestamps();

                $table->index('rekap_id', 'idx_pengeluaran_umum_rekap_id');
                $table->index(['rekap_id', 'tanggal'], 'idx_pengeluaran_umum_rekap_tanggal');
                $table->index(['rekap_id', 'total'], 'idx_pengeluaran_umum_rekap_total');
                $table->index(['petugas_id', 'tanggal', 'rekap_id', 'total'], 'idx_kpu_petugas_stats');
            });
        }

        if (! Schema::hasTable('keuangan_pengeluaran_umum_lpj')) {
            Schema::create('keuangan_pengeluaran_umum_lpj', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('rab_detail_id')->nullable();
                $table->date('tanggal');
                $table->unsignedBigInteger('rekap_id')->nullable();
                $table->foreignId('petugas_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('nama_kegiatan');
                $table->unsignedBigInteger('nominal')->default(0);
                $table->unsignedBigInteger('total')->default(0);
                $table->string('jenis_pembayaran', 50);
                $table->string('bukti_transfer')->nullable();
                $table->json('lampiran')->nullable();
                $table->text('keterangan')->nullable();
                $table->timestamps();

                $table->index('rab_detail_id', 'idx_pengeluaran_umum_lpj_rab_id');
                $table->index('rekap_id', 'idx_pengeluaran_umum_lpj_rekap_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_pengeluaran_umum_lpj');
        Schema::dropIfExists('keuangan_pengeluaran_umum');
        Schema::dropIfExists('keuangan_pengeluaran_umum_rekap');
    }
};
