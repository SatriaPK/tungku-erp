# Hasil Pemeriksaan Revisi Tungku

Pemeriksaan lokal 18 November 2026 menggunakan lembar revisi yang diberikan pengguna. Ruang lingkup hanya `feat/manager-side-nav` dan `feat/staff-client-login`; tidak ada perubahan pada `main`. Ini merupakan verifikasi teknis lokal, bukan berita acara UAT mitra atau bukti publikasi.

## Perubahan

Pada branch manager, detail paket sekarang membedakan total jam rekaman per paket dengan layanan yang tidak mencakup rekaman. Deskripsi mempertahankan baris baru dan membungkus teks panjang. Pada branch client, deskripsi dialog juga mempertahankan baris baru; pemisah rincian rekomendasi dan teks saat pencarian diperjelas.

Dokumen `sdlc.md`, `deployment.md`, dan dokumen pemeriksaan ini ditambahkan pada kedua worktree. Tahap audit awal tidak mengubah route, middleware, action, policy, skema database, harga, transisi status, atau proses pembayaran. Modifikasi environment, CORS client, konfigurasi Vite, README client, dan direktori artifacts yang sudah ada sebelum pemeriksaan ini dipertahankan. Persiapan hosting lanjutan dijelaskan di bawah.

## Hasil pengujian

| Pemeriksaan | Branch | Hasil |
| --- | --- | --- |
| `npm run build` setelah perubahan | Manager | Lulus TypeScript dan build Vite |
| `npm run build` setelah perubahan | Client | Lulus TypeScript dan build Vite |
| `php artisan test --compact --filter='PackageCatalogTest\|PackageRecommendationTest\|PackageRecommendationModelTest\|PackagePricingTest\|TrackStageSubmissionTest\|FinalDelivery'` | Manager | 57 test lulus, 193 assertion |
| `php vendor/bin/pest --compact tests/Feature/Client/PackageCatalogTest.php tests/Feature/Client/PackageRecommendationTest.php tests/Feature/PackageRecommendationModelTest.php` | Client | 29 test lulus, 100 assertion |
| `php vendor/bin/pest --compact tests/Feature/Integration/BookingFlowIntegrationTest.php tests/Feature/Integration/PaymentFlowTest.php tests/Feature/Auth/AuthTest.php tests/Feature/Policies/RecordingSessionPolicyTest.php` | Masing-masing branch | 23 test dan 81 assertion lulus pada setiap branch |
| `npx playwright test tests/package-selection.spec.ts tests/quotation-confirm.spec.ts --reporter=line` | Client | 3 test browser lulus |
| Pemeriksaan diff dengan pengenalan CRLF | Kedua branch | Tidak ada kesalahan whitespace |

Total backend: 132 eksekusi test dengan 455 assertion lulus. Test browser memastikan detail paket dapat dibuka melalui kartu, keyboard, dan menu, lalu diteruskan ke booking; konfirmasi penawaran juga lulus untuk dua kondisi metadata tawaran. Test browser menggunakan API mock dan tidak membuktikan integrasi server produksi.

Satu percobaan build manager dan pengujian tambahan dengan filter luas kehabisan memori/paging Windows ketika beberapa pemeriksaan berjalan bersamaan. Build manager kemudian diulang dan berhasil; pengujian alur diulang dengan file tertentu pada kedua branch dan berhasil. Hasil di atas berasal dari eksekusi yang selesai, bukan percobaan yang gagal tersebut. Seluruh suite dan uji konkurensi MySQL tidak dijalankan.

## Hal yang masih terbuka

Publikasi belum diverifikasi karena server tujuan, domain, serta URL publik belum tersedia dalam pemeriksaan ini. Rencana publikasi ada di `deployment.md`. Backend kedua branch memiliki perbedaan yang sudah ada sebelumnya; rilis gabungan perlu menentukan backend yang mendukung kedua frontend dan menguji kompatibilitasnya. Tidak dilakukan merge otomatis untuk menghindari perubahan alur.

Model rekomendasi dan fallback telah diuji secara fungsional, tetapi kualitas rekomendasi terhadap pilihan pelanggan independen belum terukur. Hasil test bukan angka akurasi model. Pelatihan serta status model pada deployment produksi perlu diverifikasi setelah katalog sebenarnya tersedia.

Data fasilitas, batas revisi, format hasil, estimasi penyelesaian, dan biaya tambahan harus disahkan studio dan diisi dalam deskripsi setiap paket. Sistem tidak menciptakan ketentuan layanan tersebut. Judul laporan akademik, persetujuan dosen, serta bukti UAT memerlukan dokumen dan pelaksanaan sebenarnya; tidak dinyatakan selesai oleh perubahan kode ini.

## Persiapan hosting lanjutan

Permintaan berikutnya adalah publikasi API terlebih dahulu, kemudian web, pada paket gratis. Ditambahkan Docker/Apache, worker queue dan scheduler, blueprint Render Free, serta proxy Cloudflare Pages untuk mempertahankan cookie sesi pada domain web. CORS backend manager mendukung daftar origin melalui environment. Trusted proxy dapat dikonfigurasi melalui environment pada kedua backend. Tiga endpoint GET milik portal client ditambahkan ke backend manager dengan middleware peran dan filter kepemilikan yang sama; tidak ada perubahan pada action transaksi atau status bisnis.

Verifikasi lanjutan: build Pages pada kedua frontend berhasil, lima test proxy lulus (termasuk cookie, CSRF, POST, error, dan pembatasan upstream), 32 test katalog/rekomendasi/workspace pada backend manager lulus dengan 118 assertion, dan tiga test workspace client lulus dengan 18 assertion. Konfigurasi ini belum menjadi deployment publik dan Docker/TiDB belum diuji pada layanan cloud. Detail kebutuhan akun dan batas paket gratis ada di `free-hosting.md`.


## Pembaruan microservice rekomendasi

Training dan inferensi rekomendasi dipindahkan ke Python FastAPI/scikit-learn yang dideploy terpisah di https://tungku-recommendation.vercel.app. Laravel hanya adapter HTTP; lihat package-recommendation-ml.md. API/format UI tetap sama. Model memakai katalog terbaru pada tiap request; kualitas terhadap pilihan pelanggan belum dievaluasi.
