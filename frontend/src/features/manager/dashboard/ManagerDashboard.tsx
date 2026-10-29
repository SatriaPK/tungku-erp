import { useEffect, useState, type ReactNode } from 'react'
import { Button, Icon, Input, LoadingScreen, StatCard } from '../../../components/ui'
import type { BadgeTone, IconName } from '../../../components/ui'
import { ManagerSideNav } from '../../../components/layout'
import { getCurrentUser, getManagerDashboard } from '../../../lib/manager-dashboard-api'
import type { ManagerDashboardData, ManagerUser } from '../../../lib/manager-dashboard-api'
import './ManagerDashboard.css'

const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
const currency = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 })
const number = new Intl.NumberFormat('id-ID')

function formatCurrency(value: number) {
  return currency.format(value).replace(/\u00a0/g, ' ')
}

function formatTime(value: string) {
  return new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23', timeZone: 'UTC' }).format(new Date(value))
}

function formatAge(seconds: number) {
  if (seconds < 3600) return `${Math.max(1, Math.floor(seconds / 60))} menit lalu`
  if (seconds < 86400) return `${Math.floor(seconds / 3600)} jam lalu`
  if (seconds < 604800) return `${Math.floor(seconds / 86400)} hari lalu`
  return `${Math.floor(seconds / 604800)} minggu lalu`
}

type StatBadge = { icon: IconName; label: string; tone: BadgeTone }

function financeBadge(trend: ManagerDashboardData['revenue'], metric: 'revenue' | 'expense'): StatBadge {
  const direction = trend.direction === 'up' || trend.direction === 'down' ? trend.direction : 'neutral'
  const isPositive = (metric === 'revenue' && direction === 'up') || (metric === 'expense' && direction === 'down')
  const tone = direction === 'neutral' ? 'neutral' : isPositive ? 'success' : 'negative'
  const label = trend.percentage === null
    ? '0%'
    : `${direction === 'up' ? 'Naik' : direction === 'down' ? 'Turun' : 'Tetap'} ${number.format(Math.abs(trend.percentage))}% dari bulan lalu`

  return { icon: direction === 'up' ? 'trending-up' : direction === 'down' ? 'trending-down' : 'activity', label, tone }
}

function Panel({ title, action, actionHref, children, className = '' }: { title: string; action?: string; actionHref?: string; children: ReactNode; className?: string }) {
  const header = (
    <header className="manager-panel__header">
      <h2>{title}</h2>
      {action && <Icon className="manager-panel__action" name="arrow-right" size={20} />}
    </header>
  )

  return (
    <section className={`manager-panel ${className}`}>
      {actionHref ? <a aria-label={action} className="manager-panel__header-link" href={actionHref}>{header}</a> : header}
      {children}
    </section>
  )
}

function OperationalPanel({ title, action, href, children }: { title: string; action: string; href: string; children: ReactNode }) {
  return (
    <Panel action={action} actionHref={href} className="manager-operational-panel" title={title}>
      <div className="manager-operational-panel__content">{children}</div>
    </Panel>
  )
}

