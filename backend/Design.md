# Design: Arsitektur dan Skema Database Backend

Terakhir diperbarui: 8 Oktober 2026

Dokumen ini menjabarkan arsitektur backend dan skema database. Alur bisnis dibahas di Plan.md, daftar tugas di Task.md. Bagian yang berlabel "rencana" belum ditulis kodenya.

## 1. Gambaran arsitektur

Backend adalah aplikasi Laravel 13 yang hanya mengeluarkan JSON. Setiap permintaan melewati lapisan yang sama, dan setiap lapisan punya satu tugas.

| Lapisan | Tugas | Contoh |
| --- | --- | --- |
| Route | Memetakan URL ke controller, memasang middleware | `routes/api/manager.php` |
| Middleware | Memeriksa login, status aktif, dan peran | `auth:sanctum`, `active`, `role:manager` |
| Form Request | Validasi dan perapian input | `PackageRequest`, `RegisterRequest` |
| Controller | Menerima request, memanggil Action, mengembalikan respons. Dibuat setipis mungkin | `PackageController` |
| Action | Aturan bisnis. Satu kelas satu aksi, satu method `handle()` | `SavePackage`, `RegisterClient` |
| Model Eloquent | Akses data, relasi, cast, scope | `Package`, `Project` |
| Resource | Menentukan field yang keluar ke frontend | `PackageResource` |
| Service (rencana) | Hal yang bergantung pihak luar | Pembayaran, WhatsApp |

Action dan Eloquent bekerja di lapisan berbeda. Eloquent menjawab bagaimana data dibaca dan disimpan, sedangkan Action menjawab apa aturan bisnisnya. Action memakai model di dalamnya.

Pemisahan ini ditujukan terutama untuk bagian yang punya aturan nyata dan perlu dites sendiri: hold slot, pembuatan project setelah PO lunas, perpindahan status tahap, dan validasi persentase gaji. Untuk hal yang sangat kecil, logika langsung di controller tidak dilarang.

## 2. Konvensi

| Hal | Aturan |
| --- | --- |
| Uang | Integer rupiah tanpa desimal (`unsignedBigInteger`). Persentase gaji memakai `decimal(5,2)` |
| Waktu | Disimpan UTC memakai `dateTime` (bukan `timestamp`, untuk menghindari batas tahun 2038). Konversi ke Asia/Jakarta dilakukan di frontend, kecuali batas laporan bulanan yang dihitung di backend |
| Status dan tipe | Kolom `string` dengan panjang terbatas, nilainya dijaga PHP enum. Bukan `enum` MySQL, agar menambah nilai tidak butuh `ALTER TABLE` |
| Primary key | `id` bigint auto increment. Foreign key bernama `{model}_id`, atau nama peran bila menunjuk `users` (`client_id`, `operator_id`, `created_by`) |
| Prefix API | `/api/v1`, dipasang sejak awal |
| Bentuk respons | Resource dibungkus kunci `data`; error validasi berstatus 422; semua error di `/api/*` selalu JSON |
| Penamaan | Istilah "track" dipakai konsisten, tidak bercampur dengan "song" |
| Namespace enum | `App\Enum` (folder `app/Enum`), trait pendukung berada di folder yang sama |
| Mass assignment | `User::role` dan `User::is_active` tidak masuk `$fillable`, diset eksplisit di kode |

## 3. Autentikasi dan otorisasi

Autentikasi memakai Sanctum mode SPA berbasis cookie dan session. Frontend memanggil `GET /sanctum/csrf-cookie` sekali, lalu mengirim permintaan dengan kredensial (`withCredentials` dan `withXSRFToken` pada Axios) dan header `Accept: application/json`.

Konfigurasi yang dibutuhkan:

| Pengaturan | Nilai |
| --- | --- |
| `bootstrap/app.php` | `statefulApi()`, alias middleware `role` dan `active`, render JSON untuk path `api/*` |
| `.env` | `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN=localhost`, `FRONTEND_URL`, `SESSION_DRIVER=database`. Frontend dan API sama-sama memakai `localhost`, tidak dicampur `127.0.0.1` |
| `config/cors.php` | Path `api/*` dan `sanctum/csrf-cookie`, origin sama dengan `FRONTEND_URL`, `supports_credentials` bernilai true |
| Rate limiting | Limiter `login`: 5 per menit per kombinasi email dan IP. Registrasi: 10 per menit |

Otorisasi berlapis:

1. `auth:sanctum` memastikan ada pengguna yang login.
2. `active` (`EnsureUserIsActive`) menolak akun yang dinonaktifkan walaupun sesinya masih hidup. Route logout sengaja tidak memakainya agar akun nonaktif tetap bisa keluar.
3. `role:manager,operator` (`EnsureRole`) membatasi per peran. Nama peran yang salah ketik melempar error saat route dipanggil, bukan lolos diam-diam.
4. Policy per model (rencana) untuk aturan yang bergantung pada data, misalnya klien hanya boleh melihat project miliknya dan tautan hasil akhir hanya setelah project `completed`.

