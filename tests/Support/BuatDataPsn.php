<?php

namespace Tests\Support;

use App\Models\Kegiatan;
use App\Models\Psn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pembuat data uji ringkas di atas referensi hasil ReferensiSeeder.
 */
trait BuatDataPsn
{
    protected function ref(string $tabel, string $kode): int
    {
        return (int) DB::table($tabel)->where('kode', $kode)->value('id');
    }

    protected function buatPsn(array $atribut = [], array $unitKode = ['07']): Psn
    {
        $psn = Psn::withoutGlobalScopes()->create($atribut + [
            'nama' => 'PSN Uji '.Str::random(5),
            'klaster_id' => $this->ref('ref_klaster', 'R.6'),
            'status_psn_id' => $this->ref('ref_status_psn', '3'),
        ]);

        foreach ($unitKode as $kode) {
            DB::table('psn_unit_pengampu')->insert(['psn_id' => $psn->id, 'unit_kerja_id' => $this->ref('ref_unit_kerja', $kode)]);
        }

        return $psn;
    }

    protected function buatKegiatan(Psn $psn, array $target, array $atribut = []): Kegiatan
    {
        $k = Kegiatan::withoutGlobalScopes()->create($atribut + ['psn_id' => $psn->id, 'nama' => 'RO Uji']);
        foreach ($target as $t) {
            DB::table('kegiatan_target')->insert($t + ['kegiatan_id' => $k->id, 'tahun' => 2026]);
        }

        return $k;
    }

    protected function buatPengguna(string $peran, ?string $unitKode = null): User
    {
        $u = User::create([
            'username' => Str::lower(Str::random(8)), 'name' => $peran, 'email' => Str::random(8).'@uji.test',
            'password' => 'rahasia-uji', 'unit_kerja_id' => $unitKode ? $this->ref('ref_unit_kerja', $unitKode) : null,
        ]);

        return $u->assignRole($peran);
    }
}
