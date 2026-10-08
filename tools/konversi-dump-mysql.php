<?php

/**
 * Mengonversi dump SIRENDA lama (phpMyAdmin, MariaDB 10.x) agar dapat diimpor ke MySQL 8:
 *  1. `DEFAULT uuid()`  -> `DEFAULT (uuid())`   (MySQL mewajibkan default ekspresi dalam kurung)
 *  2. `DEFINER=`user`@`host` SQL SECURITY DEFINER` -> `SQL SECURITY INVOKER` (pengguna server lama tidak ada)
 *  3. Tabel pengganti view yang kosong (`CREATE TABLE x (\n);`) dihapus.
 * View yang memang rusak di server asal tetap gagal dibuat; tidak dipakai ETL.
 *
 * Pemakaian: php tools/konversi-dump-mysql.php <dump-asli.sql> [keluaran.sql]
 */
[$_, $masuk, $keluar] = $argv + [null, null, null];
if (! $masuk || ! is_file($masuk)) {
    fwrite(STDERR, "Pemakaian: php tools/konversi-dump-mysql.php <dump-asli.sql> [keluaran.sql]\n");
    exit(1);
}
$keluar ??= preg_replace('/\.sql$/i', '', $masuk).'_mysql8.sql';

$sql = file_get_contents($masuk);
$sql = str_replace("\r\n", "\n", $sql);
$sql = preg_replace('/DEFAULT uuid\(\)/i', 'DEFAULT (uuid())', $sql, -1, $nUuid);
$sql = preg_replace('/ DEFINER=`[^`]+`@`[^`]+` SQL SECURITY DEFINER/', ' SQL SECURITY INVOKER', $sql, -1, $nDefiner);
$sql = preg_replace('/CREATE TABLE `[^`]+` \(\n\);\n/', '', $sql, -1, $nStub);
file_put_contents($keluar, $sql);

echo "Selesai: {$keluar}\n- default uuid(): {$nUuid}\n- definer view: {$nDefiner}\n- tabel stub kosong dihapus: {$nStub}\n";
