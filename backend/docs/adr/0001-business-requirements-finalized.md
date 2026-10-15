# ADR-0001: Finalisasi Keputusan Bisnis untuk 13 Pertanyaan Terbuka

## Status

Accepted

## Tanggal

8 Oktober 2026

## Konteks

Pada fase perancangan awal (Plan.md bagian 7), terdapat 13 keputusan bisnis yang masih terbuka dan memblokir pengembangan fase 7-19. Keputusan ini mencakup:

1. Jam operasional dan hari libur
2. Durasi hold slot
3. Persentase PO (Down Payment)
4. Satuan penagihan perpanjangan recording
5. Ketersediaan pembayaran manual (tunai)
6. Pilihan payment gateway
7. Pratinjau hasil sebelum pelunasan
8. Masa berlaku kesepakatan harga
9. Batas ronde tawar-menawar
10. Persetujuan biaya perpanjangan oleh klien
11. Penyimpanan riwayat revisi
12. Bobot tahap untuk perhitungan progress
13. Aturan pembatalan dan refund PO

Tanpa jawaban atas pertanyaan ini, implementasi fitur inti (booking, checkout, eksekusi tahap) tidak dapat dilanjutkan karena logika bisnis tidak lengkap.

## Keputusan

Seluruh 13 pertanyaan telah dijawab melalui sesi grilling dengan pemilik studio pada 8 Oktober 2026. Berikut adalah keputusan final untuk masing-masing:

### Q1: Jam Operasional Studio

**Keputusan**: Senin-Minggu, 09:00-22:00 (setiap hari, tanpa hari libur tetap).

**Implikasi**:
- Validasi slot di `HoldSlot` action harus cek rentang jam
- Disimpan sebagai konfigurasi di tabel `studio_settings` agar Manager bisa ubah via UI
- Format: JSON `{"monday": "09:00-22:00", "tuesday": "09:00-22:00", ...}`

### Q2: Durasi Hold Slot

**Keputusan**: 15 menit (default), dapat diubah oleh Manager melalui pengaturan studio (range: 10-30 menit).

**Implikasi**:
- Kolom `hold_duration_minutes` di tabel `studio_settings`
- Formula `held_until`: `now() + getSetting('hold_duration_minutes')`
- Command `ReleaseExpiredHolds` berjalan setiap menit via scheduler

### Q3: Persentase PO (Down Payment)

**Keputusan**: 50% dari harga paket (default), dapat diubah oleh Manager via pengaturan (range: 30-70%).

**Implikasi**:
- Kolom `po_percentage` di tabel `studio_settings`
- Perhitungan `po_amount` di checkout: `price * (getSetting('po_percentage') / 100)`
- Persentase disalin ke `package_snapshot` saat project dibuat agar tidak terpengaruh perubahan setting

### Q4: Satuan Penagihan Perpanjangan Recording

**Keputusan**: Per jam penuh dengan pembulatan ke atas.

**Implikasi**:
- Formula: `extra_fee = ceil(duration_minutes / 60) * hourly_rate`
- Contoh: perpanjangan 15 menit = 1 jam penuh
- Klien dan studio bisa nego offline untuk durasi sangat pendek (tidak dihandle sistem)

### Q5: Pembayaran Manual (Tunai)

**Keputusan**: Ya, harus ada. Klien datang ke studio, bayar tunai, Manager konfirmasi via sistem.

**Implikasi**:
- Enum `PaymentProvider::Cash`
- Status `PaymentStatus::PendingManualConfirmation`
- Action `ConfirmCashPayment` (Manager only)
- Endpoint `POST /api/v1/manager/payments/{id}/confirm-cash`
- Slot tetap `held` sampai Manager konfirmasi pembayaran tunai

### Q6: Provider Payment Gateway

**Keputusan**: Midtrans.

**Implikasi**:
- Interface `PaymentGatewayInterface` dengan implementasi `MidtransGateway`
- Pakai Snap API untuk redirect
- Webhook signature verification wajib
- Testing di sandbox Midtrans

### Q7: Pratinjau Hasil Sebelum Pelunasan

**Keputusan**: Tidak perlu. Klien bisa datang offline ke studio jika ingin dengar hasil sebelum bayar.

**Implikasi**:
- Tidak ada kolom `preview_url` di `track_stage_progress`
- Policy `viewFinalResult`: klien hanya bisa akses setelah project `completed`
- Manager dan operator bisa lihat kapan saja

### Q8: Masa Berlaku Kesepakatan Harga

**Keputusan**: Tidak ada batas waktu. Harga kesepakatan berlaku sampai project selesai.

**Implikasi**:
- Tidak ada kolom `valid_until` di tabel `quotations`
- Tidak ada validasi expiry saat klien pilih slot dari quotation yang sudah lama

### Q9: Batas Ronde Tawar-Menawar

**Keputusan**: Tidak ada batasan. Negosiasi bisa berlangsung sampai salah satu pihak accept/decline.

**Implikasi**:
- Tidak ada validasi `max_rounds` di `RespondToQuotation` action
- Sistem tidak auto-decline quotation setelah X ronde

### Q10: Persetujuan Biaya Perpanjangan oleh Klien

**Keputusan**: Operator yang input perpanjangan (atas permintaan klien secara offline). Tidak perlu approval eksplisit dari klien via sistem, tapi wajib notifikasi real-time.

**Implikasi**:
- Button "Request Extension" ada di UI Operator (bukan Klien)
- Saat perpanjangan dibuat, kirim notifikasi email + in-app ke klien dengan rincian biaya
- Biaya langsung masuk ke tagihan pelunasan tanpa approval flow
- Klien tidak bisa reject perpanjangan via sistem (nego offline jika ada masalah)

