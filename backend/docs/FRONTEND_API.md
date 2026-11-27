# Panduan API untuk frontend ERP Tungku

Dokumentasi ini mengikuti implementasi backend fase 18. Mulai dari panduan ini, lalu gunakan [referensi semua endpoint dan field](API_REFERENCE.md) atau impor [openapi.json](openapi.json) ke Postman/Insomnia. Halaman interaktif tersedia di `/docs/api`, JSON di `/docs/api.json`. Di luar lingkungan `local`/`testing`, keduanya hanya dapat dibuka Manager aktif.

## Base URL dan autentikasi

Gunakan origin backend sebagai `API_ORIGIN`, misalnya `http://localhost:8000`. Prefix endpoint aplikasi adalah `/api/v1`; endpoint kompatibilitas `/api/user` juga masih tersedia. Tidak ada endpoint login yang menerbitkan bearer token. Frontend SPA menggunakan cookie session Sanctum.

Urutan login:

1. `GET /sanctum/csrf-cookie`, dengan credentials/cookies.
2. Ambil cookie `XSRF-TOKEN`, URL-decode nilainya, kirim sebagai header `X-XSRF-TOKEN`.
3. `POST /api/v1/auth/login`, body `{ "email": "client@example.test", "password": "kata-sandi", "remember": false }`.
4. `GET /api/v1/auth/me` untuk profil dan role. Role adalah `client`, `operator`, atau `manager`.
5. Kirim cookie pada semua request; kirim `X-XSRF-TOKEN` pada mutasi.
6. `POST /api/v1/auth/logout` mengakhiri session, response 204 tanpa body. Logout tetap tersedia untuk akun nonaktif.

```js
const API_ORIGIN = 'http://localhost:8000';

function xsrfToken() {
  const cookie = document.cookie.split('; ').find(v => v.startsWith('XSRF-TOKEN='));
  return cookie ? decodeURIComponent(cookie.substring('XSRF-TOKEN='.length)) : '';
}

async function api(path, { method = 'GET', body } = {}) {
  const headers = { Accept: 'application/json' };
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) headers['X-XSRF-TOKEN'] = xsrfToken();
  const response = await fetch(`${API_ORIGIN}${path}`, {
    method, headers, credentials: 'include',
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const data = response.status === 204 ? null : await response.json();
  if (!response.ok) {
    throw Object.assign(new Error(data?.message ?? 'Request gagal'), {
      status: response.status, errors: data?.errors,
      retryAfter: Number(response.headers.get('Retry-After') ?? 0),
    });
  }
  return data;
}

await fetch(`${API_ORIGIN}/sanctum/csrf-cookie`, {
  credentials: 'include', headers: { Accept: 'application/json' },
});
await api('/api/v1/auth/login', {
  method: 'POST', body: { email: 'client@example.test', password: 'kata-sandi' },
});
const { data: user } = await api('/api/v1/auth/me');
```

Konfigurasi backend harus mencocokkan origin frontend: `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS` (host beserta port), dan domain cookie session bila memakai subdomain. CORS mendukung credentials; default origin frontend adalah `http://localhost:5173`. Tambahkan host/port frontend tersebut pada daftar stateful bila belum ada. Gunakan nama host yang konsisten; `localhost` dan `127.0.0.1` bukan domain cookie yang sama. Frontend dan API SPA perlu berbagi domain induk agar cookie CSRF dapat dibaca frontend.

Registrasi `POST /api/v1/auth/register` membuat akun Client dan langsung login:

```json
{
  "name": "Klien Contoh",
  "email": "client@example.test",
  "phone": "081234567890",
  "password": "contoh-password",
  "password_confirmation": "contoh-password",
  "crm_consent": false
}
```

Password minimal 8 karakter; nomor seluler Indonesia dinormalisasi ke `628...`. `crm_consent` harus eksplisit boolean, boleh false. Hapus consent menggunakan `DELETE /api/v1/auth/crm-consent` (204).

## Konvensi response

Resource umumnya dibungkus `{ "data": ... }`. Collection non-paginated memakai array `data`. Collection paginated memiliki `data`, `links`, dan `meta`. Endpoint command tertentu memakai envelope khusus; lihat response per endpoint pada referensi. Jangan mengasumsikan semua response memiliki `data` atau pagination.

