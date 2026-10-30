import { useEffect, useRef, useState, type ReactNode } from 'react'
import { Button, Icon, Input, LoadingScreen, StatCard } from '../../../components/ui'
import type { BadgeTone, IconName } from '../../../components/ui'
import { ManagerSideNav } from '../../../components/layout'
import { getCurrentUser, getManagerDashboard } from '../../../lib/manager-dashboard-api'
import type { ManagerDashboardData, ManagerUser } from '../../../lib/manager-dashboard-api'
import {
  CartesianGrid,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import './ManagerDashboard.css'

const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
const currency = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 })
const number = new Intl.NumberFormat('id-ID')
const compactChartCurrency = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 })

function formatCurrency(value: number) {
  return currency.format(value).replace(/\u00a0/g, ' ')
}

function formatCompactChartCurrency(value: number) {
  const absValue = Math.abs(value)
  if (absValue >= 1_000_000) return `${compactChartCurrency.format(value / 1_000_000)} jt`
  if (absValue >= 1_000) return `${compactChartCurrency.format(value / 1_000)} rb`
  return compactChartCurrency.format(value)
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

function Panel({ title, action, actionHref, bodyHref, children, className = '' }: { title: string; action?: string; actionHref?: string; bodyHref?: string; children: ReactNode; className?: string }) {
  const header = (
    <header className="manager-panel__header">
      <h2>{title}</h2>
      {action && <Icon className="manager-panel__action" name="arrow-right" size={20} />}
    </header>
  )

  return (
    <section className={`manager-panel ${className}`}>
      {actionHref ? <a aria-label={action} className="manager-panel__header-link" href={actionHref}>{header}</a> : header}
      {bodyHref ? <a aria-label={action} className="manager-panel__body-link" href={bodyHref}>{children}</a> : children}
    </section>
  )
}

function OperationalPanel({ title, action, href, children }: { title: string; action: string; href: string; children: ReactNode }) {
  return (
    <Panel action={action} actionHref={href} bodyHref={href} className="manager-operational-panel" title={title}>
      <div className="manager-operational-panel__content">{children}</div>
    </Panel>
  )
}

function FinanceReportTooltip({ active, payload, label }: { active?: boolean; payload?: Array<{ color?: string; dataKey?: string; name?: string; value?: number }>; label?: string }) {
  if (!active || !payload?.length) return null

  return (
    <div className="manager-chart-tooltip" role="status">
      <strong>{label}</strong>
      {payload.map((entry) => <span key={entry.dataKey}>
        <i className={entry.dataKey === 'expense' ? 'is-expense' : 'is-revenue'} />
        <span>{entry.name}</span>
        <b>Rp {formatCompactChartCurrency(Number(entry.value ?? 0))}</b>
      </span>)}
    </div>
  )
}

function FinanceReportDiagram({ data, onYearChange, selectedYear, yearLoading }: { data: ManagerDashboardData; onYearChange: (year: number) => void; selectedYear: number; yearLoading: boolean }) {
  const points = [...data.monthly]
    .sort((left, right) => left.month - right.month)
    .slice(0, 12)
    .map((point) => ({
      ...point,
      label: `${monthNames[point.month - 1] ?? point.month} ${selectedYear}`,
    }))
  const currentYear = new Date().getFullYear()
  const years = Array.from({ length: 5 }, (_, index) => currentYear - index)

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
            {years.map((year) => <option key={year} value={year}>{year}</option>)}
          </select>
          <Icon aria-hidden="true" className="manager-finance__year-chevron" name="chevron-down" size={16} />
          <select aria-hidden="true" aria-label="Buka pilihan tahun" className="manager-finance__year-hitbox" disabled={yearLoading} onChange={(event) => onYearChange(Number(event.target.value))} tabIndex={-1} value={selectedYear}>
            {years.map((year) => <option key={year} value={year}>{year}</option>)}
          </select>
        </label>
      </div>
      <div aria-label="Grafik pendapatan dan pengeluaran bulanan" className="manager-chart-wrap" role="img">
        <ResponsiveContainer height="100%" minHeight={220} minWidth={0} width="100%">
          <LineChart accessibilityLayer data={points} margin={{ top: 0, right: 0, bottom: 0, left: -16 }}>
            <CartesianGrid stroke="var(--ts-outline-variant)" vertical={false} />
            <XAxis axisLine={false} dataKey="label" height={24} padding={{ left: 4, right: 4 }} tick={{ fill: 'var(--ts-on-surface-variant)', fontSize: 10 }} tickFormatter={(label: string) => label.split(' ')[0].toUpperCase()} tickLine={false} />
            <YAxis axisLine={false} domain={[0, (max: number) => Math.max(max, 1)]} mirror={false} tick={{ fill: 'var(--ts-on-surface-variant)', fontSize: 10 }} tickFormatter={(value: number) => formatCompactChartCurrency(value)} tickLine={false} width={52} />
            <Tooltip content={<FinanceReportTooltip />} cursor={{ stroke: 'var(--ts-outline)', strokeDasharray: '4 4' }} isAnimationActive={false} />
            <Line activeDot={{ r: 6 }} dataKey="revenue" dot={{ r: 3 }} isAnimationActive={false} name="Pendapatan" stroke="var(--ts-success)" strokeWidth={2} type="monotone" />
            <Line activeDot={{ r: 6 }} dataKey="expense" dot={{ r: 3 }} isAnimationActive={false} name="Pengeluaran" stroke="var(--ts-error)" strokeWidth={2} type="monotone" />
          </LineChart>
        </ResponsiveContainer>
      </div>
    </Panel>
  )
}

