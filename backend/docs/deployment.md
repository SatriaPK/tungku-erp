# Publikasi Sistem Manajemen Operasional Tungku

Dokumen ini menyiapkan publikasi; bukan bukti aplikasi telah online. Server, domain, kredensial layanan, dan URL publik belum diverifikasi pada pemeriksaan 18 November 2026.

## Persiapan rilis

Pilih revisi backend yang kompatibel dengan kedua frontend. Bangun frontend manager dari `feat/manager-side-nav` dan frontend client dari `feat/staff-client-login` sebagai dua artifact terpisah. Jangan menyalin salah satu `dist` di atas artifact yang lain. Catat commit backend dan kedua frontend pada berita acara rilis.

Gunakan versi runtime yang memenuhi `composer.lock` dan `frontend/package-lock.json`. Pada backend jalankan `composer check-platform-reqs` dan `composer install --no-dev --prefer-dist --optimize-autoloader` di direktori rilis. Pada masing-masing frontend jalankan `npm ci`, lalu `npm run build`. Unggah hasil `frontend/dist` ke host frontend masing-masing; konfigurasi fallback SPA ke `index.html` agar refresh URL seperti `/client/packages` bekerja.

Document root backend harus `backend/public`. `.env`, `vendor`, database, dan `storage/app/private` tidak boleh menjadi document root. Gunakan HTTPS dan konfigurasi produksi `APP_ENV=production`, `APP_DEBUG=false`, URL backend, database, mail, session, dan provider pembayaran yang benar. Simpan secret di server; jangan memasukkannya ke Git atau variabel `VITE_*` karena variabel tersebut dibundel ke browser.

Periksa variabel URL API dan origin pada contoh environment masing-masing branch. Sesuaikan CORS dengan origin frontend sebenarnya dan Sanctum/session cookie dengan susunan domain yang digunakan. Sesi cookie lintas situs perlu diuji pada browser tujuan. Jangan mengandalkan proxy Vite development sebagai konfigurasi produksi.

## Pelaksanaan pada server tujuan

1. Backup database dan berkas upload; pastikan prosedur restore dapat digunakan. Siapkan direktori rilis baru dan environment produksi. Pertahankan `APP_KEY` deployment yang sudah ada; untuk instalasi baru buat satu kali saja.
2. Jalankan migrasi `php artisan migrate --force` setelah meninjau migrasi terhadap backup. Jangan menjalankan seeder demo pada database produksi. Siapkan akun staf melalui prosedur administrasi yang sah serta paket yang benar-benar disediakan studio.
3. Pastikan direktori `storage` dan `bootstrap/cache` dapat ditulis oleh proses aplikasi. Konfigurasi storage dan URL unduhan sesuai disk yang dipakai tanpa membuka direktori privat.
4. Jalankan `php artisan ml:train-package-recommendations` setelah minimal dua paket valid non-demo tersedia. Periksa hasil training dan metadata endpoint sebagai client. Jika belum cukup data, dokumentasikan fallback; jangan mengklaim model terlatih aktif.
5. Jalankan `php artisan optimize`. Kelola queue worker sesuai koneksi produksi, misalnya `php artisan queue:work`, melalui process manager. Konfigurasikan cron untuk `php artisan schedule:run` setiap menit. Restart worker setelah pergantian rilis dengan `php artisan queue:restart`.
6. Konfigurasi callback provider pembayaran menuju endpoint webhook yang tersedia di `routes/Api/webhooks.php`. Uji signature, status berhasil/gagal/kedaluwarsa, dan pemrosesan ulang sesuai lingkungan provider sebelum menggunakan transaksi produksi.
7. Aktifkan artifact frontend dan backend yang kompatibel. Periksa login/logout, detail paket, rekomendasi, penawaran, booking, pembayaran, penugasan, submission/review, serta hak akses melalui HTTPS publik. Jangan menyatakan lolos hanya berdasarkan halaman login berhasil dibuka.

## Bukti publikasi dan pemulihan

Catat URL manager, URL client, URL API, tanggal rilis, commit setiap artifact, status model rekomendasi, hasil smoke test, dan penanggung jawab. Sampai bukti ini ada, revisi "segera di-publish" masih terbuka.

Jika rilis gagal, kembalikan artifact aplikasi ke versi kompatibel sebelumnya dan restart worker. Tinjau kompatibilitas database sebelum rollback; jangan menjalankan rollback migrasi yang menghapus data secara otomatis. Jika pemulihan database diperlukan, gunakan backup yang diverifikasi dan koordinasikan transaksi pembayaran yang terjadi setelah backup. Pantau error aplikasi dan job setelah pemulihan.