```json
{
  "data": [],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 0 }
}
```

Contoh di atas diringkas; schema lengkap mencantumkan field pagination lainnya. Gunakan `page`; gunakan `per_page` hanya pada endpoint yang mendukungnya. Dashboard mengembalikan daftar lengkap sesuai kontrak, bukan paginator.

- ID umumnya integer; ID notifikasi adalah UUID string.
- Nominal rupiah umumnya integer, tanpa pecahan sen. Persentase payroll berupa string desimal; jangan mengubahnya menjadi uang.
- Field optional bisa tidak dikirim, khususnya relationship yang tidak dimuat. Bedakan field tidak hadir dari `null`.
- Tanggal kalender seperti `spent_on`, `purchase_date`, `issue_date` memakai `YYYY-MM-DD`.
- Default waktu bisnis adalah Asia/Jakarta. Kirim timestamp dengan offset, misalnya `2027-01-20T10:00:00+07:00`. Payment/booking/inbox/dashboard memakai ISO dengan offset WIB. Beberapa Resource Eloquent legacy, misalnya CRM, dapat mengembalikan ISO UTC `Z`; parse offset yang dikirim, jangan menambah tujuh jam secara manual.
- Gunakan nilai `status` sebagai state aplikasi, `status_label` hanya untuk tampilan. Label dapat berubah tanpa mengubah nilai enum.

## Error dan retry

| Status | Penanganan frontend |
| --- | --- |
| 400 | Request/provider tidak didukung pada endpoint terkait. |
| 401 | Session hilang, atau signature webhook tidak valid. Untuk SPA, minta login. |
| 403 | Role, ownership, status akun, atau operasi tidak diizinkan. |
| 404 | Resource tidak tersedia bagi request ini; jangan berasumsi ID pasti ada. |
| 419 | CSRF/session kedaluwarsa. Ambil cookie CSRF kembali dan periksa session. |
| 422 | Validasi field atau aturan bisnis gagal; tampilkan `message`/`errors`. |
| 429 | Baca `Retry-After` dalam detik. Tunggu sebelum mencoba lagi. |
| 500 | Error tak terduga; jangan otomatis mengulang mutasi pembayaran. |

```json
{
  "message": "The given data was invalid.",
  "errors": { "deadline_at": ["Tenggat harus berada di masa depan."] }
}
```

Pesan aktual dapat berbeda per aturan. Kredensial login salah menggunakan 422. Tidak semua error membawa `errors`; selalu sediakan fallback `message`. Endpoint tidak menyediakan header idempotency umum: setelah timeout mutasi, baca state terbaru sebelum mengulang.

Rate limit per menit:

| Kelompok | Batas | Bucket |
| --- | ---: | --- |
| Login | 5 | Email ternormalisasi + IP |
| Semua percobaan login | 30 | IP, walaupun email berganti |
| Register | 10 | IP |
| Inisiasi pembayaran invoice/final | 5 | User, bersama lintas ID |
| Hold/confirm/cancel/perpanjangan booking | 10 | User |
| Submit/respond quotation | 20 | User |
| Assignment/deadline/submission/review | 30 | User |
| Confirm/cancel/refund payment dan payroll write | 10 | User |
| Dispatch campaign | 5 | User |
| Inbox read/write | 60 / 30 | Dua bucket per user |
| Dashboard/activity/finance summary/recommendations/availability | 60 | User |

Header `Retry-After`, `X-RateLimit-Limit`, dan `X-RateLimit-Remaining` diekspos melalui CORS. Limit tidak bergantung ID resource. Webhook provider tidak memakai bucket pengguna.

## Alur Client

Gunakan katalog `GET /client/packages`, booking `/client/bookings`, quotation `/client/quotations`, invoice `/client/invoices`, dan payment `/client/payments/...`, semuanya relatif ke `/api/v1`.

Booking:

```json
{
  "room_id": 1,
  "start_at": "2027-01-20T10:00:00+07:00",
  "end_at": "2027-01-20T12:00:00+07:00",
  "package_id": 1
}
```

Kirim tiga field jadwal untuk `POST /client/bookings/check-availability`; tambahkan salah satu `package_id` atau `quotation_id` untuk `POST /client/bookings/hold`. `quotation_id` harus milik Client, accepted, dan belum dipakai project. Durasi booking 1-12 jam, waktu mulai harus di masa depan. Hasil availability bukan reservasi; hanya hold yang menyimpan booking. `held_until` menentukan batas hold.