Login memasukkan `is_active` ke kredensial, sehingga akun nonaktif gagal dengan pesan yang sama seperti kata sandi salah. Hal ini mencegah orang luar menebak akun mana yang ada.

Resource adalah pagar kebocoran data. Field seperti harga dasar, gaji operator lain, atau `raw_payload` pembayaran tidak boleh keluar ke peran yang tidak berhak. Model `Payment` juga menyembunyikan `raw_payload` dan `snap_token` dari serialisasi sebagai pengaman kedua.

## 4. Skema database

### 4.1 Hubungan antartabel

```
users +--< quotations +--< quotation_offers
      +--< projects +--< project_tracks ---------+
      |              +--< project_stages --------+--< track_stage_progress
      |              +--< payments
      |              +--< recording_sessions >-- rooms
      +--< expenses >-- expense_categories
      +--< payroll_items >-- payrolls
      +--< crm_campaigns +--< crm_campaign_recipients
      +--< inventories >-- inventory_categories, rooms, expenses
packages +--< projects, crm_campaigns
quotations +-- projects (satu penawaran, satu project)
base_prices (berdiri sendiri, dibaca saat menghitung harga)
```

### 4.2 Pengguna dan data induk

| Tabel | Kolom penting | Catatan |
| --- | --- | --- |
| `users` | name, email (unik), phone (wajib, format 62...), password, role (default `client`, indeks), crm_consent_at (nullable), is_active (default true), email_verified_at, remember_token | Satu tabel untuk tiga peran |
| `rooms` | name, is_active | Satu baris saat ini. Baris ini dikunci saat hold slot |
| `base_prices` | stage (unik), unit (`hour` atau `track`), price, updated_by | Empat baris: recording, editing, mixing, mastering |
| `packages` | name, description, track_count, recording_hours (default 0), include_recording, include_editing, include_mixing, include_mastering, auto_price, manual_price (nullable), is_price_manual, is_active, created_by, soft delete | Harga berlaku dipilih accessor `price` |

### 4.3 Penawaran

| Tabel | Kolom penting | Catatan |
| --- | --- | --- |
| `quotations` | client_id, track_count, recording_hours, include_*, notes, status (default `awaiting_studio`), auto_price, agreed_price, closed_by, closed_at | Indeks (client_id, status) dan status |
| `quotation_offers` | quotation_id (cascade), offered_by, side (`client` atau `studio`), price, note | Riwayat tawar-menawar. Urutkan dengan `id`, bukan `created_at`, karena dua tawaran bisa punya detik yang sama |

**Perubahan yang direncanakan Phase 17 (keputusan owner 2026-10-14):** nama paket khusus diisi klien saat mengajukan quotation dan disimpan, lalu ditampilkan pada progress dashboard serta diteruskan ke snapshot project. Kolom nama belum ada pada schema existing; migration/Request/Action/Resource dan regression direncanakan di Ticket #13. Data legacy boleh nullable dengan fallback eksplisit, tanpa backfill nama historis rekaan.

### 4.4 Project dan produksi

| Tabel | Kolom penting | Catatan |
| --- | --- | --- |
| `projects` | code (unik), client_id, package_id (nullable), quotation_id (nullable, unik), name, package_snapshot (json), price, po_amount, status (default `pending_payment`), completed_at | `price` adalah salinan dari snapshot untuk memudahkan query |
| `project_tracks` | project_id (cascade), title, position | Unik per (project_id, position) |
| `project_stages` | project_id (cascade), stage, sequence (1 sampai 4), operator_id (nullable), status (default `locked`), deadline_at, assigned_at, started_at, completed_at | Unik per (project_id, stage). Satu baris per tahap aktif, satu operator per tahap |
| `track_stage_progress` | project_stage_id (cascade), project_track_id (cascade), status (default `pending`), result_url, review_notes, revision_count, submitted_at, approved_at, approved_by | Unik per (stage, track). Nama tabel ditulis eksplisit di model karena Laravel akan menebak jamaknya salah |

Tiga lapisan status bekerja di tingkat berbeda dan tidak saling menggantikan:

| Lapisan | Tabel | Pertanyaan yang dijawab |
| --- | --- | --- |
| Siklus hidup project | `projects.status` | Sudah dibayar, sedang dikerjakan, atau menunggu pelunasan? |
| Posisi tahap | `project_stages.status` | Tahap Mixing sudah boleh dimulai atau masih terkunci? |
| Progres per track | `track_stage_progress.status` | Track kedua di tahap Editing sudah disetujui? |

Tahap aktif saat ini adalah tahap pertama (menurut `sequence`) yang belum `completed`. Hasilnya tidak disimpan sebagai kolom di project, melainkan dihitung lewat relasi `currentStage()`.

