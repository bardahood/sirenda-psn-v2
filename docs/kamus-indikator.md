# Kamus Indikator Dashboard Monev PSN

Semua ambang dan bobot dibaca dari `config/psn_dashboard.php`. Angka per cut-off selalu dibaca dari tabel `snapshot_*` (dibekukan oleh `php artisan psn:snapshot {YYYY-MM}`). Pembanding "Δ" = cut-off terbit sebelumnya.

## Status otomatis

| Status | Aturan | Warna |
|---|---|---|
| On Track | deviasi (realisasi − rencana) > −5 pp | `green-600` |
| Berisiko | −20 pp ≤ deviasi ≤ −5 pp | `amber-600` |
| Terlambat | deviasi < −20 pp | `red-600` |
| Tanpa data | tidak ada pembaruan realisasi > 35 hari sebelum tanggal cut-off | `slate-500` |

Batas dibaca persis: deviasi −5 = Berisiko; deviasi −20 = Berisiko; deviasi −20,01 = Terlambat.

Level risiko residual: skor = kemungkinan (1–5) × dampak (1–5). Rendah 1–4, Sedang 5–9, Tinggi 10–16, Sangat Tinggi 17–25. Risiko yang hanya memiliki label (data lama) memakai label tersebut apa adanya.

Status selalu ditampilkan sebagai badge berlabel, tidak pernah warna saja.

## Kartu KPI

| Kode | Nama | Rumus | Sumber | Δ |
|---|---|---|---|---|
| K1 | Total PSN | COUNT PSN aktif (`ref_status_psn.is_aktif`, tidak terhapus) sesuai filter | `snapshot_psn.is_aktif` | % (naik = hijau) |
| K2 | Total Investasi | Σ `nilai_investasi_rp` PSN aktif, tanpa `investasi_anomali`, dalam Rp triliun 1 desimal | `snapshot_psn.nilai_investasi_rp` | % |
| K3 | Progres Fisik Rata-rata | tertimbang: Σ(progres × investasi) ÷ Σ investasi; sederhana: rata-rata progres (`k3_metode`) | `snapshot_psn.progres_realisasi_persen` | pp |
| K4 | Risiko Kritis | COUNT PSN dengan status Terlambat ATAU risiko residual ≥ Tinggi | `snapshot_psn.is_kritis` | % (**turun = hijau**) |

## Panel

| Kode | Nama | Rumus | Sumber |
|---|---|---|---|
| P1 | Distribusi Klaster | COUNT PSN per klaster; 8 teratas (`p1_top_n`) + "Lainnya" | `snapshot_psn.klaster_id` |
| P2 | Progres Fisik vs Target | realisasi tertimbang vs rencana s.d. bulan cut-off | `snapshot_psn` |
| P3 | Realisasi Anggaran | Σ realisasi ÷ Σ pagu tahun berjalan | `snapshot_psn.pagu_rp`, `realisasi_anggaran_rp` |
| P4 | RO Tercapai | COUNT RO tercapai ÷ COUNT RO tahun berjalan | `snapshot_kegiatan.is_tercapai` |
| P5 | Tren Bulanan | rencana vs realisasi kumulatif Jan–Des per cut-off terbit | `snapshot_psn` lintas cut-off |
| P6 | Sumber Pendanaan | Σ investasi per skema (APBN, APBD, KPBU, Lainnya); PSN multi-sumber tanpa rincian nilai dihitung penuh di tiap skema, dan ini dijelaskan di tooltip | `psn_sumber_dana`, `skema_dana` |
| P7 | Peta Sebaran | COUNT PSN per provinsi; PSN multi-lokasi dihitung di tiap provinsi (tooltip) | `snapshot_psn.provinsi_kode` |
| P8 | Kontribusi Trisula | **Metodologi belum ditetapkan**: komponen placeholder dengan penanda | `trisula`, `trisula_target` |

## Komponen lain

- **RO Critical Path Berisiko:** 10 KP/RO dengan `is_critical_path` dan deviasi terburuk (`snapshot_kegiatan`).
- **Tahapan Status:** COUNT per `ref_status_psn.tahap` (Perencanaan, Transaksi, Konstruksi, Operasi/Selesai).
- **Aktivitas Terbaru:** `audit_log` terbaru sesuai cakupan akses pengguna.

## Kualitas data

- **Kelengkapan** = field wajib terisi ÷ field wajib × 100, per bagian profil (`field_wajib`).
- **Tepat waktu** = `pengisian_psn.diajukan_at` ≤ `periode_cutoff.tanggal_cutoff`.
- **Menunggu verifikasi** = `pengisian_psn.status = DIAJUKAN`.

## Penilaian usulan PSN

- **Gate** KU1–KU3 (Ya/Tidak): satu "Tidak" → rekomendasi **DITOLAK** berapa pun nilainya.
- **Skor komponen** = Σ skor ÷ (3 × jumlah sub-kriteria yang berlaku) × 100.
- **Nilai akhir** = 0,35·Pendukung + 0,35·Kesiapan + 0,15·Lokasi + 0,15·Trisula.
- Ambang Direkomendasikan/Dipertimbangkan: **belum ditetapkan** (`penilaian.ambang`).
