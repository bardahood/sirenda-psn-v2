# Uji Akurasi Indikator

Kriteria selesai v1 mensyaratkan angka dashboard **identik** dengan query SQL acuan yang ditulis manual.

## Metode

- **Query acuan**: `app/Support/Akurasi/KueriAcuan.php`. Query ini tidak memakai kode agregasi aplikasi:

  | Indikator | Sumber query acuan | Mengapa independen |
  |---|---|---|
  | K1, K2, P1, P6, P7 | Tabel inti (`psn`, `psn_lokasi`, `psn_sumber_dana`, `ref_status_psn`) | Menguji snapshot sekaligus agregasi |
  | K3, P2, P5 | Agregat SQL atas `snapshot_psn` | Agregasi aplikasi dilakukan di PHP |
  | K4 | Komponennya (`status_progres`, `risiko_level_maks`) | Bukan kolom `is_kritis` |
  | P3, P4 | Tingkat KP/RO (`snapshot_kegiatan`) | Aplikasi menjumlah kolom tingkat PSN |

  Filter diterjemahkan ke klausa `EXISTS` atas tabel inti, bukan `JSON_CONTAINS` seperti di aplikasi.
- **Pembanding**: `App\Support\Akurasi\UjiAkurasi` menjalankan 10 kombinasi filter bawaan: tanpa filter, tiap dimensi dengan nilai terbanyak, PSN/PKPN, skema dana tunggal dan ganda, status, dan gabungan. Kombinasi tambahan bisa diberikan. Hitungan wajib sama persis; nilai berdesimal boleh berbeda paling banyak 0,05 (pembulatan 1 desimal).

## Cara menjalankan

```bash
php artisan psn:uji-akurasi                      # cut-off terbit terbaru
php artisan psn:uji-akurasi 2026-08 --filter="prov=31&kat=psn" --hanya-selisih
```

Jalankan segera setelah snapshot dibangun. Acuan yang berbasis tabel inti mencerminkan kondisi terkini, sehingga perubahan data sesudah snapshot akan tampak sebagai selisih.

Uji otomatis: `tests/Feature/AkurasiIndikatorTest.php`. Datanya sengaja memuat kasus tepi:
- nilai seri di batas 8 teratas;
- PSN multi-lokasi dan multi-sumber dana;
- investasi anomali;
- PSN keluar dan PSN terhapus;
- dua cut-off.

Dengan `LEGACY_TEST=1`, uji yang sama juga dijalankan pada data riil hasil impor.

## Hasil (data riil, impor 7 Oktober 2026, cut-off 2026-09)

`110 pemeriksaan, 110 cocok, 0 selisih` (11 indikator × 10 kombinasi filter).

**Temuan dan perbaikan.** Uji pertama menemukan 1 selisih: P1 dengan filter `dana=apbn`. Dua klaster sama-sama berisi 3 PSN di peringkat ke-8, dan aplikasi tidak menetapkan urutan untuk nilai seri. Akibatnya isi "8 teratas" tidak deterministik, dan tautan URL yang sama bisa menampilkan hasil berbeda.

Perbaikan: semua distribusi kini diurutkan berdasarkan jumlah menurun, lalu nama menaik (`DashboardService::urutDeterministik`). Perbaikan ini dikunci oleh `test_urutan_seri_deterministik_untuk_url_yang_sama`.