function DonutPanel({ title, items, label, total, href, valueLabel, className = '' }: { title: string; items: Array<{ name: string; count: number; percentage: number }>; label: string; total: number; href: string; valueLabel: 'count' | 'amount'; className?: string }) {
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
      {items.length ? <ul className="manager-legend">{items.slice(0, 5).map((item, index) => <li key={item.name}><i style={{ background: colors[index % colors.length] }} /><span>{item.name}</span><strong>{valueLabel === 'count' ? number.format(item.count) : formatCurrency(item.count)}</strong></li>)}</ul> : <span className="manager-empty manager-empty--compact" />}
      <span className="manager-sr-only">Total: {number.format(total)}</span>
    </Panel>
  )
}

function ManagerDashboardContent({ data, user }: { data: ManagerDashboardData; user: ManagerUser | null }) {
  const [selectedYear, setSelectedYear] = useState(data.period.year)
  const [yearData, setYearData] = useState<{ year: number; data: ManagerDashboardData } | null>(null)
  const [yearLoading, setYearLoading] = useState(false)
  const [searchOpen, setSearchOpen] = useState(false)
  const searchContainerRef = useRef<HTMLDivElement>(null)
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

  useEffect(() => {
    if (!searchOpen) return

    const closeSearchOnOutsideClick = (event: PointerEvent) => {
      if (event.target instanceof Node && !searchContainerRef.current?.contains(event.target)) {
        setSearchOpen(false)
      }
    }
    const closeSearchOnEscape = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setSearchOpen(false)
    }

    document.addEventListener('pointerdown', closeSearchOnOutsideClick)
    document.addEventListener('keydown', closeSearchOnEscape)
    return () => {
      document.removeEventListener('pointerdown', closeSearchOnOutsideClick)
      document.removeEventListener('keydown', closeSearchOnEscape)
    }
  }, [searchOpen])

  const sales = data.package_sales.items.map((item) => ({ name: item.name, count: item.project_count, percentage: item.percentage }))
  const expenses = data.expense_categories.items.map((item) => ({ name: item.category_name ?? 'Lainnya', count: item.amount, percentage: item.percentage }))
  return (
    <div className="manager-shell">
      <ManagerSideNav managerName={user?.name ?? 'Manager'} pendingProjectAssignments={data.pending_project_assignments ?? 0} />

      <main className="manager-main">
        <header className="manager-topbar">
          <div className="manager-topbar__heading">
            <span className="manager-breadcrumb">Utama / Dashboard</span>
            <h1>Dashboard</h1>
          </div>
          <div className="manager-topbar__tools">
            <Button aria-label="Project Baru" className="manager-create-button" leadingIcon="plus">Project Baru</Button>
            <div className={`manager-search-wrap${searchOpen ? ' is-open' : ''}`} ref={searchContainerRef}>
              <Input aria-label="Cari project, klien, operator" className="manager-search" leadingIcon="search" placeholder="Cari project, klien, operator..." />
              <button aria-expanded={searchOpen} aria-label={searchOpen ? 'Tutup pencarian' : 'Buka pencarian'} className="manager-topbar__icon manager-search-toggle" onClick={() => setSearchOpen((open) => !open)} type="button">
                <Icon name="search" size={20} />
              </button>
            </div>
            <button aria-label="Notifikasi" className="manager-topbar__icon" type="button"><Icon name="bell" size={20} /></button>
            <button aria-label="Pengaturan" className="manager-topbar__icon manager-topbar__settings" type="button"><Icon name="settings" size={20} /></button>
          </div>
        </header>

        <div className="manager-dashboard">
          <section aria-label="Ringkasan" className="manager-stats">
            <StatCard badge={financeBadge(data.revenue, 'revenue')} href="/laporan" icon="trending-up" label="Pendapatan Bulan Ini" tone="success" value={formatCurrency(data.revenue.current)} />
            <StatCard badge={financeBadge(data.expense, 'expense')} href="/pengeluaran" icon="trending-down" label="Pengeluaran Bulan Ini" tone="error" value={formatCurrency(data.expense.current)} />
            <StatCard badge={{ icon: 'folder', label: `${number.format(data.awaiting_final_payment.project_count)} project`, tone: 'warning' }} href="/invoice" icon="credit-card" label="Pembayaran Belum Lunas" tone="blue" value={formatCurrency(data.awaiting_final_payment.amount)} />
            <StatCard badge={{ icon: 'music', label: `${number.format(data.in_progress_projects.track_count)} track`, tone: 'mastering' }} href="/project" icon="folder" label="Project Aktif" tone="purple" value={number.format(data.in_progress_projects.count)} />
            <StatCard badge={{ icon: 'clock', label: `${number.format(Math.max(0, data.quotations.open_count - data.quotations.awaiting_studio_count))} menunggu klien`, tone: 'warning' }} href="/quotation" icon="handshake" label="Dalam Penawaran" tone="blue" value={number.format(data.quotations.open_count)} />
          </section>

          <section aria-label="Grafik ringkasan keuangan" className="manager-analytics"><FinanceReportDiagram data={dashboardData} onYearChange={changeYear} selectedYear={selectedYear} yearLoading={yearLoading} /><DonutPanel className="manager-sales-panel" href="/manajemen-paket" items={sales} label="Paket terjual" title="Paket Terlaris Bulanan" total={data.package_sales.total_count} valueLabel="count" /><DonutPanel className="manager-expense-panel" href="/pengeluaran" items={expenses} label="Pengeluaran" title="Pengeluaran Bulan Ini" total={data.expense_categories.total_amount} valueLabel="amount" /></section>

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