import { useEffect, useState, type ReactNode } from 'react'
import { Link, NavLink, useLocation, useNavigate } from 'react-router-dom'
import { Button, Icon, Input } from '../../../components/ui'
import type { IconName } from '../../../components/ui'
import { getCurrentUser, getManagerDashboard } from '../../../lib/manager-dashboard-api'
import type { ManagerDashboardData, ManagerUser } from '../../../lib/manager-dashboard-api'
import ManagerWorkspace from '../workspace/ManagerWorkspace'
import ProfileDialog from '../../auth/ProfileDialog'
import tungkuIcon from '../../../assets/tungku-icon.svg'
import { logout } from '../../../lib/auth-api'
import './ManagerDashboard.css'

const navigationGroups: Array<{ title: string; items: Array<{ label: string; icon: IconName; badge?: string }> }> = [
  { title: 'UTAMA', items: [{ label: 'Dashboard', icon: 'grid' }] },
  { title: 'OPERASIONAL', items: [{ label: 'Booking', icon: 'calendar' }, { label: 'Quotation', icon: 'handshake', badge: '2' }, { label: 'Project', icon: 'folder', badge: '2' }, { label: 'Inventaris', icon: 'inventory' }] },
  { title: 'PENJUALAN', items: [{ label: 'Manajemen Paket', icon: 'package' }, { label: 'CRM', icon: 'users' }] },
  { title: 'KEUANGAN', items: [{ label: 'Invoice', icon: 'file' }, { label: 'Laporan', icon: 'file' }, { label: 'Pengeluaran', icon: 'chart' }] },
  { title: 'ADMINISTRASI', items: [{ label: 'Operator', icon: 'user' }, { label: 'Pengaturan', icon: 'settings' }] },
]

const sectionPaths: Record<string, string> = { Dashboard: '', Booking: 'booking', Quotation: 'quotation', Project: 'project', Inventaris: 'inventory', 'Manajemen Paket': 'packages', CRM: 'crm', Invoice: 'invoice', Laporan: 'reports', Pengeluaran: 'expenses', Operator: 'operator', Pengaturan: 'settings' }

const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
const currency = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 })
const number = new Intl.NumberFormat('id-ID')

function formatCurrency(value: number) {
  return currency.format(value).replace(/\u00a0/g, ' ')
}

function formatTime(value: string) {
  return new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23', timeZone: 'Asia/Jakarta' }).format(new Date(value))
}

function formatAge(seconds: number) {
  if (seconds < 3600) return `${Math.max(1, Math.floor(seconds / 60))} menit lalu`
  if (seconds < 86400) return `${Math.floor(seconds / 3600)} jam lalu`
  if (seconds < 604800) return `${Math.floor(seconds / 86400)} hari lalu`
  return `${Math.floor(seconds / 604800)} minggu lalu`
}

function TrendCard({ label, value, trend, icon, tone, detail }: { label: string; value: string; trend?: ManagerDashboardData['revenue']; icon: IconName; tone: string; detail?: string }) {
  const isUp = trend?.direction === 'up'
  const trendLabel = trend?.percentage === null || trend?.percentage === undefined
    ? trend?.percentage_reason ?? 'Belum ada data pembanding'
    : `${number.format(Math.abs(trend.percentage))}%`

  return (
    <article className={`manager-stat manager-stat--${tone}`}>
      {trend && <span className={`manager-stat__trend${isUp ? ' is-up' : ' is-down'}`}><Icon name={isUp ? 'trending-up' : 'trending-down'} size={14} />{trendLabel}</span>}
      {detail && <span className="manager-stat__trend"><Icon name="folder" size={14} />{detail}</span>}
      <span aria-hidden="true" className="manager-stat__icon"><Icon name={icon} size={27} /></span>
      <span className="manager-stat__label">{label}</span>
      <strong className="manager-stat__value">{value}</strong>
    </article>
  )
}

function Panel({ title, action, href, children, className = '' }: { title: string; action?: string; href?: string; children: ReactNode; className?: string }) {
  return (
    <section className={`manager-panel ${className}`}>
      <header className="manager-panel__header"><h2>{title}</h2>{action && <Link aria-label={action} className="manager-panel__action" to={href ?? "/dashboard"}><Icon name="arrow-right" size={20} /></Link>}</header>
      {children}
    </section>
  )
}

