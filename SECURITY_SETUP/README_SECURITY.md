# PeerSpace Laravel 13 — Security Upgrade (Tahap 1)

**Status: hardening dasar untuk pengujian; BELUM persetujuan bahwa website aman untuk pengguna publik.** Paket ini melengkapi project Laravel 13 yang sudah berjalan dengan patch server dan browser. Anda tetap harus melakukan deployment TLS, TURN, moderasi dan pengujian keamanan.

## Cara pemasangan (Windows + Laragon)

1. **Backup** folder `D:\laragon\www\Multi2Screen` dan database SQLite (atau ekspor MySQL).
2. Dari paket `PeerSpace_Security_Patch.zip`, gabungkan file ke **root folder yang berisi `artisan`**. Jika sudah memodifikasi file yang sama, gunakan diff/merge; jangan menimpa perubahan lain tanpa review.
3. Jangan mengganti `.env`, `composer.json`, `composer.lock`, atau `vendor/`. Paket **tidak** menyertakan rahasia `.env`.
4. Set untuk local di `.env`:

   ```dotenv
   APP_ENV=local
   APP_DEBUG=true
   APP_URL=http://127.0.0.1:8000
   DB_CONNECTION=sqlite
   SESSION_DRIVER=file
   CACHE_STORE=file
   QUEUE_CONNECTION=sync
   RTC_RELAY_ONLY=false
   ```

   Nilai `APP_DEBUG=true` **hanya untuk development**, jangan pernah digunakan di internet publik.
5. Buka terminal baru dan jalankan:

   ```powershell
   cd D:\laragon\www\Multi2Screen
   php artisan optimize:clear
   php artisan migrate
   php artisan route:list --path=random
   php artisan test --filter=RandomChatTest
   php artisan serve
   ```
6. Test dengan **dua browser/tab**: tekan Mulai, chat, share screen, Next, Stop. Buka `/random` lalu gunakan DevTools > Network untuk memeriksa respons `429` pada percobaan spam, header CSP/Permissions Policy dan pemisahan akses antar room.
7. Pada Laravel asli, jalankan pemeriksaan dependency:

   ```powershell
   composer audit
   npm audit --omit=dev
   ```

   Jika tidak menggunakan dependency Node, `npm audit` dapat diabaikan.

## Apa yang diperbaiki

- `app/Http/Middleware/SecurityHeaders.php`: anti clickjacking, nosniff, policy izin kamera/mikrofon/layar, referrer, cache no-store untuk WebRTC dan **CSP dengan nonce** di `/random`; HSTS hanya aktif untuk HTTPS production.
- `bootstrap/app.php`: mendaftarkan middleware di seluruh respons. CSP strict hanya pada `/random` agar tidak mematahkan halaman legacy `/screenshare` yang masih menggunakan script/style inline dan Tailwind CDN.
- `app/Providers/AppServiceProvider.php`, `routes/web.php`: rate-limit **terpisah** untuk join/state/signal/leave/report, gabungan IP dan session. Batasnya perlu diuji beban dan disesuaikan dengan trafik nyata.
- `RandomChatController`: chat maksimal 20/menit per peer, batas payload SDP/ICE/chat, validasi report, endpoint `state` tidak diam-diam mengantre ulang peer yang sudah berhenti.
- `config/rtc.php`: konfigurasi WebRTC dari Laravel `config()`, opsi TURN dengan HMAC berumur terbatas. Static TURN hanya digunakan untuk local dev, bukan saat `APP_ENV=production`.
- `public/js/random-chat.js`: konfigurasi mode `RTC_RELAY_ONLY` jika tidak ingin kandidat P2P langsung mengekspos IP peer (butuh TURN yang benar-benar siap).
- `screenshare.blade.php`: chat via `textContent`/`createTextNode` alih-alih `innerHTML` yang rentan **DOM-based XSS** dari pesan peer, juga memperbaiki STUN port dan akses `sender.track` saat null.
- `routes/console.php`: jadwal hapus signaling lebih dari 15 menit, exclusion kadaluarsa, peer idle lebih dari 1 hari, report lebih dari 90 hari (dapat diatur).
- `tests/Feature/RandomChatTest.php`: regression test untuk report saat tidak berpasangan, stop tidak otomatis antre ulang, oversized chat, dan security headers/CSP.

