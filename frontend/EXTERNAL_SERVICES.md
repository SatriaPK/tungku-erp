# Integrasi eksternal frontend

Backend Laravel tidak diubah. Fitur eksternal belum terhubung sampai konfigurasi dan kontrak penyedia dikonfirmasi. Tidak ada data demo atau keberhasilan mutasi lokal.

Tambahkan konfigurasi publik di `.env.local`, lalu restart Vite:

```dotenv
VITE_NOTIFICATION_WS_URL=
VITE_SUBMISSION_UPLOAD_URL=
VITE_MANAGEMENT_API_URL=
VITE_EXTERNAL_WITH_CREDENTIALS=false
```

## Kontrak adapter sementara

- WebSocket: URL `wss://` penyedia, autentikasi ditangani layanan. Setiap pesan memicu pembacaan inbox Laravel yang terautentikasi, dibatasi sekali per dua detik. Pesan tidak ditampilkan langsung. Reconnect memakai backoff maksimal 30 detik; polling 30 detik tetap berjalan sebagai rekonsiliasi. Tanpa publisher yang meneruskan perubahan Laravel, layanan WebSocket tidak bisa mengetahui notifikasi baru. Protokol Pusher/Echo memerlukan adapter khusus setelah detail penyedia tersedia.
- Upload: POST multipart dengan field `file`, maksimal 100 MB, respons `{ "url": "https://..." }` atau `{ "data": { "url": "https://..." } }`. Link harus dapat dibuka reviewer melalui autentikasi layanan yang sesuai; jangan gunakan link yang kedaluwarsa sebelum review. Upload tidak langsung mengirim submission: pengguna memeriksa link lalu menekan Kirim Hasil pada endpoint Laravel yang sudah ada. File yang sudah diupload tetapi belum dikirim tetap berada pada penyedia; retensi/penghapusannya mengikuti penyedia.
- Management: base URL tanpa `/` terakhir; GET/POST `/rooms`, `/users`, `/projects`; PATCH/DELETE `/{resource}/{id}`. GET menerima `page` dan mengembalikan array atau `{ data: [], meta: { last_page: 1 } }`. Form mengirim `name`, room/user juga `is_active`, user juga `email`. Field tambahan, role, password, dan alur pembuatan project harus disesuaikan dengan kontrak penyedia sebelum layanan diaktifkan. Data eksternal tidak otomatis tersinkron ke Laravel.
- Credentials hanya diaktifkan bila `VITE_EXTERNAL_WITH_CREDENTIALS=true`. Layanan harus memvalidasi session, role Manager, CORS, serta CSRF untuk mutasi yang memakai cookie. Penjagaan route frontend bukan otorisasi server. Tidak ada penyalinan session/token Laravel ke layanan eksternal.

Jangan masukkan secret, server key, atau admin token dalam variabel `VITE_*`: semuanya terlihat di browser. Jika penyedia memerlukan secret server, perlu endpoint server milik penyedia yang aman; frontend tidak dapat menyimpan secret tersebut.

Referensi browser: [WebSocket](https://developer.mozilla.org/en-US/docs/Web/API/WebSocket), [FormData](https://developer.mozilla.org/en-US/docs/Web/API/FormData).