`POST /client/bookings/confirm` menerima `{ "recording_session_id": 1 }` dan hanya boleh berhasil setelah pembayaran terkait paid serta hold masih berlaku. Frontend tidak boleh menganggap redirect dari gateway sebagai bukti lunas; baca status payment kembali.

`POST /client/invoices/{invoice}/initiate-payment` menerima `{ "gateway": "midtrans" }` atau `xendit`. Endpoint invoice publik saat ini belum menerima cash, walaupun Action internal mendukungnya. `POST /client/payments/{payment}/final/initiate` menerima `{ "provider": "midtrans" }`, `xendit`, atau `cash`. Manual berarti pembayaran tunai, dikonfirmasi Manager; bukan transfer bank manual.

Quotation memakai round-trip offer: kirim `latest_offer_id` yang terakhir dibaca bersama `response_type` (`accept`, `decline`, `counter`). Counter juga membutuhkan `price`; kirim ulang pembacaan detail jika offer sudah usang. Nilai awal `offer_price` berbeda dari field counter `price`.

Final delivery tersedia melalui `GET /client/projects/{project}/final-delivery` setelah akses diizinkan policy pelunasan. Link hasil adalah data privat; jangan mengirimkannya ke analytics/log browser.

## Alur Operator dan Manager produksi

Daftar Project Manager menggunakan `GET /api/v1/manager/projects`. Query opsional: `search` (nama project/klien), `status`, `stage` (tahap yang disertakan project), `sort` (`newest`, `oldest`, `name_asc`, `name_desc`) dan `per_page` (1–100, default 15). Urutan terbaru/terlama memakai tanggal pembuatan project. Response berisi project paginated dengan klien, status, tahap saat ini/operator, tanggal mulai, jumlah track, dan progress persetujuan track.

Manager menugaskan seluruh tahap aktif project sekaligus:

```json
{ "assignments": { "recording": 10, "editing": 11, "mixing": 12, "mastering": 13 } }
```

Kirim ke `POST /manager/projects/{project}/assignments`. Hanya tahap dalam snapshot project; operator tiap tahap harus aktif dan berbeda. Project siap ditugaskan setelah PO paid.

Operator memakai `POST /operator/stages/{stage}/confirm-deadline` dengan `{ "deadline_at": "2027-01-21T17:00:00+07:00" }`. Tenggat hanya sekali untuk tahap yang memerlukannya dan siap. Submission dikirim sebagai link, bukan upload berkas:

```json
{ "result_url": "https://example.test/results/track-1" }
```

Endpoint: `POST /operator/track-progress/{progress}/tracks/{track}/submissions`. Setiap revisi menghasilkan versi submission baru. Manager review melalui `POST /manager/track-progress/{progress}/submissions/{submission}/review`:

```json
{ "decision": "request_revision", "review_notes": "Perbaiki keseimbangan level vokal." }
```

`decision` adalah `approve` atau `request_revision`; catatan revisi wajib 10-5000 karakter. Approval terakhir membuka tahap selanjutnya atau membuat tagihan pelunasan. Perpanjangan recording menerima `room_id`, `start_at`, `end_at` di `/operator/stages/{stage}/recording-extensions`; biaya dihitung backend dan masuk pelunasan.

## Keuangan, CRM dan inventaris Manager

- `/manager/finance/monthly-summary?year=2026&month=9`: ringkasan bulanan.
- `/manager/finance/payroll-allocations`: GET dengan year/month; PUT dengan year/month, allocations (`operator_id`, `percentage`), dan optional `studio_profit_percentage`.
- `/manager/finance/payroll-finalization`: POST year/month untuk periode yang sudah berakhir. Snapshot finalized tidak boleh diedit.
- Expense/category, inventory/category, package, base-price dan setting: lihat seluruh operasi CRUD serta field wajib di referensi. PATCH resource memakai validator yang sama dengan PUT.
- Cash dikonfirmasi melalui `/manager/payments/{payment}/confirm`, body `{ "note": "Tunai diterima", "paid_at": "2026-10-24T10:00:00+07:00" }`; paid_at optional dan tidak boleh di masa depan.
- **Refund dilakukan di luar sistem (ADR Q13).** Endpoint refund kompatibilitas tetap menolak refund baru; frontend tidak menyediakan alur refund in-system. Status `refunded` hanya diperlukan untuk menampilkan histori legacy.
- CRM: buat campaign dengan `name`, `message`, `channels: ["email"]`, `client_ids` (1-500), optional `package_id`; dispatch melalui `/manager/crm/campaigns/{campaign}/dispatch` (202). Semua penerima harus aktif dan mempunyai consent. WhatsApp belum tersedia.