## Konfigurasi minimum production (jangan diterapkan pada localhost HTTP)

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-real-domain.example
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_ENCRYPT=true
RTC_TURN_URL=turns:turn.your-real-domain.example:5349?transport=tcp
RTC_TURN_SHARED_SECRET=GENERATE_LONG_RANDOM_SECRET_AND_KEEP_ON_SERVER
RTC_TURN_TTL=3600
RTC_RELAY_ONLY=false
RTC_REPORT_RETENTION_DAYS=90
```

- `SESSION_SECURE_COOKIE=true` memerlukan **HTTPS**. Jangan mengaktifkannya di `http://127.0.0.1:8000` karena dapat mengacaukan cookie session.
- Ganti domain contoh, pasang sertifikat TLS tepercaya, dan pastikan `APP_KEY` sudah dibuat serta disimpan aman. **Jangan mengganti APP_KEY setiap kali deploy** (akan membatalkan cookie/sesi dan data enkripsi).
- Redis pada `CACHE_STORE` sangat penting agar *matchmaking lock* bersifat distributed saat memakai banyak PHP worker/server; jika multi-server, session harus memakai penyimpanan terpusat.
- Jalankan `php artisan optimize` setelah env siap di production (jangan hanya mengganti `.env` ketika konfigurasi masih dicache). Gunakan queue workers, scheduler `php artisan schedule:run` per menit, dan Supervisor/systemd di server.
- Web root **hanya folder `public/`**, bukan root project. Blokir akses `.env`, `storage/`, `vendor/`, `.git/`, file backup dan log melalui webserver. Matikan directory listing.
- Pasang reverse proxy TLS dan batasi `Host` yang sah (Laravel `trustHosts` atau webserver whitelist), serta *trust proxy* hanya dari IP proxy yang dipercaya.
- Berikan rate-limit juga di Nginx/CDN/WAF; PHP limiter sendiri tidak cukup untuk menahan DDoS. Siapkan Cloudflare Turnstile/hCaptcha/antibot sebelum masuk antrean dan perlindungan khusus perangkat/akun.
- Tambahkan peraturan layanan, privasi, kebijakan usia, opsi consent, admin moderation, review laporan, ban dan block yang tidak hanya mengandalkan session, serta mekanisme banding sebelum publik.
- Lakukan pentest dan test dua perangkat lintas NAT, tes browser mobile, audit log, alert, backup/restore, dan perbarui dependency rutin.

## TURN coturn (contoh konfigurasi — harus dikerjakan pada server TURN)

```ini
listening-port=3478
tls-listening-port=5349
realm=turn.your-real-domain.example
fingerprint
use-auth-secret
static-auth-secret=SET_THE_SAME_VALUE_AS_RTC_TURN_SHARED_SECRET
cert=/etc/letsencrypt/live/turn.your-real-domain.example/fullchain.pem
pkey=/etc/letsencrypt/live/turn.your-real-domain.example/privkey.pem
no-loopback-peers
no-multicast-peers
min-port=49160
max-port=49200
```

Ganti contoh realm/domain/sertifikat/secret; izinkan UDP 3478, TCP 5349, dan port relay sesuai konfigurasi; perlu bandwidth TURN yang memadai. Model `use-auth-secret` memakai username `expiry:random` dengan password HMAC-SHA1 base64. **TURN shared secret tidak boleh muncul di JavaScript atau repo publik.** Browser tetap menerima *credential sementara*, ini perilaku standar untuk akses TURN.

Bila privacy lebih penting daripada koneksi langsung, aktifkan `RTC_RELAY_ONLY=true` tetapi pastikan TURN dapat dijangkau; relay menambah biaya bandwidth dan latensi. **WebRTC DTLS-SRTP mengenkripsi media saat transit**, bukan jaminan mitra tidak dapat merekam/menyebarkan konten atau tidak mengetahui IP saat P2P langsung dipakai.

## Catatan batas keamanan patch ini

1. Masih berbasis **guest session**, tanpa verifikasi identitas. Pelaku dapat menghapus cookie, ganti browser, proxy/VPN; anti-bot dan admin enforcement wajib ditambahkan.
2. Signaling masih HTTP polling ke database. Untuk skala publik, migrasikan ke Redis + Laravel Reverb, tetapi **channel private/presence harus divalidasi dan berotorisasi per room**, tidak hanya URL channel.
3. Legacy `/screenshare` memiliki script inline dan CDN. Injeksi chat yang mudah terlihat sudah diatasi, tetapi belum menjalani audit penuh atau strict CSP. Idealnya migrasikan semua asset ke build lokal Vite dan terapkan CSP strict untuk setiap route.
4. Laravel menyimpan **sinyal SDP/ICE dan chat** di database sementara. Ini dapat mengandung alamat jaringan/teks pribadi; atur akses DBA dan logging, batasi retention, dan cantumkan di Privacy Policy. Tidak ada fitur rekaman media bawaan.
5. Tidak ada kode yang menjamin website bebas celah. Lakukan security test terpisah sebelum menerima pengguna publik.

## Pengujian praktis

- [ ] Endpoint GET `/random` mengembalikan status 200 serta header `Content-Security-Policy`, `X-Frame-Options: DENY`.
- [ ] Dengan dua browser, `join` keduanya menjadi `paired`, offer/answer/ICE diterima di peer sesuai room.
- [ ] Setelah Stop, `/random/api/state` tetap `idle` dan tidak mengantre ulang.
- [ ] `report` tanpa pasangan ditolak HTTP 409.
- [ ] Kirim chat 1001 karakter ditolak 422; pesan chat remote berisi `<img onerror=...>` **ditampilkan sebagai teks**, bukan diproses HTML.
- [ ] Setelah batas chat/rate-limit tercapai, API mengembalikan 429.
- [ ] Tab yang bukan partner tidak menerima sinyal room lain.
- [ ] Scheduler memang dijalankan dan data signaling lama terhapus.
- [ ] Tes autentikasi/otorisasi moderator admin jika nanti dikembangkan, termasuk file upload dan IDOR.
