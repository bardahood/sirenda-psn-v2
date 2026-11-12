<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dimensi filter "Sumber PSN" (RKP / Permenko) pada snapshot per cut-off (aditif).
 * Snapshot yang sudah ada diisi dari psn.sumber_input dengan pemetaan config psn_dashboard.sumber_psn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('snapshot_psn', function (Blueprint $table) {
            $table->string('sumber', 10)->nullable()->after('kategori')->comment('RKP|PERMENKO (dari psn.sumber_input)');
            $table->index(['periode_cutoff_id', 'sumber']);
        });

        foreach (config('psn_dashboard.sumber_psn') as $kode => $sumber) {
            DB::table('snapshot_psn')->join('psn', 'psn.id', '=', 'snapshot_psn.psn_id')
                ->where('psn.sumber_input', $kode)->update(['snapshot_psn.sumber' => $sumber]);
        }
    }

    public function down(): void
    {
        Schema::table('snapshot_psn', function (Blueprint $table) {
            $table->dropIndex(['periode_cutoff_id', 'sumber']);
            $table->dropColumn('sumber');
        });
    }
};