Dashboard `/manager/dashboard` menyertakan metrik, jadwal, progress project/quotation dan aktivitas operator. Nilai `percentage` MoM bisa null bila pembanding nol; tampilkan alasan/placeholder, jangan ubah menjadi 0%. Aktivitas operator bukan audit generik seluruh bisnis.

## Inbox bersama

Tersedia untuk ketiga role aktif:

| Operasi | Endpoint | Request |
| --- | --- | --- |
| Daftar | GET `/notifications` | `unread=1` atau `0`, `page`, `per_page` 1-100 (default 15) |
| Jumlah belum dibaca | GET `/notifications/unread-count` | Tanpa body |
| Tandai satu | PATCH `/notifications/{uuid}/read` | Tanpa body |
| Tandai semua yang sudah ada | PATCH `/notifications/read-all` | Tanpa body |

Jumlah: `{ "data": { "unread_count": 3 } }`. Bulk: `{ "data": { "updated_count": 3 } }`. GET tidak menandai dibaca. PATCH berulang mempertahankan read_at pertama; notifikasi baru yang masuk setelah bulk tetap unread. Target pengguna lain menghasilkan 404.

```json
{
  "data": [{
    "id": "00000000-0000-4000-8000-000000000001",
    "type": "stage_assigned",
    "data": {
      "schema_version": 1,
      "event_key": "stage:12:assigned",
      "type": "stage_assigned",
      "title": "Penugasan tahap",
      "body": "Penugasan tahap",
      "target": { "type": "stage", "id": 12 },
      "metadata": { "project_id": 1, "stage_id": 12 }
    },
    "read_at": null,
    "created_at": "2026-10-24T10:00:00+07:00"
  }],
  "links": {},
  "meta": {}
}
```

Contoh sintetis diringkas; label mengikuti response aktual. Map `target.type` ke route UI sendiri, jangan memperlakukannya sebagai URL dari server. Notifikasi legacy pembayaran/invoice mempunyai payload berbeda (misalnya payment_id/order_id/amount). Tipe tidak dikenal dikembalikan sebagai `notification` dengan data `[]`; frontend harus menyediakan tampilan fallback. Polling boleh memakai unread-count; tidak ada WebSocket endpoint dalam kontrak ini.

## Enum utama

| Domain | Nilai |
| --- | --- |
| Project | pending_payment, awaiting_assignment, in_progress, awaiting_final_payment, completed, cancelled |
| Payment | pending, pending_manual_confirmation, paid, failed, expired, cancelled, refunded (legacy) |
| Quotation | awaiting_studio, awaiting_client, accepted, declined |
| Tahap | recording, editing, mixing, mastering |
| Status tahap | locked, ready, in_progress, completed |
| Progress track | pending, in_review, revision, approved |
| Review | approve, request_revision |

## Memperbarui dokumentasi

Jalankan `php artisan api:docs` pada lingkungan pengembangan yang mempunyai schema database hasil migration. Command ini mengekspor `docs/openapi.json` dan `docs/API_REFERENCE.md`; tidak memigrasi database atau membaca data akun. Generator memerlukan metadata schema, jadi database development harus dapat diakses. Untuk memverifikasi tanpa database aplikasi, jalankan `php artisan test --compact tests/Feature/ApiDocumentationTest.php`; test memakai SQLite memory dan menulis artifact sementara di `storage/app/private`.

Referensi mencakup endpoint provider untuk kelengkapan route. Frontend tidak memanggil webhook pembayaran/Resend dan tidak menyimpan server key atau signature provider. Tidak ada endpoint publik pengelolaan room/user/project generik di luar daftar; jangan membuat UI yang bergantung endpoint yang belum tersedia.
