# SDLC Sistem Informasi Manajemen Operasional Studio Rekaman Tungku

Tanggal pemeriksaan: 18 November 2026. Dokumen ini mendeskripsikan implementasi yang tersedia pada branch `feat/manager-side-nav` dan `feat/staff-client-login`. Tujuannya adalah mendokumentasikan kebutuhan, rancangan, implementasi, pengujian, dan rencana pengoperasian tanpa mengubah proses bisnis yang ada.

## Ruang lingkup dan kebutuhan

Nama sistem difokuskan pada manajemen operasional studio rekaman. Sistem menghubungkan katalog paket, penawaran, booking, produksi, pembayaran, inventaris, dan laporan operasional. Tidak ada klaim bahwa aplikasi telah memenuhi suatu standar ERP tertentu. Akuntansi perusahaan secara menyeluruh, pengadaan, rantai pasok, dan manajemen SDM lengkap tidak menjadi cakupan dokumen ini.

| ID | Kebutuhan revisi | Implementasi atau bukti | Status |
| --- | --- | --- | --- |
| R1 | Sistem rekomendasi dan tambahan AI | `app/Services/PackageRecommendationClient.php + recommendation-service/app.py`, `app/Http/Controllers/Api/V1/Client/PackageRecommendationController.php`, `docs/package-recommendation-ml.md`; UI `PackageRecommendations.tsx` pada branch client | Tersedia; model harus dilatih pada katalog deployment. Kualitas rekomendasi belum dievaluasi dengan pilihan pelanggan independen. |
| R2 | Detail paket diperjelas untuk customer | API katalog memuat deskripsi, harga akhir, jumlah lagu, jam rekaman, dan empat layanan. Dialog client menampilkan layanan termasuk/tidak termasuk serta akses booking dan penawaran yang sudah ada. | Tersedia; fasilitas, revisi, format hasil, dan estimasi harus diisi studio sesuai paket sebenarnya. |
| R3 | Fokus sistem lebih spesifik daripada ERP umum | Judul dan ruang lingkup dokumen ini; judul web dan meta description sudah menyebut manajemen studio | Tersedia pada aplikasi; perubahan judul laporan akademik dan persetujuan dosen masih perlu dilakukan pada dokumen laporan asli. |
| R4 | Hak akses diperinci | Matriks di bawah, middleware peran dan akun aktif, serta policy kepemilikan/penugasan pada backend | Terdokumentasi; pembatasan dijalankan server. |
| R5 | Segera dipublikasikan | Prosedur `deployment.md` | Belum terverifikasi: URL publik, server tujuan, dan hasil uji produksi belum tersedia dalam pemeriksaan ini. |
| R6 | Dokumen SDLC diselesaikan | Dokumen ini, referensi API, ADR, pengujian, dan prosedur deployment | Dokumentasi teknis tersedia; berita acara UAT dan bukti publikasi perlu dilampirkan setelah pelaksanaan sebenarnya. |

Sebagian kalimat pada lembar revisi sumber sendiri ditandai sebagai perkiraan bacaan. Matriks ini memakai bagian yang jelas tentang ruang lingkup, hak akses, dan detail paket; tidak menganggap bagian yang tidak terbaca sebagai kebutuhan baru.

## Analisis proses yang dipertahankan

Client masuk dan melihat paket, lalu dapat membuka rincian atau mencari rekomendasi menggunakan kebutuhan produksi dan anggaran. Rekomendasi hanya membantu memilih paket; tidak membuat booking, penawaran, atau pembayaran secara otomatis.

Untuk paket siap pakai, client dapat melanjutkan ke booking. Untuk kebutuhan kustom, client mengirim penawaran, manager meninjau harga, dan client menanggapi sampai tercapai kesepakatan. Booking memeriksa ketersediaan ruang dan waktu serta membuat hold. Proses pembayaran dan konfirmasi tetap memakai action serta validasi yang ada.

Manager mengelola proyek dan penugasan operator. Operator mengerjakan tahap yang ditugaskan dan mengirim hasil; manager meninjau hasil dan dapat meminta revisi. Akses hasil akhir mengikuti policy proyek dan aturan penyelesaian/pembayaran yang sudah diimplementasikan. Penambahan dokumentasi dan perjelasan teks paket tidak mengubah endpoint, status, transisi, harga, atau urutan persetujuan ini.

Keputusan bisnis lebih rinci terdapat pada `docs/adr/0001-business-requirements-finalized.md`. Jika terdapat perbedaan antara rancangan historis dan implementasi, verifikasi implementasi melalui action, policy, dan test sebelum mengubah aturan bisnis.

## Rancangan teknis

Frontend React dan TypeScript berkomunikasi dengan REST API Laravel melalui sesi autentikasi Sanctum. Backend memakai request validation, middleware autentikasi/peran/akun aktif, controller, action bisnis, Eloquent, dan resource untuk membentuk respons. Struktur endpoint didokumentasikan pada `docs/API_REFERENCE.md`, `docs/FRONTEND_API.md`, dan `docs/openapi.json`.

Data utama mencakup pengguna dan peran, paket dan tarif dasar, penawaran dan riwayat tawaran, ruang dan sesi booking, proyek dan lagu, tahap produksi dan versi submission, invoice dan pembayaran, inventaris, pengeluaran, serta pengaturan studio. Definisi tabel dan relasi yang dapat direproduksi berada pada `database/migrations` dan `app/Models`. Snapshot proyek menjaga informasi paket pada transaksi lama ketika katalog berubah.

