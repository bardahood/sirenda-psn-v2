# Memasang SIRENDA PSN v2 di Hosting cPanel

Panduan ini untuk hosting bersama berbasis cPanel (contoh: `server387.web-hosting.com`). Paket aplikasi disiapkan di komputer lokal (Laragon), lalu diunggah. Server tidak memerlukan Node.js, dan Composer pun tidak wajib.

Struktur akhir di server (`CPUSER` = nama pengguna cPanel):

```
/home/CPUSER/
├── sirenda/              ← seluruh aplikasi (DI LUAR public_html)
│   ├── app, config, vendor, storage, .env, ...
│   └── public/           ← satu-satunya folder yang boleh diakses web
└── public_html/          ← situs utama domain, tidak dipakai SIRENDA
```

> **Penting.** Jangan mengekstrak aplikasi ke dalam `public_html`. Bila `.env` berada di folder web, kata sandi basis data dan kunci aplikasi bisa diunduh siapa saja. Arahkan *document root* ke `sirenda/public` (langkah 3).

## 1. Siapkan paket di komputer lokal (Laragon)

```bat
cd C:\laragon\www\sirenda-psn-v2
git pull origin feature/dashboard-v2
composer install --no-dev --optimize-autoloader
npm run build
```

Kompres folder proyek menjadi `sirenda.zip`, **tanpa** item berikut:

| Jangan ikut diunggah | Alasan |
|---|---|
| `node_modules\`, `.git\`, `tests\` | Tidak diperlukan di server dan ukurannya besar |
| `.env` | Server memakai `.env` sendiri (langkah 5) |
| `storage\app\private\etl\`, `storage\logs\*.log` | Laporan ETL dan log berisi data pengguna |
| `*.sql`, dump basis data lama, `*.pem` kunci privat | Data sensitif |

Pastikan folder `public\build\` **ikut**, karena berisi hasil `npm run build`.

Setelah paket dibuat, kembalikan dependensi pengembangan di lokal dengan `composer install`.

## 2. Pilih versi PHP

Di cPanel, buka **Select PHP Version** (atau **MultiPHP Manager**):
- Versi PHP: **8.3** (minimal 8.2).
- Aktifkan ekstensi `pdo_mysql`, `mysqlnd`, `mbstring`, `intl`, `gd`, `zip`, `xml`, `dom`, `fileinfo`, `bcmath`, `curl`, `openssl`, `sodium`, dan `opcache`.
- Di tab *Options*, atur `memory_limit` = 256M, `upload_max_filesize` = 20M, `post_max_size` = 25M, dan `max_execution_time` = 120.

## 3. Buat (sub)domain dengan document root ke `public`

Buka cPanel **Domains**, lalu **Create A New Domain**:
- Domain: mis. `sirenda.ahcigars.com`.
- **Hapus centang** *Share document root*, lalu isi document root `sirenda/public`.

Setelah itu buka **SSL/TLS Status** dan jalankan **Run AutoSSL** untuk domain tersebut agar HTTPS aktif.

## 4. Unggah dan ekstrak

1. Buka **File Manager** lalu folder `/home/CPUSER/`. Buat folder `sirenda`.
2. Unggah `sirenda.zip` ke folder itu, lalu klik kanan dan pilih **Extract**.
3. Pastikan folder `storage/` dan `bootstrap/cache/` dapat ditulis (izin 755 untuk folder, 644 untuk berkas).

## 5. Basis data dan `.env`

**Buat basis data.** Buka **MySQL® Database Wizard**, lalu buat basis data (mis. `CPUSER_sirenda`) dan pengguna (mis. `CPUSER_sirenda`) dengan kata sandi kuat dan hak **ALL PRIVILEGES**.

**Pindahkan data dari Laragon.** Data sudah lengkap di lokal (hasil `legacy:import`, snapshot, akun pengguna). Ekspor dari lokal:

```bat
mysqldump -u root --single-transaction --no-tablespaces sirenda_psn_v2 > sirenda_v2.sql
php tools\konversi-dump-mysql.php sirenda_v2.sql sirenda_v2_hosting.sql
```

Konversi diperlukan karena view hasil `mysqldump` membawa `DEFINER=root@localhost`, yang ditolak hosting bersama. Skrip konversi menggantinya dengan `SQL SECURITY INVOKER`.

Impor `sirenda_v2_hosting.sql` lewat **phpMyAdmin** (pilih basis data, lalu tab *Import*). Bila berkas melebihi batas unggah, kompres dulu menjadi `.sql.gz`.

**Buat `.env`.** Di File Manager, buat berkas `/home/CPUSER/sirenda/.env`:

```ini
APP_NAME="SIRENDA PSN"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://sirenda.ahcigars.com
APP_LOCALE=id

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=CPUSER_sirenda
DB_USERNAME=CPUSER_sirenda
DB_PASSWORD=kata-sandi-basis-data