function FinanceReportDiagram({ data, onYearChange, selectedYear, yearLoading }: { data: ManagerDashboardData; onYearChange: (year: number) => void; selectedYear: number; yearLoading: boolean }) {
  const points = [...data.monthly].sort((left, right) => left.month - right.month).slice(0, 12)
  const max = Math.max(1, ...points.flatMap((point) => [point.revenue, point.expense]))
  const x = (index: number) => 32 + index * (850 / Math.max(1, points.length - 1))
  const y = (value: number) => 270 - (value / max) * 250
  const pathFor = (values: number[]) => values.map((value, index) => `${index === 0 ? 'M' : 'L'} ${x(index)} ${y(value)}`).join(' ')

  return (
    <Panel className="manager-finance" title="Laporan Keuangan">
      <div className="manager-finance__controls">
        <div aria-label="Keterangan grafik" className="manager-finance__legend">
          <span><i className="is-revenue" />Pendapatan</span>
          <span><i className="is-expense" />Pengeluaran</span>
        </div>
        <label className="manager-finance__year" title="Ganti Tahun">
          <Icon aria-hidden="true" name="calendar" size={16} />
          <select aria-label="Ganti Tahun" disabled={yearLoading} onChange={(event) => onYearChange(Number(event.target.value))} value={selectedYear}>
            {Array.from({ length: 5 }, (_, index) => selectedYear - index).map((year) => <option key={year} value={year}>{year}</option>)}
          </select>
          <Icon aria-hidden="true" className="manager-finance__year-chevron" name="chevron-down" size={16} />
          <select aria-hidden="true" aria-label="Buka pilihan tahun" className="manager-finance__year-hitbox" disabled={yearLoading} onChange={(event) => onYearChange(Number(event.target.value))} tabIndex={-1} value={selectedYear}>
            {Array.from({ length: 5 }, (_, index) => selectedYear - index).map((year) => <option key={year} value={year}>{year}</option>)}
          </select>
        </label>
      </div>
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

function DonutPanel({ title, items, label, total, href, className = '' }: { title: string; items: Array<{ name: string; count: number; percentage: number }>; label: string; total: number; href: string; className?: string }) {
  const colors = ['#ce4337', '#2b2626', '#7a5900', '#4f8c62', '#315f88']
  const segments = items.reduce<Array<{ color: string; start: number; end: number }>>((result, item, index) => {
    const start = result.at(-1)?.end ?? 0
    result.push({ color: colors[index % colors.length], start, end: start + item.percentage })
    return result
  }, []).map((segment) => `${segment.color} ${segment.start}% ${segment.end}%`)

  return (
    <Panel action={`Lihat ${title.toLowerCase()}`} actionHref={href} className={`manager-donut-panel ${className}`} title={title}>
      {items.length ? (
        <div aria-label={`Distribusi ${label.toLowerCase()}`} className="manager-donut-visual" role="img" style={{ background: `conic-gradient(${segments.join(', ')})` }}>
          <div aria-hidden="true" className="manager-donut" />
        </div>
      ) : <p aria-label={`No data ${label.toLowerCase()}`} className="manager-donut-empty">No data</p>}
      {items.length ? <ul className="manager-legend">{items.slice(0, 5).map((item, index) => <li key={item.name}><i style={{ background: colors[index % colors.length] }} /><span>{item.name}</span><strong>{number.format(item.percentage)}%</strong></li>)}</ul> : <span className="manager-empty manager-empty--compact" />}
      <span className="manager-sr-only">Total: {number.format(total)}</span>
    </Panel>
  )
}

function ManagerDashboardContent({ data, user }: { data: ManagerDashboardData; user: ManagerUser | null }) {
  const [selectedYear, setSelectedYear] = useState(data.period.year)
  const [yearData, setYearData] = useState<{ year: number; data: ManagerDashboardData } | null>(null)
  const [yearLoading, setYearLoading] = useState(false)
  const selectedDashboardData = yearData?.year === selectedYear ? yearData.data : data
  const dashboardData = selectedYear === data.period.year ? data : selectedDashboardData

  const changeYear = async (nextYear: number) => {
    if (yearLoading || nextYear === selectedYear) return
    const previousYear = selectedYear
    setSelectedYear(nextYear)
    setYearLoading(true)
    try {
      const result = await getManagerDashboard(nextYear)
      setYearData({ year: nextYear, data: result })
    } catch (cause: unknown) {
      setSelectedYear(previousYear)
      window.alert(cause instanceof Error ? cause.message : 'Laporan keuangan tidak dapat dimuat.')
    } finally {
      setYearLoading(false)
    }
  }

  const sales = data.package_sales.items.map((item) => ({ name: item.name, count: item.project_count, percentage: item.percentage }))
  const expenses = data.expense_categories.items.map((item) => ({ name: item.category_name ?? 'Lainnya', count: item.transaction_count, percentage: item.percentage }))
  const displayName = user?.name ?? 'Manager'

  return (
    <div className="manager-shell">
      <ManagerSideNav managerName={displayName} />

      <main className="manager-main">
        <header className="manager-topbar"><div><span className="manager-breadcrumb">Utama / Dashboard</span><h1>Dashboard</h1></div><div className="manager-topbar__tools"><Button className="manager-create-button" leadingIcon="plus">Buat Project</Button><Input aria-label="Cari project, klien, operator" className="manager-search" leadingIcon="search" placeholder="Cari project, klien, operator..." /><button aria-label="Pengaturan" className="manager-topbar__icon" type="button"><Icon name="settings" size={25} /></button><button aria-label="Notifikasi" className="manager-topbar__icon" type="button"><Icon name="bell" size={25} /></button></div></header>

        <div className="manager-dashboard">
          <section aria-label="Ringkasan" className="manager-stats">
            <StatCard badge={financeBadge(data.revenue, 'revenue')} href="/laporan" icon="trending-up" label="Pendapatan Bulan Ini" tone="success" value={formatCurrency(data.revenue.current)} />
            <StatCard badge={financeBadge(data.expense, 'expense')} href="/pengeluaran" icon="trending-down" label="Pengeluaran Bulan Ini" tone="error" value={formatCurrency(data.expense.current)} />
            <StatCard badge={{ icon: 'folder', label: `${number.format(data.awaiting_final_payment.project_count)} project`, tone: 'primary' }} href="/invoice" icon="credit-card" label="Pembayaran Belum Lunas" tone="blue" value={formatCurrency(data.awaiting_final_payment.amount)} />
            <StatCard badge={{ icon: 'music', label: `${number.format(data.in_progress_projects.track_count)} track`, tone: 'mastering' }} href="/project" icon="folder" label="Project Aktif" tone="purple" value={number.format(data.in_progress_projects.count)} />
            <StatCard badge={{ icon: 'clock', label: `${number.format(Math.max(0, data.quotations.open_count - data.quotations.awaiting_studio_count))} menunggu klien`, tone: 'warning' }} href="/quotation" icon="handshake" label="Dalam Penawaran" tone="blue" value={number.format(data.quotations.open_count)} />
          </section>

          <section aria-label="Grafik ringkasan keuangan" className="manager-analytics"><FinanceReportDiagram data={dashboardData} onYearChange={changeYear} selectedYear={selectedYear} yearLoading={yearLoading} /><DonutPanel className="manager-sales-panel" href="/manajemen-paket" items={sales} label="Paket terjual" title="Paket Terlaris Bulanan" total={data.package_sales.total_count} /><DonutPanel className="manager-expense-panel" href="/pengeluaran" items={expenses} label="Pengeluaran" title="Pengeluaran Bulan Ini" total={data.expense_categories.total_amount} /></section>

          <section aria-label="Ringkasan operasional" className="manager-operations">
            <OperationalPanel action="Lihat jadwal" href="/booking" title="Jadwal Rekaman Hari Ini">{data.recording_schedule.length ? <ul className="manager-list">{data.recording_schedule.map((item) => <li className="manager-list__row" key={item.id}><span><strong>{item.project_name ?? 'Project tidak tersedia'}</strong><small>{item.client_name ?? 'Klien tidak tersedia'}</small></span><time>{formatTime(item.start_at)} – {formatTime(item.end_at)}</time></li>)}</ul> : <p className="manager-operational-empty">No data</p>}</OperationalPanel>
            <OperationalPanel action="Lihat project" href="/project" title="Progress Proyek">{data.project_progress.length ? <ul className="manager-list">{data.project_progress.map((item) => <li className="manager-list__row" key={item.id}><span><strong>{item.name}</strong><small>{item.client_name ?? 'Klien tidak tersedia'}</small></span><span className={`manager-status manager-status--${item.current_stage ?? 'neutral'}`}><Icon name={item.current_stage === 'recording' ? 'microphone' : item.current_stage === 'editing' ? 'scissors' : 'sliders'} size={14} />{item.current_stage_label ?? item.status_label}</span></li>)}</ul> : <p className="manager-operational-empty">No data</p>}</OperationalPanel>
            <OperationalPanel action="Lihat penawaran" href="/quotation" title="Progress Penawaran">{data.quotation_progress.length ? <ul className="manager-list">{data.quotation_progress.map((item) => <li className="manager-list__row" key={item.id}><span><strong>{item.name ?? item.package_display_name}</strong><small>{item.client_name ?? 'Klien tidak tersedia'}</small></span><span className={`manager-status manager-status--${item.status === 'awaiting_client' ? 'orange' : 'error'}`}><Icon name="handshake" size={14} />{item.status_label}</span></li>)}</ul> : <p className="manager-operational-empty">No data</p>}</OperationalPanel>
            <OperationalPanel action="Lihat aktivitas operator" href="/operator" title="Aktivitas Operator">{data.operator_activities.length ? <ul className="manager-activity-list">{data.operator_activities.slice(0, 5).map((item) => <li key={item.id}><strong>{item.description}</strong><time>{formatAge(item.age_seconds)}</time></li>)}</ul> : <p className="manager-operational-empty">No data</p>}</OperationalPanel>
          </section>
        </div>
      </main>
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

  if (loading) return <LoadingScreen label="Memuat dashboard Manager" />
  if (error) return <main className="manager-state"><Icon name="activity" size={32} /><h1>Dashboard belum dapat dimuat</h1><p>{error}</p><button onClick={() => window.location.reload()} type="button">Coba lagi</button></main>
  if (!data) return null

  return <ManagerDashboardContent data={data} user={user} />
}