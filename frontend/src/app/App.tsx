import { lazy, Suspense, useEffect, useState } from 'react'
import { BrowserRouter, Route, Routes } from 'react-router-dom'
import { Badge, Button, Card, CardHeader, Icon, Input, PackageCard, PhaseChip, ProjectCard, SegmentedButton, StatCard, Switch } from '../components/ui'
import type { ButtonVariant, IconName } from '../components/ui'
import AuthScreen from '../features/auth/AuthScreen'
import ManagerDashboard from '../features/manager/dashboard/ManagerDashboard'
import { getCurrentUser } from '../lib/auth-api'
import './App.css'

const ClientPortal = lazy(() => import('../features/client/ClientPortal'))
const OperatorPortal = lazy(() => import('../features/operator/OperatorPortal'))

const buttonVariants: readonly ButtonVariant[] = ['filled', 'outlined', 'tonal', 'text', 'dark']

const previewIcons: readonly IconName[] = [
  'activity', 'arrow-right', 'badge-check', 'bell', 'box', 'calendar', 'chart', 'check', 'chevron-down',
  'chevron-left', 'chevron-right', 'chevron-up', 'circle-plus', 'clock', 'credit-card', 'dollar', 'download',
  'edit', 'file', 'filter', 'folder', 'grid', 'handshake', 'home', 'inventory', 'logout', 'menu', 'microphone',
  'more', 'music', 'package', 'plus', 'search', 'scissors', 'settings', 'sliders', 'sparkles', 'trending-down',
  'trending-up', 'user', 'users',
]

function ComponentShowcase() {
  const [selectedFilter, setSelectedFilter] = useState('Semua Project')
  const [switchChecked, setSwitchChecked] = useState(true)
  const [notificationsEnabled, setNotificationsEnabled] = useState(false)

  return (
    <main className="app-root component-showcase">
      <header className="showcase-header">
        <img alt="" className="showcase-logo" src="/tungku-icon.svg" />
        <div>
          <p className="showcase-eyebrow">Tungku Studio · UI KIT</p>
          <h1>Komponen UI</h1>
          <p className="showcase-description">Pustaka komponen reusable berdasarkan desain Tungku ERP.</p>
        </div>
      </header>

      <section aria-labelledby="button-heading" className="showcase-section">
        <div className="showcase-section__heading">
          <p>01 · Aksi</p>
          <h2 id="button-heading">Buttons</h2>
          <span>Filled, outlined, tonal, text, dan tombol dengan ikon.</span>
        </div>
        <div className="showcase-panel showcase-button-grid">
          {buttonVariants.map((variant) => (
            <div className="showcase-example" key={variant}>
              <code>{variant}</code>
              <Button variant={variant}>Text Here</Button>
            </div>
          ))}
          <div className="showcase-example">
            <code>leadingIcon</code>
            <Button leadingIcon="circle-plus">Buat Project</Button>
          </div>
          <div className="showcase-example">
            <code>trailingIcon</code>
            <Button trailingIcon="arrow-right" variant="outlined">Lihat Detail</Button>
          </div>
        </div>
      </section>

      <section aria-labelledby="input-heading" className="showcase-section">
        <div className="showcase-section__heading">
          <p>02 · Formulir</p>
          <h2 id="input-heading">Inputs</h2>
          <span>Field mendukung label, pencarian dengan ikon, pesan bantuan, dan validasi.</span>
        </div>
        <div className="showcase-panel showcase-input-grid">
          <Input aria-label="Nama project" placeholder="Nama project" />
          <Input aria-label="Cari project" leadingIcon="search" placeholder="Cari project, klien, operator..." />
          <Input aria-label="Project dengan validasi" error="Nama project wajib diisi." leadingIcon="search" placeholder="Cari project..." />
        </div>
      </section>

      <section aria-labelledby="badge-heading" className="showcase-section">
        <div className="showcase-section__heading">
          <p>03 · Status</p>
          <h2 id="badge-heading">Badges &amp; filter</h2>
          <span>Warna tahap produksi dan kontrol filter.</span>
        </div>
        <div className="showcase-panel showcase-column">
          <div className="showcase-chip-row">
            <Badge>Default</Badge>
            <Badge tone="primary">Utama</Badge>
            <Badge tone="success">Selesai</Badge>
            <Badge tone="warning">Menunggu</Badge>
            <Badge tone="error">Ditolak</Badge>
          </div>
          <div className="showcase-chip-row">
            <PhaseChip phase="Recording" />
            <PhaseChip phase="Editing" />
            <PhaseChip phase="Mixing" />
            <PhaseChip phase="Mastering" />
          </div>
          <SegmentedButton
            onChange={setSelectedFilter}
            options={['Semua Project', 'Recording', 'Editing', 'Mixing', 'Mastering']}
            value={selectedFilter}
          />
        </div>
      </section>

      <section aria-labelledby="switch-heading" className="showcase-section">
        <div className="showcase-section__heading">
          <p>04 · Preferensi</p>
          <h2 id="switch-heading">Switch</h2>
          <span>Kontrol pilihan on/off yang mendukung keyboard dan teknologi bantu.</span>
        </div>
        <div className="showcase-panel showcase-column showcase-switches">
          <Switch aria-label="Notifikasi email" checked={switchChecked} onChange={setSwitchChecked} />
          <Switch aria-label="Notifikasi SMS" checked={notificationsEnabled} onChange={setNotificationsEnabled} />
        </div>
      </section>

      <section aria-labelledby="cards-heading" className="showcase-section">
        <div className="showcase-section__heading">
          <p>05 · Informasi</p>
          <h2 id="cards-heading">Cards</h2>
          <span>Kartu dashboard, proyek, paket, dan container umum.</span>
        </div>
        <div className="showcase-stat-grid">
          <StatCard icon="trending-up" label="Pendapatan Bulan Ini" tone="success" trend={{ direction: 'up', value: '7%' }} value="Rp 1.243.000" />
          <StatCard icon="trending-down" label="Pengeluaran Bulan Ini" tone="error" trend={{ direction: 'down', value: '5%' }} value="Rp 321.000" />
          <StatCard icon="credit-card" label="Pembayaran Belum Lunas" tone="blue" value="Rp 649.000" />
          <StatCard icon="folder" label="Project Aktif" tone="purple" value={3} />
        </div>
        <div className="showcase-card-grid">
          <ProjectCard
            client="Nadine Aulia"
            date="26 Sep 2026"
            name="Album Senja di Kota"
            onDetails={() => undefined}
            operators={[{ name: 'Raka' }, { name: 'Dimas' }, { name: 'Ayu' }]}
            phase="Recording"
            progress={35}
            progressLabel="Recording berjalan"
          />
          <ProjectCard
            client="Mawar Melati"
            name="Single — Ruang Rindu"
            operators={[{ name: 'Budi' }, { name: 'Sari' }]}
            phase="Editing"
            progress={70}
          />
          <PackageCard
            description="Paket lengkap untuk satu lagu, dari rekaman sampai siap dirilis."
            name="Paket Lengkap A"
            onMenuClick={() => undefined}
            phases={['Recording', 'Editing', 'Mixing', 'Mastering']}
            price="Rp 970.000"
            recordingHours={6}
            songCount={1}
          />
          <Card className="showcase-card-example">
            <CardHeader action={<Button aria-label="Buka semua aktivitas" iconOnly leadingIcon="arrow-right" variant="text">Semua</Button>} title="Aktivitas terbaru" />
            <div className="showcase-card-example__content">
              <Icon className="showcase-card-example__icon" name="badge-check" size={22} />
              <div>
                <strong>Rekaman berhasil diselesaikan</strong>
                <p>Project Album Senja · 2 jam lalu</p>
              </div>
            </div>
          </Card>
        </div>
      </section>

      <section aria-labelledby="icon-heading" className="showcase-section">
        <div className="showcase-section__heading">
          <p>06 · Navigasi</p>
          <h2 id="icon-heading">Icons</h2>
          <span>Ikon Lucide, di-resolve on-demand oleh unplugin-icons.</span>
        </div>
        <div className="showcase-panel showcase-icon-grid">
          {previewIcons.map((name) => (
            <span className="showcase-icon-example" key={name} title={name}>
              <Icon name={name} size={20} />
              <code>{name}</code>
            </span>
          ))}
        </div>
      </section>

      <footer className="showcase-footer">Tungku Studio · Design system components</footer>
    </main>
  )
}