SESSION_DRIVER=database
SESSION_LIFETIME=30
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
CACHE_STORE=database
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local

# Integrasi SIF (isi setelah kredensial dari Dit. PSI diterima)
SIF_BASE_URL=
SIF_API_KEY=
SIF_CLIENT_ID=konsultan-psn-snkv93
SIF_KUNCI_PUBLIK=storage/app/private/kunci/sif-public.pem
SIF_SINKRON_OTOMATIS=false
```

Ubah izin `.env` menjadi **600**. Variabel `LEGACY_DB_*` tidak diperlukan di server.

## 6. Perintah artisan (Terminal cPanel atau SSH)

Bila menu **Terminal** tersedia di cPanel (atau melalui SSH, port 21098):

```bash
cd ~/sirenda
php artisan key:generate --force
php artisan migrate --force        # memastikan skema mutakhir; aman dijalankan setelah impor
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Bila tidak ada akses Terminal maupun SSH, buat `APP_KEY` di Laragon dengan `php artisan key:generate --show`, lalu salin hasilnya ke `.env` server. Langkah cache boleh dilewati.

Setiap kali `.env` diubah, jalankan lagi `php artisan config:cache`.

## 7. Cron (snapshot bulanan & sinkron SIF)

Buka cPanel **Cron Jobs** dan tambahkan jadwal setiap menit (`* * * * *`), atau setiap 5 menit bila hosting membatasi:

```
/usr/local/bin/php /home/CPUSER/sirenda/artisan schedule:run >> /dev/null 2>&1
```

## 8. Periksa

1. Buka `https://sirenda.ahcigars.com/login`, lalu masuk dengan akun yang ada di basis data lokal.
2. Pastikan `https://sirenda.ahcigars.com/.env` menampilkan 404 atau 403, **bukan** isi berkas.
3. Pastikan dashboard tampil dengan cut-off terbaru dan menu Perencanaan dapat dibuka.

## Mengambil data dari basis data SIRENDA lama (sirenda-psn.id)

SIRENDA v2 **tidak dapat** memakai basis data aplikasi lama sebagai basis data utamanya, karena struktur tabelnya berbeda. Basis data lama dipakai sebagai **sumber** (koneksi `legacy`), lalu datanya disalin ke basis data v2 dengan `legacy:import`. Importer hanya **membaca** basis data lama dan tidak pernah mengubahnya.

### Cara A: koneksi langsung ke server sirenda-psn.id

1. **Di server sirenda-psn.id** (cPanel milik aplikasi lama, atau minta ke pengelolanya):
   1. Buat pengguna MySQL khusus dengan hak **SELECT saja** pada basis data lama, mis. `sirendapsn_bappenas`.
   2. Di **Remote MySQL**, tambahkan alamat IP server hosting v2 (lihat *Shared IP Address* di panel kanan cPanel hosting v2).
2. **Di `.env` hosting v2:**
   ```ini
   LEGACY_DB_HOST=sirenda-psn.id
   LEGACY_DB_PORT=3306
   LEGACY_DB_DATABASE=nama_basis_data_lama
   LEGACY_DB_USERNAME=pengguna_select_saja
   LEGACY_DB_PASSWORD=...
   LEGACY_DB_TIMEOUT=15
   # LEGACY_DB_SSL_CA=/home/CPUSER/ca.pem   (bila server lama mendukung SSL)
   ```
3. **Uji koneksi, lalu impor** dari Terminal:
   ```bash
   cd ~/sirenda
   php artisan config:cache
   php artisan db:show --database=legacy        # harus menampilkan daftar tabel lama
   php artisan legacy:import --fresh -n
   php artisan psn:snapshot 2026-08 --terbit --paksa
   php artisan psn:snapshot 2026-09 --terbit --paksa
   php artisan psn:snapshot 2026-10 --terbit --paksa
   php artisan cache:clear
   php artisan psn:uji-akurasi 2026-10          # harus 0 selisih
   ```

