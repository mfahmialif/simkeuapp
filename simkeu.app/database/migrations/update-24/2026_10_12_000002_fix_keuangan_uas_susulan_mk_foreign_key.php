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
        Schema::table('keuangan_uas_susulan_mk', function (Blueprint $table) {
            // Drop erroneous self-referencing foreign key constraint
            $table->dropForeign('keuangan_uas_susulan_mk_uas_susulan_id_foreign');

            // Re-create foreign key correctly referencing parent table keuangan_uas_susulan
            $table->foreign('uas_susulan_id')
                ->references('id')
                ->on('keuangan_uas_susulan')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('keuangan_uas_susulan_mk', function (Blueprint $table) {
            $table->dropForeign(['uas_susulan_id']);

            $table->foreign('uas_susulan_id')
                ->references('id')
                ->on('keuangan_uas_susulan_mk');
        });
    }
};
