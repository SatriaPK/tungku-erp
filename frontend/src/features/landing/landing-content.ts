// Konten halaman pengenalan Studio Tungku (sebelum login).
// Ganti teks, portofolio, dan kontak di bawah dengan data asli studio.
// Daftar paket TIDAK diisi di sini: diambil langsung dari API (paket aktif yang diatur manajer).

export const studioProfile = {
  name: 'Tungku Recording Studio',
  tagline: 'Tempat karya musikmu dibakar sampai matang.',
  about:
    'Tungku Studio adalah studio rekaman yang mendampingi musisi dari sesi rekaman pertama sampai lagu siap dirilis. Kami menangani recording, editing, mixing, dan mastering di satu tempat, dengan operator berpengalaman yang memantau setiap tahap project-mu.',
}

export const services = [
  { icon: 'microphone', title: 'Recording', description: 'Sesi rekaman vokal dan instrumen di ruang yang sudah ditata akustiknya, dibantu operator studio.' },
  { icon: 'scissors', title: 'Editing', description: 'Merapikan take terbaik: timing, tuning, dan pembersihan noise sebelum masuk ke tahap mixing.' },
  { icon: 'sliders', title: 'Mixing', description: 'Menyeimbangkan setiap track agar vokal dan instrumen terdengar jelas dan menyatu.' },
  { icon: 'music', title: 'Mastering', description: 'Sentuhan akhir agar lagu siap diputar di platform streaming dengan level yang konsisten.' },
] as const

export const customerJourney = [
  { title: 'Lihat & pilih paket', description: 'Bandingkan paket berdasarkan jumlah lagu, jam rekaman, dan tahap yang termasuk — atau ajukan penawaran khusus (quotation).' },
  { title: 'Booking jadwal', description: 'Pilih ruang dan jadwal rekaman yang tersedia, lalu bayar PO/DP untuk mengunci slot.' },
  { title: 'Pantau progres', description: 'Ikuti progres project tahap demi tahap dari dashboard klien.' },
  { title: 'Terima hasil akhir', description: 'Setelah pelunasan, unduh file final lagu yang siap dirilis.' },
] as const

// TODO: ganti dengan proyek asli yang pernah dikerjakan studio.
export const portfolio = [
  { title: 'Contoh Proyek — Album', client: 'Nama artis', services: 'Recording · Mixing · Mastering', year: '2026' },
  { title: 'Contoh Proyek — Single', client: 'Nama artis', services: 'Recording · Editing', year: '2026' },
  { title: 'Contoh Proyek — EP', client: 'Nama band', services: 'Mixing · Mastering', year: '2025' },
] as const

// TODO: lengkapi alamat, WhatsApp, dan Instagram asli studio. Item yang kosong tidak ditampilkan.
export const contact = {
  email: 'hello@tungkustudio.com',
  whatsapp: '',
  instagram: '',
  address: '',
  hours: '',
}
