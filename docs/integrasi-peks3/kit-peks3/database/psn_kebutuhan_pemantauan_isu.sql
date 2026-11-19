-- Sinkron dua arah Dashboard -> SIRENDA PEKS3 (docs/integrasi-peks3.md §9.5).
-- Kolom pemantauan isu (psn_kebutuhan jenis A) yang diisi Operator di menu Pengisian Dashboard.
-- Jalankan SEKALI di basis data SIRENDA PEKS3 (sirendapsn_bappenas). Hanya menambah kolom (aman, dapat diulang tidak).
ALTER TABLE psn_kebutuhan
    ADD COLUMN status VARCHAR(20) NULL COMMENT 'TERBUKA|PROSES|SELESAI (isu jenis A)' AFTER file_bukti,
    ADD COLUMN pic_nama VARCHAR(255) NULL AFTER status,
    ADD COLUMN tenggat DATE NULL AFTER pic_nama,
    ADD COLUMN tindak_lanjut TEXT NULL AFTER tenggat,
    ADD COLUMN tanggal_selesai DATE NULL AFTER tindak_lanjut;
