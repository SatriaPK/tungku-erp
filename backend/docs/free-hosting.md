# Deployment Gratis API dan Web Tungku

Konfigurasi disiapkan pada 18 November 2026. Publikasi belum terjadi sampai akun layanan dihubungkan, database dibuat, serta URL publik diuji. Hanya dua branch fitur yang digunakan; tidak perlu mengubah `main`.

## Layanan dan batas biaya

Gunakan Render Free untuk satu API, TiDB Cloud Starter tanpa metode pembayaran untuk database MySQL-compatible, dan dua proyek Cloudflare Pages Free untuk web manager dan client. Jangan memilih trial berbayar, mengaktifkan upgrade, atau memasukkan kartu untuk konfigurasi ini. Paket gratis tetap memiliki kuota; tidak menjamin layanan tanpa batas atau SLA produksi.

Render Free dapat tidur setelah 15 menit tanpa traffic, membutuhkan waktu untuk bangun, dan kehilangan filesystem lokal saat restart. Database, sesi, cache, serta queue memakai database eksternal. Hasil produksi pada implementasi sekarang disimpan sebagai URL, bukan upload audio lokal. Artifact rekomendasi dibangun ulang dari katalog saat container mulai. Scheduler dan worker berada di container yang sama dan hanya berjalan ketika layanan aktif; eksekusi tepat waktu saat layanan tidur tidak dapat dijamin. Ini batas deployment gratis untuk demonstrasi, bukan perubahan aturan bisnis.

Referensi resmi: [Render Free](https://render.com/docs/free), [TiDB Starter](https://docs.pingcap.com/tidbcloud/select-cluster-tier/?compatibility=mysql&plan=starter), dan [Cloudflare Pages limits](https://developers.cloudflare.com/pages/platform/limits/). Periksa batas dan pilihan harga pada akun saat membuat layanan. Aiven MySQL gratis tidak dipilih karena tier tersebut sudah dihentikan untuk layanan baru.

## API terlebih dahulu

1. Masuk Render dan hubungkan akses ke repository privat `SatriaPK/tungku-erp` sesuai izin pemilik repository. Gunakan branch `feat/manager-side-nav`, file `render.yaml`, paket `free`, root directory `backend`, dan Dockerfile `backend/Dockerfile`.
2. Buat satu instance TiDB Cloud Starter gratis dengan spending limit nol/tanpa metode pembayaran. Catat host, port, database, dan username; masukkan password langsung ke environment Render. Jangan mengirim secret ke chat atau menyimpannya di repository. Gunakan CA sistem untuk TLS (`MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt`). Kecocokan migrasi dan locking TiDB harus diuji pada database deployment sebelum menyatakan rilis selesai; test SQLite bukan bukti kompatibilitas TiDB.
3. Set `APP_KEY` permanen (hasil `php artisan key:generate --show` pada lingkungan aman), `APP_URL` ke URL HTTPS API, `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD`. Jangan mengganti APP_KEY setiap restart. Untuk database yang sudah berisi data terenkripsi, gunakan key deployment/data asal yang benar.
4. Set `SESSION_DOMAIN=null`, `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax`, `SESSION_DRIVER=database`, `CACHE_STORE=database`, dan `QUEUE_CONNECTION=database`. `TRUSTED_PROXIES=*` disediakan khusus runtime di belakang proxy Render; jangan aktifkan sembarang pada server lain.
5. Isi `FRONTEND_URLS` dengan dua origin HTTPS web dipisahkan koma, dan `SANCTUM_STATEFUL_DOMAINS` dengan kedua hostname tanpa `https://`. Setelah URL Pages dibuat, isi atau perbarui kedua nilai ini dan redeploy API sebelum uji login.
6. Start container menjalankan migrasi yang ada, cache konfigurasi/view, serta mencoba melatih rekomendasi. Tidak ada seeder demo atau akun/password bawaan yang dijalankan. Siapkan akun manager dan data studio melalui prosedur administrasi yang sah; deployment database kosong bukan salinan data lokal. Migrasi data lokal yang sudah ada perlu dilakukan secara terpisah dan terkontrol.
7. Buka `/up` melalui HTTPS, periksa deployment logs dan status migrasi, lalu uji autentikasi dan endpoint client/manager. Jangan menyatakan API sudah berfungsi penuh hanya dari health check. Provider pembayaran dan email memerlukan kredensial sendiri; tanpa itu, fungsi terkait belum terverifikasi. Render Free memblokir port SMTP umum sehingga gunakan integrasi email HTTPS yang sudah didukung aplikasi bila diperlukan.

Backend manager mendapat tiga endpoint GET yang sebelumnya sudah tersedia di branch client: `client/base-prices`, `client/rooms`, dan `client/projects`. Endpoint tersebut tetap di bawah middleware client aktif. Ini menyediakan kontrak baca yang dibutuhkan portal client pada satu API bersama, tanpa mengubah transaksi, harga, maupun status produksi. Endpoint proyek memfilter pemilik dan endpoint ruang hanya menampilkan ruang aktif.

## Web setelah API tersedia

Buat dua proyek Cloudflare Pages dari repository yang sama. Untuk masing-masing proyek pilih branch fitur yang tepat, root directory `frontend`, output `dist`, dan command berikut:

```sh
npm ci && node deploy/build-cloudflare.mjs
```

Script build secara eksplisit mengatur `VITE_API_ORIGIN` menjadi string kosong, termasuk pada Windows yang dapat menghapus environment kosong. Ini membuat browser memakai `/api/...` serta `/sanctum/...` pada domain web sendiri. Set environment runtime `API_ORIGIN` ke origin HTTPS API Render, tanpa path/query. Helper menyalin `_worker.js` dan `_routes.json` ke hasil build. Worker hanya menangani `/api/*` dan `/sanctum/*`; halaman dan asset tetap dilayani Pages.

Cookie session dan XSRF diteruskan oleh proxy tanpa mengubah alur login Sanctum. Proxy memakai upstream tetap dari environment, mempertahankan metode/body/query/status/cookie, tidak mengikuti redirect upstream otomatis, dan melarang cache respons API. Jangan mengisi `VITE_API_ORIGIN` dengan URL Render pada konfigurasi ini karena cookie XSRF pada domain lain tidak dapat dibaca oleh frontend.

Web manager memakai `feat/manager-side-nav`; web client memakai `feat/staff-client-login`. Gunakan URL gratis `pages.dev` masing-masing. Subdomain gratis sudah cukup; domain berbayar tidak diperlukan. Login di kedua domain memiliki cookie browser masing-masing. Perpindahan antarportal dapat meminta login ulang, sesuai sesi masing-masing domain.

## Verifikasi sebelum dinyatakan selesai

Periksa login/logout dan CSRF pada kedua web, akses silang peran, harga dan detail paket, rekomendasi, proyek milik client, booking, penawaran dan persetujuannya, invoice/pembayaran, serta penugasan/submission/review. Uji setelah API tidur/restart untuk memastikan sesi dan data tetap tersedia. Catat URL API, kedua URL web, commit, batas kuota, hasil uji aktual, dan status provider pembayaran/email. Jalankan pengujian migrasi serta transaksi terhadap TiDB; jika ada inkompatibilitas jangan memaksakan publikasi dengan mengubah alur bisnis.

Docker belum dibangun pada perangkat lokal jika executable Docker tidak tersedia. Build frontend, pengujian proxy, serta test backend lokal harus dibedakan dari hasil deployment cloud sebenarnya.
