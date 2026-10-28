# Komponen UI

Komponen reusable dipisah berdasarkan jenis komponen supaya implementasi dan styling dapat diedit tanpa mengganggu komponen lain.

```text
src/components/ui/
├── badge/                 # Badge dan phase chip
├── button/                # Button dan segmented button
├── card/                   # Card dan card header
├── icon/                   # Adapter ikon dari unplugin-icons (koleksi Lucide)
├── input/                  # Input berlabel, ikon, pesan hint/error
├── loading-screen/         # Loading screen dengan logo terisi animasi
├── package-card/           # Kartu paket studio
├── project-card/           # Kartu progress proyek
├── stat-card/              # Kartu metrik/statistik
├── switch/                 # Switch aksesibel
├── index.ts                # Public barrel ekspor
└── styles.css              # Style semua primitive UI
```

Gunakan komponen lewat barrel publik `src/components/ui/index.ts`, atau impor dari barrel folder masing-masing. Style dimuat sekali melalui entry aplikasi. Teks/label antarmuka ditulis dalam bahasa Indonesia; nama stage dan API komponen mengikuti istilah desain dan model domain.

## Ikon

Ikon dirender menggunakan `unplugin-icons` dan koleksi Lucide, tanpa inline SVG path manual. Plugin Vite dikonfigurasi dalam `vite.config.js`; koleksi Iconify ada di devDependencies sebagai `@iconify-json/lucide`. Daftar nama ikon yang didukung adapter terdapat di `src/components/ui/icon/Icon.tsx`.

```tsx
import { Button, Icon, Input, PhaseChip, StatCard } from './components/ui'

<Button leadingIcon="plus">Buat Project</Button>
<Input aria-label="Cari project" leadingIcon="search" placeholder="Cari project..." />
<PhaseChip phase="Recording" />
<StatCard icon="trending-up" label="Pendapatan Bulan Ini" value="Rp 1.243.000" />
<Icon aria-label="Notifikasi" name="bell" />
```

`LoadingScreen` dapat dipakai sebagai fallback untuk route/lazy page atau proses pemuatan layar penuh:

```tsx
<LoadingScreen label="Memuat project" />
```

Gunakan `className` dan design tokens yang sudah ada ketika membuat variasi, lalu tambahkan props baru hanya saat variasi komponen memang diperlukan.

## Design tokens

`src/styles/tokens.css` adalah sumber token untuk warna, tipografi, spacing, radius, elevation, dan ukuran ikon/touch-target. Nilainya mengikuti referensi di `docs/ui_design/Color.png`, `Typhography.png`, `Spacing_and_Sizing.png`, dan `Component_Guidelines.png`. Gunakan custom properties `--ts-*` daripada mengulang nilai ukuran atau bayangan di stylesheet komponen.

- Spacing memakai grid dasar 4px (`--ts-space-1` sampai `--ts-space-12`).
- Radius tersedia dari none/XS sampai XL dan full (`--ts-radius-*`).
- Elevation tersedia pada level 0–5 (`--ts-elevation-*`).
- Tipografi DM Sans memakai type scale Display, Headline, Title, Body, dan Label, termasuk size, weight, line-height, serta letter-spacing.
- Ukuran ikon dan touch target menggunakan `--ts-icon-*` dan `--ts-target-*`.