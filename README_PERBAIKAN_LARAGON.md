# PeerSpace Laravel 13 — Fix error SQLite + UI/UX lengkap

## Kenapa ada error pada screenshot?

Error `Illuminate\\Database\\QueryException: could not find driver (Connection: sqlite, SQL: select * from sessions ...)` menunjukkan **PHP CLI yang dipakai untuk `php artisan serve` belum mempunyai driver PDO SQLite aktif**. Ini terjadi bahkan saat membuka beranda karena Laravel kamu sedang memakai `SESSION_DRIVER=database`. Mengubah `SESSION_DRIVER=file` bisa memperbaiki error *beranda* sementara, tetapi random matchmaking **tetap membutuhkan database** dan driver yang sesuai.

## LANGKAH 1 — PHP di Laragon (Windows)

Buka terminal di folder Laravel kamu (yang berisi `artisan`):

```powershell
php -v
php --ini
php -m | findstr /I "pdo_sqlite sqlite3 pdo_mysql"
php -r "print_r(PDO::getAvailableDrivers());"
```

Perhatikan `Loaded Configuration File` dari `php --ini` — itulah `php.ini` untuk **PHP CLI** yang dipakai Artisan. Jalur PHP yang disediakan Laragon berbeda-beda, jangan asal mengedit `C:\\Windows\\php.ini`.

**Opsi A: SQLite (paling sederhana untuk development)**

1. Di `php.ini` yang aktif, aktifkan dua extension berikut dengan menghilangkan `;` pada awal baris:

   ```ini
   extension=pdo_sqlite
   extension=sqlite3
   ```

2. Pastikan DLL extension tersedia dan cocok dengan versi PHP di folder `ext` Laragon. Kalau DLL tidak tersedia, ubah versi PHP melalui Laragon atau instal distribusi PHP yang sudah berisi ekstensi tersebut. Jangan menyalin DLL dari versi PHP lain.
3. Restart terminal dan **Ctrl+C** pada proses `php artisan serve`, lalu jalankan ulang.
4. Periksa: `php -m | findstr /I "pdo_sqlite sqlite3"`. Keduanya seharusnya tampil.

File opsional `tools/check-laragon-db.ps1` membantu diagnosis. Jika DLL SQLite sudah ada, kamu bisa meminta skrip tersebut mengaktifkan extension secara otomatis (**skrip membuat backup php.ini**):

```powershell
powershell -ExecutionPolicy Bypass -File .\tools\check-laragon-db.ps1
powershell -ExecutionPolicy Bypass -File .\tools\check-laragon-db.ps1 -EnableSQLite
```

**Opsi B: MySQL Laragon**

Jika ingin memakai MySQL, pastikan layanan MySQL menyala dan `pdo_mysql` terlihat dalam `php -m`. Buat database kosong `peerspace` melalui HeidiSQL/phpMyAdmin, kemudian gunakan pengaturan `SETUP_EXAMPLES/mysql.env.example` ke `.env` milikmu. Sesuaikan user, password, dan nama database. Pastikan `pdo_mysql` aktif juga.

## LANGKAH 2 — Ubah `.env` tanpa menimpa `APP_KEY`

Pilih SATU database. Untuk SQLite gunakan:

```dotenv
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=sqlite
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

Jika `DB_DATABASE=` di `.env` menunjuk ke lokasi lain, pastikan file itu benar, atau hapus `DB_DATABASE` untuk memakai default Laravel `database/database.sqlite`. Buat filenya pada PowerShell dari root Laravel:

```powershell
if (!(Test-Path database\database.sqlite)) { New-Item database\database.sqlite -ItemType File | Out-Null }
```

`SESSION_DRIVER=file` memindahkan session ke `storage/framework/sessions`, dan `CACHE_STORE=file` menggunakan local file cache untuk lock matchmaking. **Keduanya tidak menghapus kebutuhan database untuk `/random`.** Untuk produksi multi-server, gunakan Redis terpusat dan mekanisme antrean yang telah diuji beban.

## LANGKAH 3 — Jalankan proyek

Pastikan kamu berada di root Laravel aslimu yang memiliki `artisan` dan `composer.json`. Salin paket perubahan dari ZIP dengan **menggabungkan folder**, bukan mengganti satu folder secara keseluruhan. Backup dulu file yang sudah dikustomisasi.

```powershell
php artisan optimize:clear
php artisan migrate
php artisan route:list --path=random
php artisan serve
```

Jika `optimize:clear` gagal karena cache database, pastikan `CACHE_STORE=file` di `.env`, lalu jalankan kembali. Jika sebelumnya terdapat config cache lama, kamu bisa jalankan `php artisan config:clear` terlebih dahulu.

Akses:
- Dashboard: `http://127.0.0.1:8000/`
- Random video chat: `http://127.0.0.1:8000/random`
- Screen sharing manual lama: `http://127.0.0.1:8000/screenshare`

## Apa yang sudah diperbaiki/ditambahkan

- Layout Blade bersama `resources/views/layouts/app.blade.php` yang sebelumnya kosong.
- `components/navbar.blade.php`, `components/sidebar.blade.php`, `components/footer.blade.php`: terisi tautan nyata, menu aktif, UI mobile, branding, footer.
- `dashboard.blade.php` dan `welcome.blade.php`: dashboard modern dan responsif menggunakan partial `partials/dashboard-content.blade.php`.
- Asset baru `public/css/peerspace-ui.css` dan `public/js/peerspace-ui.js`; **tidak memerlukan `npm install`** untuk UI ini.
- Dashboard menampilkan pemeriksaan API WebRTC/browser tanpa mengklaim ada orang online atau koneksi server aktif.
- Link kembali ke dashboard di random chat. Halaman lama `/screenshare` tetap dipertahankan.
- Mengubah batas throttling endpoint `random/api` dari 90 menjadi 300 permintaan per menit per IP **untuk pengujian beberapa tab** (HTTP polling setiap sekitar 1,1 detik akan menghabiskan 90 request/menit pada dua tab). Sebelum aplikasi publik, buat rate limit berbasis pengguna/sesi dan anti-abuse terpisah.
- Halaman baru `/dashboard`, selain route `/` yang sudah ada.

## Hal yang belum bisa dijamin / catatan produksi

- PHP di lingkungan penyusunan tidak memiliki driver PDO database; uji migrasi dan pairing WebRTC *end-to-end* belum dilakukan. Syntax checking PHP dan JavaScript dapat dilakukan tanpa menghubungkan database.
- SQLite extension harus dinyalakan di mesin Windows sendiri. ZIP tidak mengandung `php.ini`, `.env`, kredensial, file SQLite yang menimpa milikmu, atau `vendor`.
- Kamera, mikrofon, dan `getDisplayMedia()` perlu HTTPS atau localhost sebagai secure context. `http://192.168.x.x:8000` dari perangkat kedua biasanya **tidak** mendapat izin browser. Gunakan hostname HTTPS yang dapat diakses kedua perangkat.
- TURN server diperlukan untuk koneksi di sejumlah jaringan. Jangan mengekspos aplikasi video chat acak ke publik tanpa moderasi, pelaporan, batas usia, aturan penggunaan, dan pengamanan abuse.

## Verifikasi cepat

```powershell
php -m | findstr /I "pdo_sqlite sqlite3"    # jika DB_CONNECTION=sqlite
php artisan migrate:status
php artisan route:list --path=random
php artisan test --filter=RandomChatTest
```

Jika tes memerlukan konfigurasi DB khusus, atur environment testing dengan driver yang tersedia; jangan gunakan database production.