Kolom waktu di `project_stages` terisi dari aksi yang memang wajib dilakukan pengguna: `assigned_at` saat Manager mengirim penugasan, `started_at` dan `deadline_at` saat operator mengonfirmasi tenggat, `completed_at` saat semua track disetujui. Waktu siap suatu tahap tidak disimpan, karena bisa diturunkan: tahap pertama siap sejak `assigned_at`, tahap berikutnya siap saat `completed_at` tahap sebelumnya. Untuk Recording, `started_at` dan `deadline_at` kosong, karena waktunya mengikuti `recording_sessions`.

### 4.5 Jadwal

| Tabel | Kolom penting | Catatan |
| --- | --- | --- |
| `recording_sessions` | room_id, project_id (nullable), project_stage_id (nullable), client_id, start_at, end_at, status (default `held`), held_until, is_extension, extra_fee, requested_by | `project_id` kosong saat slot baru ditahan. Indeks `rec_sessions_overlap_idx` (room_id, start_at, end_at) dan (status, held_until) |

Sesi tidak ditautkan ke track. Satu sesi bisa merekam beberapa track, dan satu track bisa direkam di beberapa sesi, jadi hubungannya tidak berguna untuk dilacak.

### 4.6 Keuangan

| Tabel | Kolom penting | Catatan |
| --- | --- | --- |
| `payments` | project_id, type (`po` atau `final`), amount, status, provider, order_id (unik), provider_transaction_id (unik, nullable), payment_method, snap_token, payment_url, raw_payload (json), expires_at, paid_at | Baris dibuat dengan status `pending` sebelum klien diarahkan membayar. `order_id` unik adalah dasar idempotency webhook |
| `expense_categories` | name (unik), is_active | Dikelola Manager. Kategori yang sudah dipakai tidak boleh dihapus, hanya dinonaktifkan |
| `expenses` | expense_category_id, recorded_by, description, amount, spent_on (date) | Indeks (expense_category_id, spent_on) untuk laporan per kategori |
| `payrolls` | period_year, period_month (unik bersama), total_revenue, total_expense, net_profit (bertanda), status, finalized_at, finalized_by | Satu baris per bulan, dibekukan saat ditutup |
| `payroll_items` | payroll_id (cascade), operator_id, jobs_count, recommended_percentage, percentage, amount | Unik per (payroll_id, operator_id). Total `percentage` per payroll tidak boleh melebihi 100 |

Tidak ada tabel pemasukan. Pemasukan adalah baris `payments` berstatus `paid`, dan pengeluaran adalah `expenses`. Gaji dibagi dari keuntungan bersih, sehingga tidak dicatat sebagai pengeluaran.

### 4.7 CRM

| Tabel | Kolom penting | Catatan |
| --- | --- | --- |
| `crm_campaigns` | created_by, name, package_id (nullable), message, channels (json), status, sent_at | `channels` memakai cast `AsEnumCollection` |
| `crm_campaign_recipients` | crm_campaign_id (cascade), client_id, channel, status, sent_at, error | Unik per (kampanye, klien, saluran) dengan nama indeks `crm_recipient_unique` |

### 4.8 Inventaris

| Tabel | Kolom penting | Catatan |
| --- | --- | --- |
| `inventory_categories` | name (unik), is_active | Dikelola Manager |
| `inventories` | code (unik), name, inventory_category_id, brand, model, serial_number, purchase_date, purchase_price, residual_value, useful_life_months, status, disposed_on, disposal_value, room_id (nullable), expense_id (nullable), notes, created_by, soft delete | Berdiri sendiri, tidak memengaruhi harga atau keuntungan. Nilai buku dihitung, tidak disimpan |

### 4.9 Tabel infrastruktur

