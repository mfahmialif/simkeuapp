<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keuangan_api', function (Blueprint $table) {
            $table->id();
            $table->string('uri');
            $table->string('type');
            $table->string('token');
            $table->string('userkey')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_api');
    }
};