Rekomendasi menggunakan nearest neighbors pada fitur katalog yang distandardisasi: kapasitas lagu, jam rekaman, empat layanan, dan harga efektif. Kandidat wajib mencakup kebutuhan; anggaran diprioritaskan sebelum skor kemiripan. Model tidak dilatih menggunakan label pilihan pelanggan. Model hilang atau kedaluwarsa beralih ke pencocokan katalog; ini bukan bukti bahwa model terlatih sedang digunakan. Skor kemiripan bukan akurasi atau probabilitas kepuasan. Lihat `docs/package-recommendation-ml.md` untuk pelatihan, fingerprint, dan batas evaluasi.

## Matriks hak akses

| Fungsi | Manager | Operator | Client |
| --- | --- | --- | --- |
| Kelola paket, tarif, pengaturan, dan operator | Ya | Tidak | Tidak |
| Katalog client dan rekomendasi paket | Tidak melalui API client | Tidak | Ya, paket aktif dan harga akhir |
| Penawaran | Meninjau dan menanggapi penawaran | Tidak | Membuat dan menanggapi penawaran milik sendiri |
| Booking | Melihat dan mengelola melalui API manager | Sesi sesuai penugasan | Membuat dan mengelola booking sendiri sesuai status |
| Proyek dan hasil produksi | Menugaskan, memantau, dan meninjau hasil | Tahap yang ditugaskan; mengirim hasil dan melihat riwayat yang diizinkan | Data proyek sendiri; hasil akhir sesuai policy |
| Invoice dan pembayaran | Pengelolaan serta konfirmasi sesuai aksi yang tersedia | Tidak | Data dan pembayaran milik sendiri |
| Inventaris, pengeluaran, payroll, dan CRM | Ya melalui API manager | Tidak | Tidak |
| Profil | Profil sendiri | Profil sendiri | Profil sendiri |

Pendaftaran publik membuat akun client, bukan akun manager/operator. Grup `/api/v1/manager`, `/api/v1/operator`, dan `/api/v1/client` memakai `auth:sanctum`, `active`, dan `role` masing-masing. Policy dan filter kepemilikan memberi pembatasan tambahan pada objek. Menyembunyikan menu frontend bukan pengganti otorisasi server. Akun nonaktif tetap dapat logout sesuai route autentikasi.

Client menerima `PackageCatalogResource` yang tidak menyertakan harga otomatis/manual internal, pembuat paket, atau status pengelolaan. Batas ini harus dipertahankan ketika memperluas detail paket.

## Implementasi dan pengendalian perubahan

Branch manager berisi antarmuka pengelolaan dan backend. Branch client berisi portal pelanggan dan backend terkait. Kedua frontend dibangun terpisah; jangan menganggap salah satu build telah mencakup antarmuka branch lainnya. Perubahan lintas backend harus konsisten sebelum deployment. Branch `main` berada di luar lingkup pekerjaan revisi ini.

Perubahan tampilan dibatasi pada penjelasan jam rekaman dan keterbacaan rincian/rekomendasi. Persiapan publikasi berikutnya menambahkan konfigurasi hosting dan tiga endpoint baca client pada backend manager untuk mendukung kedua portal; action transaksi dan transisi status tidak diubah. Detail fasilitas tidak diisi dengan janji yang dibuat pengembang. Manager perlu memasukkan ketentuan yang disepakati studio ke deskripsi paket; kolom tetap opsional agar alur pembuatan paket yang ada tidak berubah.

## Strategi verifikasi dan penerimaan

Jalankan pengujian backend pada database testing terisolasi dan build pada kedua frontend. Perintah, hasil pemeriksaan aktual, dan batas verifikasi dicatat di `revision-verification.md`. Test API relevan berada di `tests/Feature/Client/PackageCatalogTest.php`, `tests/Feature/Client/PackageRecommendationTest.php`, `tests/Feature/PackageRecommendationClientTest.php`, serta test pricing, booking, penawaran, dan submission.

UAT perlu dilakukan bersama perwakilan studio dan pelanggan menggunakan akun uji terpisah:

1. Manager membuat paket sesuai layanan nyata; client membaca harga, jumlah lagu, jam total, deskripsi, serta layanan termasuk/tidak termasuk.
2. Client mencari rekomendasi dengan anggaran cukup/tidak cukup dan kebutuhan tanpa pasangan; hasil membantu memilih tanpa membuat transaksi otomatis.
3. Jalankan paket siap pakai sampai booking dan pembayaran; jalankan penawaran kustom sampai kesepakatan.
4. Manager menugaskan operator, operator mengirim hasil, manager meninjau dan meminta revisi bila perlu, lalu client mengakses hasil sesuai haknya.
5. Coba akses silang peran, objek pelanggan lain, dan akun nonaktif; akses yang tidak berhak harus ditolak.

Catat tanggal, versi kedua branch, pelaksana, data uji, hasil aktual, dan temuan setiap skenario. Daftar ini adalah prosedur penerimaan, bukan klaim bahwa UAT bersama mitra sudah dilakukan.

## Pengoperasian dan pemeliharaan

Ikuti `deployment.md` untuk konfigurasi produksi, build, migrasi, worker, scheduler, pelatihan katalog, uji publik, dan rollback. Latih ulang rekomendasi setelah katalog berubah dan pantau fallback. Tinjau log kesalahan, job gagal, callback pembayaran, serta backup dan pemulihannya. Simpan bukti publikasi dan persetujuan UAT bersama laporan proyek. Evaluasi kualitas rekomendasi secara terpisah setelah tersedia data pilihan pelanggan yang sah dan independen.


## Pembaruan microservice rekomendasi

Training dan inferensi rekomendasi dipindahkan ke Python FastAPI/scikit-learn yang dideploy terpisah di https://tungku-recommendation.vercel.app. Laravel hanya adapter HTTP; lihat package-recommendation-ml.md. API/format UI tetap sama. Model memakai katalog terbaru pada tiap request; kualitas terhadap pilihan pelanggan belum dievaluasi.