Bawaan Laravel: `sessions`, `password_reset_tokens`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`. Dari Sanctum: `personal_access_tokens`. Rencana tambahan: `notifications` (lewat `php artisan make:notifications-table`) dan tabel log aktivitas dari `spatie/laravel-activitylog`.

### 4.10 Pengaturan studio

| Tabel | Kolom penting | Catatan |
| --- | --- | --- |
| `studio_settings` | key (unik), value (text/json), type (integer/decimal/json/string), description, updated_by | Konfigurasi yang dapat diubah Manager via UI. Seed default: po_percentage (50), hold_duration_minutes (15), operating_hours (JSON) |

**Seed default**:
- `po_percentage`: 50 (decimal, range 30-70)
- `hold_duration_minutes`: 15 (integer, range 10-30)
- `operating_hours`: `{"monday": "09:00-22:00", "tuesday": "09:00-22:00", ..., "sunday": "09:00-22:00"}` (json)

### 4.11 Riwayat revisi

| Tabel | Kolom penting | Catatan |
| --- | --- | --- |
| `track_stage_submissions` | track_stage_progress_id (cascade), version (auto-increment per progress), result_url, review_notes (nullable), submitted_by, submitted_at | Menyimpan histori lengkap semua versi submit per track per stage. Unique (track_stage_progress_id, version) |

Setiap kali operator submit hasil kerja (action `SubmitTrackResult`), sistem membuat row baru di tabel ini dengan `version` yang auto-increment. Manager bisa melihat histori lengkap untuk pelacakan. Tabel `track_stage_progress` tetap punya kolom `result_url` yang berisi URL versi terbaru/approved untuk query cepat.

## 5. Enum dan mesin status

Semua enum berada di `app/Enum`. Trait `HasOptions` menyediakan `values()` dan `options()` (nilai dan label untuk dropdown). Trait `HasTransitions` menyediakan `canTransitionTo()` dan `isTerminal()` untuk enum status. Setiap enum punya `label()` berbahasa Indonesia.

### 5.1 Enum referensi

| Enum | Nilai | Kolom |
| --- | --- | --- |
| `Role` | manager, operator, client | `users.role` |
| `OfferSide` | client, studio | `quotation_offers.side` |
| `StageType` | recording, editing, mixing, mastering | `base_prices.stage`, `project_stages.stage` |
| `PriceUnit` | hour, track | `base_prices.unit` |
| `PaymentType` | po, final | `payments.type` |
| `PaymentProvider` | midtrans, cash | `payments.provider` |
| `CrmChannel` | whatsapp, email | `crm_campaign_recipients.channel`, `crm_campaigns.channels` |

`StageType` juga membawa logika tahap: `sequence()` (urutan 1 sampai 4), `requiresDeadline()` (benar untuk selain Recording), `priceUnit()` (jam untuk Recording, track untuk yang lain), dan `includeColumn()` (nama kolom toggle di paket dan penawaran). `OfferSide` punya `opposite()` dan `statusAfterOffer()`. `PaymentProvider` punya `requiresGateway()` yang mengembalikan false untuk `Cash`.

### 5.2 Enum status dan transisinya

| Enum | Transisi yang diizinkan |
| --- | --- |
| `QuotationStatus` | awaiting_studio ke awaiting_client, accepted, atau declined. awaiting_client ke awaiting_studio, accepted, atau declined. accepted dan declined adalah akhir |
| `ProjectStatus` | pending_payment ke awaiting_assignment atau cancelled. awaiting_assignment ke in_progress atau cancelled. in_progress ke awaiting_final_payment atau cancelled. awaiting_final_payment ke completed. completed dan cancelled adalah akhir |
| `StageStatus` | locked ke ready. ready ke in_progress atau completed. in_progress ke completed |
| `TrackProgressStatus` | pending ke in_review. in_review ke approved atau revision. revision ke in_review. approved adalah akhir |
| `SessionStatus` | held ke confirmed, cancelled, atau expired. confirmed ke completed atau cancelled. sisanya akhir |
| `PaymentStatus` | pending ke paid, failed, atau expired. pending_manual_confirmation ke paid atau cancelled. paid ke refunded. sisanya akhir |
| `PayrollStatus` | draft ke finalized. finalized adalah akhir |
| `CampaignStatus` | draft ke sending. sending ke sent atau failed. failed ke sending (percobaan ulang) |
| `CampaignRecipientStatus` | pending ke sent atau failed. failed ke pending (percobaan ulang) |
| `InventoryStatus` | active dan in_repair saling berpindah. keduanya bisa ke sold atau disposed. sold dan disposed adalah akhir |

Catatan desain: `StageStatus` mengizinkan ready langsung ke completed karena tahap Recording tidak punya langkah konfirmasi tenggat, sehingga tidak pernah melewati in_progress. `ProjectStatus` tidak mengizinkan pembatalan dari awaiting_final_payment, karena setelah semua tahap selesai jalan ke depan hanya pelunasan. Aturan "Editing tidak boleh mulai sebelum Recording selesai" tidak ada di enum, karena bergantung pada tahap mana saja yang aktif di paket. Itu tugas Action yang membuka tahap berikutnya.

## 6. Logika inti backend

### 6.1 Perhitungan harga paket

Action `CalculatePackagePrice` menghitung harga otomatis: jam Recording dikalikan harga per jam, ditambah jumlah track dikalikan harga per track untuk tiap tahap non-Recording yang aktif. Contoh dengan harga dasar 150.000 per jam, serta 200.000 dan 400.000 per track untuk Editing dan Mixing: paket 3 track dengan Recording 4 jam, Editing, dan Mixing berharga `4 x 150.000 + 3 x (200.000 + 400.000) = 2.400.000`. Harga dasar yang belum diatur melempar error, tidak dihitung sebagai nol.

Server selalu menghitung `auto_price` sendiri dan mengabaikan nilai dari input. Harga manual yang diisi menyalakan `is_price_manual`; dikosongkan berarti kembali ke harga otomatis. Jam Recording dinormalkan ke 0 bila tahap Recording tidak aktif. Satuan jam saat ini bilangan bulat.

Ketika Manager mengubah harga dasar, `UpdateBasePrices` memperbarui harga lalu menghitung ulang `auto_price` semua paket dalam satu transaksi. Harga manual tidak disentuh, dan project berjalan tidak terpengaruh karena memakai snapshot.

### 6.2 Snapshot paket (rencana)

Saat checkout, project menyimpan salinan pengaturan paket di `package_snapshot`. Isi yang diusulkan:

```json
{
  "source": "package",
  "package_id": 1,
  "name": "Paket Single",
  "track_count": 3,
  "recording_hours": 4,
  "stages": ["recording", "editing", "mixing"],
  "price": 2400000,
  "rates": { "recording": 150000, "editing": 200000, "mixing": 400000, "mastering": 250000 },
  "po_percentage": 50
}
```

`rates` penting karena biaya perpanjangan Recording memakai tarif per jam dari snapshot, bukan harga dasar terbaru. Nama paket ikut tersimpan agar laporan paket terlaris tetap terbaca walaupun paketnya kelak dihapus atau diganti nama. Untuk paket khusus, `source` bernilai `quotation` dan `price` diambil dari `agreed_price`.

### 6.3 Hold slot dan pencegahan bentrok

Karena MySQL tidak punya exclusion constraint, pencegahan bentrok dikerjakan di kode. Seluruh proses berjalan dalam satu transaksi: baris ruangan dikunci lebih dulu dengan `lockForUpdate()`, baru dicek apakah ada sesi yang beririsan. Mengunci baris ruangan lebih aman daripada mengunci rentang waktu, karena kunci rentang di MySQL mudah memicu deadlock. Dengan satu ruangan, kinerjanya tidak menjadi masalah.

Model `RecordingSession` menyediakan dua scope untuk query ini. `blocking()` mengembalikan sesi yang terkonfirmasi atau yang berstatus `held` dengan `held_until` masih di masa depan. `overlapping($start, $end)` mengembalikan sesi yang rentang waktunya beririsan. Pemakaiannya:

```php
RecordingSession::where('room_id', $roomId)->blocking()->overlapping($start, $end)->exists();
```

Hold yang sudah lewat waktunya tidak dihitung sebagai penghalang di query itu sendiri, sehingga kebenaran jadwal tidak bergantung pada scheduler. Scheduler yang berjalan tiap menit hanya merapikan data: mengubah hold kedaluwarsa menjadi `expired` dan membatalkan project `pending_payment` yang terkait. Validasi `end_at` lebih besar dari `start_at` dikerjakan di Form Request dan Action, karena skema tidak menjaganya.

### 6.4 Pembayaran dan webhook

Alurnya: Action membuat baris `payments` berstatus `pending` dengan `order_id` unik, lalu klien diarahkan ke gateway. Penentu lunas adalah webhook. Pemrosesan webhook harus idempotent dan aman terhadap pengiriman ganda:

1. Cari pembayaran lewat `order_id` di dalam transaksi dengan penguncian baris.
2. Gunakan `PaymentStatus::canTransitionTo()`. Karena `paid` tidak punya transisi kembali ke `pending`, notifikasi kedua mudah dikenali dan diabaikan.
3. Verifikasi tanda tangan dari gateway sebelum memercayai isinya.
4. Simpan payload terakhir ke `raw_payload` untuk pelacakan.
5. Efek bisnis (sesi menjadi terkonfirmasi, project berpindah status, notifikasi terkirim) hanya berjalan sekali, saat transisi `pending` ke `paid` benar-benar terjadi.

Integrasi dikerjakan di balik interface dengan implementasi tiruan terlebih dahulu, baru disambungkan ke Midtrans atau Xendit setelah alurnya terbukti.

### 6.4.1 Pembayaran tunai

Untuk pembayaran tunai (offline), alurnya berbeda dari payment gateway:

1. Saat checkout, klien pilih `payment_method: cash`
2. Action membuat baris `payments` dengan:
   - `provider`: `PaymentProvider::Cash`
   - `status`: `pending_manual_confirmation`
   - Tidak ada `snap_token`, `payment_url`, atau `expires_at`
3. Slot tetap `held` sampai Manager konfirmasi
4. Klien datang ke studio, bayar tunai
5. Manager konfirmasi via `ConfirmCashPayment` action (Manager only):
   - Input: payment_id, amount_received (opsional), notes
   - Transisi `pending_manual_confirmation` → `paid`
   - Isi `paid_at` dengan timestamp konfirmasi
   - Trigger efek bisnis yang sama: sesi `confirmed`, project `awaiting_assignment`, notifikasi
6. Policy `PaymentPolicy::confirmCash()` memastikan hanya Manager yang bisa konfirmasi

Jika pembayaran tunai tidak dikonfirmasi dalam 24 jam, slot akan expired via scheduler (sama seperti payment gateway yang tidak dibayar).

### 6.5 Perpanjangan Recording

Perpanjangan tidak memperpanjang `end_at` sesi lama. Sistem membuat baris sesi baru dengan `is_extension = true` dalam transaksi yang sama dengan hold slot (kunci baris ruangan, cek bentrok), langsung berstatus `confirmed`. `extra_fee` dihitung dari durasi sesi dikalikan tarif Recording per jam yang berlaku saat extension dibuat, lalu disimpan pada sesi; perubahan tarif berikutnya tidak mengubah fee extension tersebut. Hanya operator yang ditugaskan di tahap Recording yang boleh meminta, dan hanya selama tahap itu belum selesai. Setelah project masuk `awaiting_final_payment`, permintaan baru ditolak karena nominal pelunasan sudah terkunci.

### 6.6 Peninjauan dan perpindahan tahap

Satu Action menjadi satu-satunya pintu perpindahan status, dan berjalan dalam satu transaksi supaya tidak ada kondisi setengah jadi:

1. Operator mengirim tautan hasil: track menjadi `in_review`, `submitted_at` terisi. Setiap submit membuat row baru di `track_stage_submissions` dengan `version` yang auto-increment per progress.
2. Manager menyetujui: track menjadi `approved`, `approved_by` dan `approved_at` terisi. Atau meminta revisi: track menjadi `revision`, `review_notes` diisi di tabel `track_stage_submissions`, `revision_count` bertambah.
3. Bila semua track di satu tahap `approved`: tahap menjadi `completed`, tahap berikutnya berpindah dari `locked` ke `ready`, dan operatornya diberi notifikasi.
4. Bila tidak ada tahap berikutnya: project menjadi `awaiting_final_payment` dan tagihan pelunasan dibuat.

Hanya Manager yang boleh menyetujui atau meminta revisi, dijaga lewat Policy. Operator hanya bisa mengirim hasil untuk tahap yang ditugaskan kepadanya dan sedang `ready` atau `in_progress`.

Riwayat lengkap semua versi submit dan review notes dapat diakses via endpoint GET history. Ini berguna untuk pelacakan dan audit.

Persentase progres project dihitung dari sel `approved` dibagi seluruh sel, dengan bobot tahap sama. Hanya status `approved` yang dihitung, supaya angka tidak melonjak lalu turun saat ada revisi.

### 6.7 Pelunasan dan akses hasil akhir

Nominal pelunasan dihitung `Project::remainingAmount()`: harga paket ditambah biaya perpanjangan dari sesi berstatus `confirmed` atau `completed`, dikurangi seluruh pembayaran lunas. Sesi perpanjangan yang dibatalkan tidak ikut tertagih.

Akses ke tautan hasil akhir diatur satu Policy:

```php
public function viewFinalResult(User $user, Project $project): bool
{
    return match ($user->role) {
        Role::Manager => true,
        Role::Operator => $project->stages()->where('operator_id', $user->id)->exists(),
        Role::Client => $project->client_id === $user->id
            && $project->status === ProjectStatus::Completed,
    };
}
```

Resource hanya menyertakan `result_url` tahap terakhir bila Policy mengizinkan.

### 6.8 Keuangan bulanan dan gaji

Batas bulan dihitung dalam Asia/Jakarta lalu dikonversi ke UTC sebelum dipakai memfilter `paid_at`. Pengelompokan per hari menambah 7 jam pada `paid_at` sebelum diambil tanggalnya. `expenses.spent_on` bertipe `date` sehingga tidak terpengaruh.

Saat bulan ditutup, `payrolls` membekukan pendapatan, pengeluaran, dan keuntungan bersih. Rekomendasi persentase operator diusulkan proporsional terhadap jumlah pekerjaan bulan itu (`jobs_count`, yaitu sel track dan tahap yang disetujui dan dikerjakan operator tersebut), dengan dua desimal. Manager boleh mengubahnya, dan total tidak boleh melebihi 100. Bulan yang sudah final tidak menerima pencatatan pengeluaran baru bertanggal di bulan itu, kecuali dibuka kembali lewat aksi eksplisit yang tercatat. Rumus rekomendasi dan nasib sisa persentase masih menunggu keputusan.

### 6.9 Dashboard

Semua grafik dihitung saat dibaca, tidak ada tabel ringkasan.

| Tampilan | Sumber | Pengelompokan |
| --- | --- | --- |
| Pendapatan bulan ini dan grafik Jan–Des | `payments` berstatus `paid` | per bulan menurut `paid_at` (waktu Jakarta), MoM terhadap bulan sebelumnya |
| Pengeluaran bulan ini dan grafik Jan–Des | `expenses` | per bulan menurut `spent_on`, MoM terhadap bulan sebelumnya |
| Penjualan paket | `projects` dibuat pada bulan itu, status bukan `cancelled` | group package:id / quotation:id / unavailable sesuai kontrak #9, tanpa syarat PO lunas; nama dari snapshot |
| Kategori pengeluaran | `expenses` pada bulan itu | per `expense_category_id`, seluruh kategori dengan jumlah transaksi, nominal dan persentase terhadap keseluruhan expense |
| Project dan track berjalan | Project tepat `in_progress` | jumlah project dan total track unik seluruh tahap; bukan jumlah sel track × stage |
| Penawaran terbuka | `awaiting_studio` / `awaiting_client` | `accepted` dan `declined` dikeluarkan; count menunggu Studio terpisah |
| Jadwal recording hari ini | sesi `confirmed` yang belum selesai dan overlap hari ini | project/klien/start_at/end_at, urut waktu mulai |

Keputusan owner 2026-10-14: seluruh paket/kategori masuk grafik dengan denominator seluruh bulan; jadwal/progress tersedia lengkap tanpa cap tiga item. Jumlah yang ditampilkan diatur UI. Nama paket quotation berasal dari input klien melalui perubahan terencana §4.3. Kontrak lengkap dan audit implementasi berada di [Phase 17 README](.scratch/phase-17-manager-dashboard/README.md).

Indeks `(status, paid_at)` dan `(expense_category_id, spent_on)` sudah menutup query ini. Kalau suatu hari lambat, bungkus dengan `Cache::remember` beberapa menit, bukan membuat tabel ringkasan.

### 6.10 Jejak aktivitas

Kontrak Phase 17 memakai ledger Eloquent `operator_activities` khusus submission hasil, konfirmasi deadline, dan tahap overdue; dependency baru tidak diperlukan. Dua event tindakan dicatat dalam transaksi Action, overdue melalui scheduler. Event DeadlineConfirmed menjadi marker eligibility persisten, sehingga deadline lama tidak di-backfill. Identitas unik `(type, source_id)`, nullable subject FKs, snapshot nama aman, waktu deteksi/deadline terpisah, dan retensi tanpa purge menjaga attribution dan idempotensi. Detail schema, urutan lock, race policy serta API ada di [Ticket #9](.scratch/phase-17-manager-dashboard/tickets/issue-009-audit-dashboard-contract.md#ledger-aktivasi-dan-overdue); runtime dibangun pada #14–#16. Activitylog generik untuk perubahan harga/approval/payroll pada Phase 18 tetap pekerjaan terpisah, bukan dependency Phase 17.

### 6.11 Inventaris

Penyusutan garis lurus: `(harga beli - nilai sisa) / umur manfaat` per bulan. Nilai buku adalah harga beli dikurangi penyusutan bulanan dikalikan bulan berjalan, tidak pernah di bawah nilai sisa, dan dihentikan di `disposed_on` untuk barang yang sudah terjual atau dibuang. Metode `Inventory::bookValue()` menghitungnya saat dibaca. Angka ini adalah perkiraan untuk pelacakan internal, bukan harga pasar.

### 6.12 CRM dan notifikasi

Penerima CRM disaring dengan `User::canReceiveCrm()`: akun aktif dan `crm_consent_at` terisi. Pengiriman WhatsApp dibungkus satu service sehingga penyedia mudah diganti. Notifikasi aplikasi memakai Laravel Notifications dengan channel database dan email, ditambah satu channel khusus WhatsApp. Pengingat terjadwal (tenggat mendekat, hasil menunggu peninjauan terlalu lama) berjalan lewat scheduler.

## 7. Komponen kode

### 7.1 Yang sudah ada

| Jenis | Komponen |
| --- | --- |
| Enum | 17 enum dan 2 trait |
| Model | 20 model dengan relasi, cast, dan `$fillable` |
| Seeder | `RoomSeeder`, `BasePriceSeeder`, `ExpenseCategorySeeder`, `InventoryCategorySeeder`, `UserSeeder`, `DatabaseSeeder` |
| Action | `RegisterClient`, `CalculatePackagePrice`, `UpdateBasePrices`, `SavePackage` |
| Controller | `AuthController`, `BasePriceController`, `PackageController` |
| Middleware | `EnsureRole`, `EnsureUserIsActive` |
| Request | `RegisterRequest`, `LoginRequest`, `PackageRequest`, `CalculatePackagePriceRequest`, `UpdateBasePricesRequest` |
| Resource | `UserResource`, `BasePriceResource`, `PackageResource` |
| Factory | `UserFactory` dengan state `manager()`, `operator()`, `inactive()` |
| Test | `AuthTest`, `PackagePricingTest` |

Model yang dikeluhkan Larastan soal tipe atribut (`User`, `Quotation`, `ProjectStage`, `Inventory`) memakai properti `$casts`, bukan method `casts()`. Hasil analisis kini bersih.

### 7.2 Yang direncanakan

| Jenis | Komponen |
| --- | --- |
| Action | `HoldSlot`, `ReleaseExpiredHolds`, `CreateCheckout`, `HandlePaymentWebhook`, `SubmitQuotation`, `RespondToQuotation`, `AssignOperators`, `ConfirmStageDeadline`, `SubmitTrackResult`, `ReviewTrackResult`, `RequestRecordingExtension`, `CreateFinalPayment`, `CloseMonth`, `CalculatePayrollRecommendation`, `SendCrmCampaign` |
| Service | Payment gateway (interface dan implementasi), WhatsApp, email |
| Command | `ReleaseExpiredHoldsCommand`, pengingat tenggat, pengingat peninjauan |
| Policy | `Project`, `Quotation`, `TrackStageProgress`, `Payment`, dan lainnya |
| Notification | Penugasan, hasil menunggu peninjauan, revisi diminta, tahap berikutnya siap, tawaran baru, project baru |
| Resource | Katalog paket untuk klien (hanya harga berlaku), project, penawaran, pembayaran |

## 8. Endpoint API

### 8.1 Sudah ada

| Method dan path | Peran | Fungsi |
| --- | --- | --- |
| `POST /api/v1/auth/register` | Publik | Daftar sebagai klien, login otomatis |
| `POST /api/v1/auth/login` | Publik | Login, dibatasi 5 per menit |
| `POST /api/v1/auth/logout` | Login | Mengakhiri sesi |
| `GET /api/v1/auth/me` | Login dan aktif | Data pengguna saat ini |
| `GET /api/v1/manager/base-prices` | Manager | Empat harga dasar berurutan |
| `PUT /api/v1/manager/base-prices` | Manager | Ubah harga dasar, hitung ulang harga otomatis paket |
| `POST /api/v1/manager/packages/calculate-price` | Manager | Pratinjau harga otomatis |
| `GET`, `POST /api/v1/manager/packages` | Manager | Daftar (bisa `?active=`) dan buat paket |
| `GET`, `PUT`, `DELETE /api/v1/manager/packages/{id}` | Manager | Lihat, ubah, hapus (soft delete) |

### 8.2 Rencana

| Kelompok | Cakupan |
| --- | --- |
| Klien | Katalog paket, ketersediaan slot, hold slot, checkout, daftar dan detail project, penawaran (ajukan, tawar, terima, tolak), pembayaran |
| Operator | Tugas saya, konfirmasi tenggat, kirim tautan hasil, ajukan perpanjangan Recording |
| Manager | Daftar project, penugasan operator, peninjauan hasil (setujui, revisi, setujui massal), penawaran masuk, pengeluaran dan kategori, penutupan bulan dan gaji, CRM, inventaris, dashboard, akun operator |
| Webhook | Penerima notifikasi gateway pembayaran, tanpa sesi, diverifikasi lewat tanda tangan |

## 9. Pengujian dan kualitas

Pengujian memakai Pest 4 dengan `RefreshDatabase` pada folder Feature. Karena `RefreshDatabase` menghapus isi database yang dipakai, `phpunit.xml` harus mengarah ke SQLite `:memory:` atau database khusus test, tidak pernah ke database MySQL pengembangan. Test yang butuh perilaku penguncian MySQL (balapan hold slot) perlu dijalankan pada MySQL sungguhan, karena SQLite tidak merepresentasikan `lockForUpdate()` dengan benar.

Test wajib untuk bagian berisiko tinggi: dua hold bersamaan untuk jam yang sama, hold yang kedaluwarsa tidak lagi menghalangi, webhook yang masuk dua kali hanya memproses sekali, tarif perpanjangan tidak berubah setelah harga dasar naik, penerimaan tawaran yang sudah diganti ditolak, total persentase gaji melebihi 100 ditolak, dan klien tidak bisa melihat tautan hasil akhir sebelum lunas.

Alat kualitas: Pint untuk gaya penulisan, Larastan level 5 untuk bug tipe (level dinaikkan bertahap), dan Pest untuk perilaku. Urutan sebelum commit: Pint, Larastan, test. Perintah di Windows ditulis dengan tanda kutip untuk batas versi Composer (misalnya `"pestphp/pest:^4.7"`) karena `^` dapat dibuang oleh terminal.

## 10. Operasional

| Hal | Rencana |
| --- | --- |
| Scheduler | Cron menjalankan `schedule:run` tiap menit. Tugas: lepas hold kedaluwarsa, pengingat tenggat, pengingat peninjauan |
| Queue | Pengiriman email, WhatsApp, dan notifikasi lewat queue, worker dijaga Supervisor |
| Backup | `spatie/laravel-backup` terjadwal ke penyimpanan luar, aktif sejak awal karena ini data keuangan |
| Pemantauan | Sentry untuk error produksi, terutama kegagalan webhook pembayaran |
| Deployment | Satu VPS kecil dengan Nginx, PHP-FPM, MySQL, dan Supervisor. Frontend berupa file statis dari `npm run build` |
| Rahasia | Kunci gateway, kredensial WhatsApp, dan password seeder di `.env`, tidak masuk repository |