### Q11: Penyimpanan Riwayat Revisi

**Keputusan**: Simpan histori lengkap per track per stage (semua versi submit + review notes).

**Implikasi**:
- Tabel baru: `track_stage_submissions`
- Setiap `SubmitTrackResult` membuat row baru dengan `version` auto-increment
- Tabel `track_stage_progress` tetap punya `result_url` (versi terbaru/approved) untuk query cepat
- Endpoint GET history: `/operator/tasks/{stageId}/tracks/{trackId}/history`

**Struktur tabel**:
```
track_stage_submissions:
- id
- track_stage_progress_id (FK)
- version (auto-increment per progress)
- result_url
- review_notes (nullable, diisi Manager saat revisi)
- submitted_by (operator_id)
- submitted_at
- unique(track_stage_progress_id, version)
```

### Q12: Bobot Tahap untuk Perhitungan Progress

**Keputusan**: Bobot sama rata per stage, dihitung dari sel (track × stage) yang approved.

**Implikasi**:
- Formula: `progress = (approved_cells / total_cells) * 100`
- Contoh: 2 track, 3 stage aktif → 6 sel total
  - Recording track-1 approved: 1/6 = 16.67%
  - Recording track-2 approved: 2/6 = 33.33%
  - Editing track-1 approved: 3/6 = 50%
- Implementasi sudah ada di `Project::progressPercent()`
- Tidak perlu perubahan

### Q13: Aturan Pembatalan dan Refund PO

**Keputusan**: Tidak ada refund otomatis. Pembatalan hanya oleh Manager dengan pertimbangan case-by-case. Status project jadi `cancelled` tapi payment tetap `paid`. Refund manual di luar sistem.

**Implikasi**:
- Tidak ada action `RefundPayment`
- Manager bisa ubah status project ke `cancelled` setelah PO paid
- Jika perlu refund, Manager catat di notes dan lakukan transfer manual
- Tidak ada kolom `refund_amount` atau `refund_at` di tabel `payments`

---

### Keputusan Tambahan (Follow-up)

Selama sesi, muncul 7 pertanyaan tambahan (Q14-Q20) yang juga telah dijawab:

**Q14**: Studio Settings UI - Manager harus bisa edit po_percentage, hold_duration, dan operating_hours via UI.

**Q15**: Flow Perpanjangan - Operator yang input (button di UI Operator), notifikasi ke klien, no approval needed.

**Q16**: Struktur Riwayat - Per track-stage (bukan per project), semua versi + review notes tersimpan.

**Q17**: Formula Progress - Sudah benar di code (approved_cells / total_cells).

**Q18**: Pembayaran Tunai - Klien datang offline, Manager konfirmasi via sistem, bukan upload bukti transfer.

**Q19**: Notifikasi Channel - Email + In-app untuk fase 1, WhatsApp planning untuk fase 2.

**Q20**: Deployment - Belum ada VPS, demo lokal dulu sampai studio siap invest.

## Konsekuensi

### Perubahan Skema Database

1. **Tabel baru: `studio_settings`**
   - Menyimpan konfigurasi yang bisa diubah Manager: po_percentage, hold_duration_minutes, operating_hours
   - Struktur: key-value dengan tipe data (integer, decimal, json)

2. **Tabel baru: `track_stage_submissions`**
   - Menyimpan histori semua versi submit hasil kerja per track per stage
   - Relasi: track_stage_progress → many submissions

3. **Enum update: `PaymentProvider`**
   - Tambah case `Cash` untuk pembayaran tunai

4. **Enum update: `PaymentStatus`**
   - Tambah case `PendingManualConfirmation` untuk pembayaran tunai yang menunggu konfirmasi Manager

### Fitur Baru yang Harus Dibangun

1. **Studio Settings Management** (Fase 7)
   - CRUD endpoint untuk Manager ubah pengaturan
   - Validasi range (po: 30-70%, hold: 10-30 menit)

2. **Cash Payment Flow** (Fase 9)
   - UI checkout: pilihan "Tunai" atau "Gateway"
   - Dashboard Manager: list pembayaran pending manual confirmation
   - Action ConfirmCashPayment dengan Policy

3. **Revision History** (Fase 12)
   - Setiap submit hasil kerja buat row baru di track_stage_submissions
   - Endpoint GET history untuk Operator dan Manager
   - Resource TrackStageSubmissionResource

### Task Baru di Task.md

- **Fase 6A** (insert setelah Fase 6): Migration studio_settings, track_stage_submissions, enum updates
- **Fase 7** (update): Studio Settings CRUD dengan validasi ketat
- **Fase 9** (update): Tambah flow pembayaran tunai + konfirmasi Manager
- **Fase 12** (update): Setiap submit simpan ke track_stage_submissions, endpoint history

### Tidak Dibangun (Keluar dari Scope)

1. ❌ Kolom `preview_url` di track_stage_progress
2. ❌ Kolom `valid_until` di quotations
3. ❌ Validasi `max_rounds` di penawaran
4. ❌ Action `RefundPayment` otomatis
5. ❌ Approval flow perpanjangan recording
6. ❌ Upload bukti transfer untuk pembayaran tunai (cukup konfirmasi Manager)
7. ❌ WhatsApp notification (ditunda ke fase 2)

## Referensi

- Sesi grilling: 8 Oktober 2026, 20:25 - 20:55 WIB
- Plan.md bagian 7: "Keputusan yang masih terbuka"
- Task.md: Fase 6 (13 keputusan menunggu jawaban)