Bila `db:show` berhenti dengan *Connection timed out*, ada dua kemungkinan:
- port 3306 di server lama belum membuka akses untuk IP hosting v2 (Remote MySQL);
- hosting v2 memblokir koneksi keluar ke port 3306. Minta dukungan hosting membukanya, atau pakai cara B.

### Cara B: lewat berkas dump (tanpa koneksi antar server)

1. Ekspor basis data lama dari phpMyAdmin sirenda-psn.id.
2. Di hosting v2, buat basis data baru, mis. `CPUSER_sirendalama`, lalu impor dump ke sana. Bila dump dibuat dengan HeidiSQL atau memuat `CREATE DATABASE`/`USE`, konversi dulu:
   ```
   php tools/konversi-dump-mysql.php dump.sql dump_hosting.sql --database=CPUSER_sirendalama
   ```
3. Isi `LEGACY_DB_HOST=localhost` dan `LEGACY_DB_DATABASE=CPUSER_sirendalama` (dengan pengguna & sandi basis data tersebut), lalu jalankan perintah impor di cara A langkah 3.

### Hal yang perlu diketahui

- `legacy:import --fresh` mempertahankan akun pengguna, usulan dari PSI/SIF beserta penilaian dan pleno, serta jejak audit aplikasi. Data PSN dan semua snapshot diganti dari sumber, lalu snapshot dibangun ulang dengan perintah di atas.
- Jalankan impor dari Terminal, bukan dari peramban. Prosesnya sekitar satu menit.
- Selama aplikasi lama masih dipakai untuk mengisi data, ulangi impor setiap kali data terbaru diperlukan, mis. menjelang cut-off bulanan. Setelah pengisian berpindah ke v2, koneksi `legacy` sebaiknya dikosongkan.

## Halaman tampil tanpa CSS (berantakan)

Buka halaman, tekan **F12**, lalu lihat tab **Console** dan **Network** (saring *CSS*). Cocokkan dengan tabel berikut.

| Gejala di F12 | Penyebab | Perbaikan |
|---|---|---|
| CSS diminta ke `http://localhost:5173/...` | Berkas `public/hot` (sisa `npm run dev`) ikut terunggah | Hapus `sirenda/public/hot`. Versi terbaru mengabaikan berkas ini di produksi |
| CSS diminta lewat `https://...`, gagal, dan situs dibuka lewat `http://` | SSL belum aktif, sedangkan `APP_URL` sudah `https://` | Selesaikan AutoSSL, atau sementara isi `APP_URL=http://...`, lalu `php artisan config:cache` |
| `/build/assets/app-xxxx.css` → **404** | Document root bukan `sirenda/public`, atau folder `public/build` tidak ikut terunggah | Arahkan document root ke `sirenda/public` (langkah 3). Pastikan `sirenda/public/build/manifest.json` dan `public/build/assets/` ada; bila belum, jalankan `npm run build` di lokal lalu unggah folder `public/build` |
| *Refused to load the stylesheet ... Content Security Policy* | Domain di bilah alamat berbeda dengan domain aset (mis. `www.` vs tanpa `www`) | Samakan `APP_URL` dengan alamat yang dibuka, lalu `php artisan config:cache` |
| Halaman *500 Server Error* (bukan sekadar tanpa CSS) | `public/build/manifest.json` tidak ada | Unggah folder `public/build` hasil `npm run build` |

Setelah perbaikan, kosongkan cache peramban (Ctrl+Shift+R).

## Pembaruan berikutnya

Ulangi langkah 1 dan 4. Saat mengekstrak, timpa berkas lama, tetapi jangan menimpa `.env` dan `storage/`. Setelah itu jalankan:

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Catatan keamanan

- SIRENDA menyimpan data 400+ akun pengguna dan data perencanaan pemerintah. Pastikan penempatan di hosting komersial dan domain non-pemerintah sudah disetujui. Sistem elektronik pemerintah umumnya diarahkan ke pusat data pemerintah dan domain `.go.id`.
- Jangan unggah kunci privat SIF; SIRENDA hanya memerlukan kunci publik.
- Batasi akses cPanel dan aktifkan autentikasi dua faktor di akun hosting.
