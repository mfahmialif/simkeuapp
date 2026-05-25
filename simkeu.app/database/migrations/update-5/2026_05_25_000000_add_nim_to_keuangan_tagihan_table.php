<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('keuangan_tagihan', 'nim')) {
            Schema::table('keuangan_tagihan', function (Blueprint $table) {
                $table->string('nim')->nullable()->after('id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('keuangan_tagihan', 'nim')) {
            Schema::table('keuangan_tagihan', function (Blueprint $table) {
                $table->dropIndex(['nim']);
                $table->dropColumn('nim');
            });
        }
    }
};
