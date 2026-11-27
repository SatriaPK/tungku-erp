# ADR 0001: Dashboard StatCard 1

- **Status:** Accepted
- **Date:** 2026-10-29

## Context

Ringkasan dashboard Manager menggabungkan nilai finansial dan hitungan operasional. Kartu membutuhkan hierarki yang konsisten serta pintasan ke area kerja terkait.

## Decision

Gunakan satu layout StatCard 1 responsif: badge dan panah berada di baris atas; label, nilai utama, dan ikon 48px berada di baris bawah. Seluruh kartu adalah tautan. Badge finansial menunjukkan perubahan bulanan dan warna mengikuti dampak bisnis (pendapatan naik/pengeluaran turun positif; pendapatan turun/pengeluaran naik negatif). Badge operasional menunjukkan jumlah project yang menunggu pelunasan akhir, track pada project aktif, atau quotation yang menunggu klien.

Pintasan kartu adalah Pendapatan ke `/laporan`, Pengeluaran ke `/pengeluaran`, Pembayaran Belum Lunas ke `/invoice`, Project Aktif ke `/project`, dan Dalam Penawaran ke `/quotation`. Kartu pembayaran tetap memakai jumlah project berstatus `awaiting_final_payment`; kartu penawaran memakai jumlah `awaiting_client`, yang dihitung sebagai `open_count - awaiting_studio_count` dari kontrak dashboard saat ini.

## Consequences

Kartu dapat digunakan sebagai satu target navigasi aksesibel dan badge mengomunikasikan konteks metrik tanpa menambah endpoint. Jika definisi status quotation dashboard berubah, formula jumlah menunggu klien harus ditinjau kembali. Route halaman tujuan saat ini merupakan placeholder Manager.