function ManagerDashboardRoute() {
  const [state, setState] = useState<{ status: 'loading' | 'allowed' | 'denied'; message?: string }>({ status: 'loading' })

  useEffect(() => {
    let mounted = true
    void getCurrentUser().then((user) => {
      if (mounted) setState({ status: user.role === 'manager' ? 'allowed' : 'denied' })
    }).catch(() => {
      if (mounted) setState({ status: 'denied', message: 'Silakan login sebagai Manager untuk membuka dashboard ini.' })
    })
    return () => { mounted = false }
  }, [])

  if (state.status === 'loading') return <main aria-live="polite" className="app-root">Memeriksa sesi…</main>
  if (state.status === 'denied') return <main className="app-root"><p role="alert">{state.message ?? 'Dashboard ini hanya tersedia untuk Manager.'}</p><a href="/staff/login">Masuk portal Studio</a></main>
  return <ManagerDashboard />
}

export default function App() {
  const showComponentShowcase = new URLSearchParams(window.location.search).has('ui-kit')

  if (showComponentShowcase) return <ComponentShowcase />

  return (
    <BrowserRouter>
      <Routes>
        <Route element={<Suspense fallback={<main className="app-root" role="status">Memuat portal klien…</main>}><ClientPortal /></Suspense>} path="/client" />
        <Route element={<Suspense fallback={<main className="app-root" role="status">Memuat portal klien…</main>}><ClientPortal /></Suspense>} path="/client/packages" />
        <Route element={<Suspense fallback={<main className="app-root" role="status">Memuat portal klien…</main>}><ClientPortal /></Suspense>} path="/client/booking" />
        <Route element={<Suspense fallback={<main className="app-root" role="status">Memuat portal klien…</main>}><ClientPortal /></Suspense>} path="/client/penawaran" />
        <Route element={<Suspense fallback={<main className="app-root" role="status">Memuat portal klien…</main>}><ClientPortal /></Suspense>} path="/client/project" />
        <Route element={<Suspense fallback={<main className="app-root" role="status">Memuat portal klien…</main>}><ClientPortal /></Suspense>} path="/client/transaksi" />
        <Route element={<ManagerDashboardRoute />} path="/dashboard/*" />
        <Route element={<Suspense fallback={<main className="app-root" role="status">Memuat portal Operator?</main>}><OperatorPortal /></Suspense>} path="/operator" />
        <Route element={<AuthScreen />} path="*" />
      </Routes>
    </BrowserRouter>
  )
}
