<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('keuangan_pembayaran_semester_pendek', function (Blueprint $table) {
            $table->foreignId('jenis_pembayaran_id')->nullable()->constrained('keuangan_jenis_pembayaran')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('keuangan_pembayaran_semester_pendek', function (Blueprint $table) {
            $table->dropForeign(['jenis_pembayaran_id']);
            $table->dropColumn('jenis_pembayaran_id');
        });
    }
};
