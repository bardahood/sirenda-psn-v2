<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Direktorat pengampu usulan -- dasar pembatasan "¹" (input/verifikasi terbatas
 * pada usulan sektor sendiri) pada halaman Perencanaan. Aditif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usulan_psn', function (Blueprint $table) {
            $table->foreignId('unit_kerja_id')->nullable()->after('klaster_id')->constrained('ref_unit_kerja')->nullOnDelete()->comment('direktorat pengampu usulan');
        });
    }

    public function down(): void
    {
        Schema::table('usulan_psn', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_kerja_id');
        });
    }
};