function FinanceChart({ data }: { data: ManagerDashboardData }) {
  const points = data.monthly.slice(0, 12)
  const max = Math.max(1, ...points.flatMap((point) => [point.revenue, point.expense]))
  const x = (index: number) => 32 + index * (850 / Math.max(1, points.length - 1))
  const y = (value: number) => 270 - (value / max) * 250
  const pathFor = (values: number[]) => values.map((value, index) => `${index === 0 ? 'M' : 'L'} ${x(index)} ${y(value)}`).join(' ')

  return (
    <Panel className="manager-finance" title="Laporan Keuangan">
      <div className="manager-finance__legend"><span><i className="is-revenue" />Pendapatan</span><span><i className="is-expense" />Pengeluaran</span><span className="manager-finance__period"><Icon name="calendar" size={14} />{new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' }).format(new Date(Date.UTC(data.period.year, data.period.month - 1, 1)))}</span></div>
      <div aria-label="Grafik pendapatan dan pengeluaran bulanan" className="manager-chart-wrap" role="img">
        <svg className="manager-chart" preserveAspectRatio="none" viewBox="0 0 900 310">
          {[0, 1, 2, 3, 4, 5].map((tick) => <g className="manager-chart__grid" key={tick}><line x1="32" x2="886" y1={20 + tick * 50} y2={20 + tick * 50} /><text x="0" y={24 + tick * 50}>{number.format(Math.round(max * (5 - tick) / 5))}</text></g>)}
          <path className="manager-chart__line is-revenue" d={pathFor(points.map((point) => point.revenue))} />
          <path className="manager-chart__line is-expense" d={pathFor(points.map((point) => point.expense))} />
          {points.map((point, index) => <text className="manager-chart__month" key={point.month} x={x(index)} y="300">{monthNames[point.month - 1]?.toUpperCase()}</text>)}
        </svg>
      </div>
    </Panel>
  )
}

function DonutPanel({ title, items, label, total, className = '' }: { title: string; items: Array<{ name: string; count: number; percentage: number }>; label: string; total: number; className?: string }) {
  const colors = ['#ce4337', '#2b2626', '#7a5900', '#4f8c62', '#315f88']
  const segments = items.reduce<Array<{ color: string; start: number; end: number }>>((result, item, index) => {
    const start = result.at(-1)?.end ?? 0
    result.push({ color: colors[index % colors.length], start, end: start + item.percentage })
    return result
  }, []).map((segment) => `${segment.color} ${segment.start}% ${segment.end}%`)

  return (
    <Panel className={`manager-donut-panel ${className}`} title={title} action={`Lihat ${title.toLowerCase()}`} href={title.includes("Paket") ? "/dashboard/packages" : "/dashboard/expenses"}>
      <div aria-label={`Distribusi ${label.toLowerCase()}`} className="manager-donut" role="img" style={{ background: segments.length ? `conic-gradient(${segments.join(', ')})` : 'conic-gradient(var(--ts-outline-variant) 0 100%)' }} />
      {items.length ? <ul className="manager-legend">{items.slice(0, 5).map((item, index) => <li key={item.name}><i style={{ background: colors[index % colors.length] }} /><span>{item.name}</span><strong>{number.format(item.percentage)}%</strong></li>)}</ul> : <p className="manager-empty manager-empty--compact">Belum ada {label.toLowerCase()} untuk periode ini.</p>}
      <span className="manager-sr-only">Total: {number.format(total)}</span>
    </Panel>
  )
}

function ManagerDashboardContent({ data, user, onUserChange }: { data: ManagerDashboardData; user: ManagerUser | null; onUserChange: (user: ManagerUser) => void }) {
  const location = useLocation()
  const navigate = useNavigate()
  const section = location.pathname.split('/')[2] ?? ''
  const currentLabel = Object.keys(sectionPaths).find((key) => sectionPaths[key] === section) ?? 'Dashboard'
  const [search, setSearch] = useState('')
  const [editingProfile, setEditingProfile] = useState(false)
  async function leave() { try { await logout(); navigate('/staff/login', { replace: true }) } catch { window.alert('Gagal keluar. Silakan coba lagi.') } }
  const sales = data.package_sales.items.map((item) => ({ name: item.name, count: item.project_count, percentage: item.percentage }))
  const expenses = data.expense_categories.items.map((item) => ({ name: item.category_name, count: item.transaction_count, percentage: item.percentage }))
  const displayName = user?.name ?? 'Manager'

  return (
    <div className="manager-shell">
      <aside className="manager-sidebar">
        <Link aria-label="Tungku Studio" className="manager-brand" to="/dashboard"><img alt="" src={tungkuIcon} /><span><strong>Tungku Studio</strong><small>Enterprise Resource Planning</small></span></Link>
        <nav aria-label="Navigasi utama" className="manager-nav">
          {navigationGroups.map((group) => <section className="manager-nav__group" key={group.title}><h2>{group.title}<Icon name="chevron-down" size={16} /></h2>{group.items.map((item) => <NavLink className={({ isActive }) => `manager-nav__item${isActive ? ' is-active' : ''}`} end={item.label === 'Dashboard'} key={item.label} to={`/dashboard${sectionPaths[item.label] ? `/${sectionPaths[item.label]}` : ''}`}><Icon name={item.icon} size={20} /><span>{item.label}</span></NavLink>)}</section>)}
        </nav>
        <div className="manager-profile"><span aria-hidden="true" className="manager-profile__avatar"><Icon name="user" size={22} /></span><span className="manager-profile__details"><strong>{displayName}</strong><small>Manager</small></span><button className="manager-profile__edit" onClick={() => setEditingProfile(true)} type="button">Edit Profil</button><button aria-label="Logout" className="manager-profile__logout" onClick={() => void leave()} type="button"><Icon name="logout" size={20} /><span>Logout</span></button></div>
      </aside>

      <main className="manager-main">
        <header className="manager-topbar"><div><span className="manager-breadcrumb">Utama / {currentLabel}</span><h1>{currentLabel}</h1></div><div className="manager-topbar__tools"><Button className="manager-create-button" leadingIcon="plus" onClick={() => navigate("/dashboard/packages")}>Kelola Paket</Button><Input aria-label="Cari menu" className="manager-search" leadingIcon="search" placeholder="Cari menu..." value={search} onChange={(event) => setSearch(event.target.value)} />{search && <div className="manager-menu-results">{Object.entries(sectionPaths).filter(([label]) => label.toLowerCase().includes(search.toLowerCase())).map(([label, path]) => <Link key={label} onClick={() => setSearch("")} to={`/dashboard${path ? `/${path}` : ""}`}>{label}</Link>)}</div>}<button aria-label="Edit Profil" className="manager-topbar__icon" onClick={() => setEditingProfile(true)} type="button"><Icon name="user" size={23} /></button><button aria-label="Logout" className="manager-topbar__icon" onClick={() => void leave()} type="button"><Icon name="logout" size={23} /></button><button aria-label="Pengaturan" className="manager-topbar__icon" onClick={() => navigate("/dashboard/settings")} type="button"><Icon name="settings" size={25} /></button><button aria-label="Penawaran menunggu" className="manager-topbar__icon" onClick={() => navigate("/dashboard/quotation")} type="button"><Icon name="bell" size={25} /></button></div></header>

        {section ? <ManagerWorkspace section={section as Parameters<typeof ManagerWorkspace>[0]["section"]} /> : <div className="manager-dashboard">
          <section aria-label="Ringkasan" className="manager-stats">
            <TrendCard icon="dollar" label="Pendapatan Bulan Ini" tone="success" trend={data.revenue} value={formatCurrency(data.revenue.current)} />
            <TrendCard icon="trending-down" label="Pengeluaran Bulan Ini" tone="error" trend={data.expense} value={formatCurrency(data.expense.current)} />
            <TrendCard icon="credit-card" label="Pembayaran Belum Lunas" tone="blue" value={formatCurrency(data.awaiting_final_payment.amount)} />
            <TrendCard detail={`${number.format(data.in_progress_projects.track_count)} Lagu`} icon="folder" label="Project Aktif" tone="purple" value={number.format(data.in_progress_projects.count)} />
            <TrendCard detail={`${number.format(data.quotations.awaiting_studio_count)} Menunggu Studio`} icon="handshake" label="Dalam Penawaran" tone="orange" value={number.format(data.quotations.open_count)} />
          </section>

          <section aria-label="Grafik ringkasan keuangan" className="manager-analytics"><FinanceChart data={data} /><DonutPanel className="manager-sales-panel" items={sales} label="Paket terjual" title="Paket Terlaris Bulanan" total={data.package_sales.total_count} /><DonutPanel className="manager-expense-panel" items={expenses} label="Pengeluaran" title="Pengeluaran Bulan Ini" total={data.expense_categories.total_amount} /></section>

          <section aria-label="Ringkasan operasional" className="manager-operations">
            <Panel title="Jadwal Rekaman Hari Ini" action="Lihat jadwal" href="/dashboard/booking"><ul className="manager-list">{data.recording_schedule.map((item) => <li className="manager-list__row" key={item.id}><span><strong>{item.project_name ?? 'Project tidak tersedia'}</strong><small>{item.client_name ?? 'Klien tidak tersedia'}</small></span><time>{formatTime(item.start_at)} – {formatTime(item.end_at)}</time></li>)}</ul>{!data.recording_schedule.length && <p className="manager-empty">Tidak ada jadwal rekaman hari ini.</p>}</Panel>
            <Panel title="Progress Proyek" action="Lihat project" href="/dashboard/project"><ul className="manager-list">{data.project_progress.map((item) => <li className="manager-list__row" key={item.id}><span><strong>{item.name}</strong><small>{item.client_name ?? 'Klien tidak tersedia'}</small></span><span className={`manager-status manager-status--${item.current_stage ?? 'neutral'}`}><Icon name={item.current_stage === 'recording' ? 'microphone' : item.current_stage === 'editing' ? 'scissors' : 'sliders'} size={14} />{item.current_stage_label ?? item.status_label}</span></li>)}</ul>{!data.project_progress.length && <p className="manager-empty">Belum ada project yang sedang berjalan.</p>}</Panel>
            <Panel title="Progress Penawaran" action="Lihat penawaran" href="/dashboard/quotation"><ul className="manager-list">{data.quotation_progress.map((item) => <li className="manager-list__row" key={item.id}><span><strong>{item.name ?? item.package_display_name}</strong><small>{item.client_name ?? 'Klien tidak tersedia'}</small></span><span className={`manager-status manager-status--${item.status === 'awaiting_client' ? 'orange' : 'error'}`}><Icon name="handshake" size={14} />{item.status_label}</span></li>)}</ul>{!data.quotation_progress.length && <p className="manager-empty">Tidak ada penawaran yang menunggu respons.</p>}</Panel>
            <Panel title="Aktivitas Operator"><ul className="manager-activity-list">{data.operator_activities.slice(0, 5).map((item) => <li key={item.id}><strong>{item.description}</strong><time>{formatAge(item.age_seconds)}</time></li>)}</ul>{!data.operator_activities.length && <p className="manager-empty">Belum ada aktivitas operator.</p>}</Panel>
          </section>
        </div>}
      </main>
      {editingProfile && user && <ProfileDialog user={user} onClose={() => setEditingProfile(false)} onSaved={(saved) => { onUserChange(saved); setEditingProfile(false) }} />}
    </div>
  )
}

export default function ManagerDashboard() {
  const [data, setData] = useState<ManagerDashboardData | null>(null)
  const [user, setUser] = useState<ManagerUser | null>(null)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let cancelled = false
    Promise.all([getManagerDashboard(), getCurrentUser()])
      .then(([dashboard, currentUser]) => {
        if (cancelled) return
        setData(dashboard)
        setUser(currentUser)
      })
      .catch((cause: unknown) => {
        if (cancelled) return
        setError(cause instanceof Error ? cause.message : 'Dashboard tidak dapat dimuat.')
      })
      .finally(() => { if (!cancelled) setLoading(false) })
    return () => { cancelled = true }
  }, [])

  if (loading) return <main aria-busy="true" className="manager-state"><span className="manager-state__spinner" /><p>Memuat dashboard...</p></main>
  if (error) return <main className="manager-state"><Icon name="activity" size={32} /><h1>Dashboard belum dapat dimuat</h1><p>{error}</p><button onClick={() => window.location.reload()} type="button">Coba lagi</button></main>
  if (!data) return null

  return <ManagerDashboardContent data={data} user={user} onUserChange={setUser} />
}