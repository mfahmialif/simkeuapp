<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idn_pembayaran', function (Blueprint $table) {
            $table->id(); // PK

            $table->integer('tagihan_id')->nullable();
            $table->string('bill_id', 200)->nullable();
            $table->string('nama_tagihan', 60)->nullable();

            // default 0 dan nullable mengikuti tabel lama
            $table->decimal('jumlah_tagihan', 12, 0)->nullable()->default(0);

            $table->string('merchant_name', 255)->nullable();
            $table->string('biller_name', 255)->nullable();
            $table->string('biller_code', 255)->nullable();
            $table->string('bill_name', 255)->nullable();

            $table->string('bill_key', 20); // NOT NULL

            $table->decimal('total_bill_amount', 12, 0)->nullable()->default(0);
            $table->string('jenjang', 50)->nullable();
            $table->string('bill_quantity', 255)->nullable();
            $table->decimal('admin_fee', 10, 0)->nullable()->default(0);

            $table->string('ref_number', 200)->nullable();
            $table->string('merchant_ref_number', 200)->nullable();

            $table->dateTime('paid_date')->nullable();

            // created_at: NULL default, ON UPDATE CURRENT_TIMESTAMP
            $table->timestamp('created_at')->nullable()->useCurrentOnUpdate();

            $table->integer('is_delete')->nullable();
            $table->string('th_akademik_id', 11)->nullable();
            $table->string('prodi_id', 10)->nullable()->default(null);
            $table->integer('kelas_id')->nullable();

            // Index yang kemungkinan dibutuhkan
            $table->index('bill_key');
            $table->index('ref_number');
            $table->index('merchant_ref_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idn_pembayaran');
    }
};
