<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('keuangan_pengeluaran_transportasi_rekap')) {
            Schema::create('keuangan_pengeluaran_transportasi_rekap', function (Blueprint $table) {
                $table->id();
                $table->string('nama')->unique();
                $table->date('bulan_tahun')->nullable()->index('idx_transportasi_rekap_bulan_tahun');
                $table->date('tanggal_rekap')->nullable()->index('idx_transportasi_rekap_tanggal');
                $table->unsignedBigInteger('jumlah_sementara')->nullable();
                $table->foreignId('petugas_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('keterangan')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('keuangan_pengeluaran_transportasi')) {
            Schema::create('keuangan_pengeluaran_transportasi', function (Blueprint $table) {
                $table->id();
                $table->date('tanggal');
                $table->unsignedBigInteger('rekap_id')->nullable();
                $table->foreignId('petugas_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('prioritas', 20);
                $table->string('nama_kegiatan');
                $table->unsignedBigInteger('nominal')->default(0);
                $table->unsignedInteger('volume')->nullable();
                $table->string('satuan')->nullable();
                $table->unsignedBigInteger('total')->default(0);
                $table->string('jenis_pembayaran', 50);
                $table->string('bukti_transfer')->nullable();
                $table->json('lampiran')->nullable();
                $table->text('keterangan')->nullable();
                $table->timestamps();

                $table->index('rekap_id', 'idx_pengeluaran_transportasi_rekap_id');
                $table->index(['rekap_id', 'tanggal'], 'idx_pengeluaran_transportasi_rekap_tanggal');
                $table->index(['rekap_id', 'total'], 'idx_pengeluaran_transportasi_rekap_total');
            });
        }

        if (! Schema::hasTable('keuangan_pengeluaran_transportasi_lpj')) {
            Schema::create('keuangan_pengeluaran_transportasi_lpj', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('rab_detail_id')->nullable();
                $table->date('tanggal');
                $table->unsignedBigInteger('rekap_id')->nullable();
                $table->foreignId('petugas_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('prioritas', 20);
                $table->string('nama_kegiatan');
                $table->unsignedBigInteger('nominal')->default(0);
                $table->unsignedInteger('volume')->nullable();
                $table->string('satuan')->nullable();
                $table->unsignedBigInteger('total')->default(0);
                $table->string('jenis_pembayaran', 50);
                $table->string('bukti_transfer')->nullable();
                $table->json('lampiran')->nullable();
                $table->text('keterangan')->nullable();
                $table->timestamps();

                $table->index('rab_detail_id', 'idx_pengeluaran_transportasi_lpj_rab_id');
                $table->index('rekap_id', 'idx_pengeluaran_transportasi_lpj_rekap_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_pengeluaran_transportasi_lpj');
        Schema::dropIfExists('keuangan_pengeluaran_transportasi');
        Schema::dropIfExists('keuangan_pengeluaran_transportasi_rekap');
    }
};
