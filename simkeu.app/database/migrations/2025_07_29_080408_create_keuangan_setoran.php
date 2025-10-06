<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keuangan_setoran', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');             // date, not null
            $table->integer('user_id');          // int, not null
            $table->double('jumlah');            // double, not null
            $table->integer('validator_id')->nullable(); // int, null
            $table->string('status')->nullable();        // varchar(255), null
            $table->string('kategori')->nullable();      // varchar(255), null
            $table->text('keterangan')->nullable();      // text, null
            $table->timestamp('created_at')->nullable(); // timestamp, null
            $table->timestamp('updated_at')->nullable(); // timestamp, null
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_setoran');
    }
};
