import { useEffect, useMemo, useRef, useState, type CSSProperties } from 'react'
import { createPortal } from 'react-dom'
import { useNavigate } from 'react-router-dom'
import { ManagerSideNav, UpperNavBar } from '../../../components/layout'
import { Badge, Button, Icon, Input, LoadingScreen, ProjectCard, StatCard } from '../../../components/ui'
import type { BadgeTone, IconName, ProjectCardProps } from '../../../components/ui'
import { getCurrentUser, getManagerProjects } from '../../../lib/manager-dashboard-api'
import type { ManagerProject, ManagerProjectPage, ManagerUser } from '../../../lib/manager-dashboard-api'
import '../dashboard/ManagerDashboard.css'
import './ManagerProjectsPage.css'

type ProjectStage = 'recording' | 'editing' | 'mixing' | 'mastering'
type StageFilter = 'all' | ProjectStage
type ProjectStatus = 'all' | 'pending_payment' | 'awaiting_assignment' | 'in_progress' | 'awaiting_final_payment' | 'completed' | 'cancelled'
type SortOrder = 'newest' | 'oldest' | 'az' | 'za'

const stages: Array<{ key: ProjectStage; label: string; tone: BadgeTone; icon: IconName }> = [
  { key: 'recording', label: 'Recording', tone: 'recording', icon: 'microphone' },
  { key: 'editing', label: 'Editing', tone: 'editing', icon: 'scissors' },
  { key: 'mixing', label: 'Mixing', tone: 'mixing', icon: 'sliders' },
  { key: 'mastering', label: 'Mastering', tone: 'mastering', icon: 'activity' },
]

const projectStatuses: Array<{ key: Exclude<ProjectStatus, 'all'>; label: string; tone: BadgeTone }> = [
  { key: 'pending_payment', label: 'Menunggu pembayaran PO', tone: 'warning' },
  { key: 'awaiting_assignment', label: 'Menunggu penugasan operator', tone: 'neutral' },
  { key: 'in_progress', label: 'Sedang dikerjakan', tone: 'primary' },
  { key: 'awaiting_final_payment', label: 'Menunggu pelunasan', tone: 'warning' },
  { key: 'completed', label: 'Project selesai', tone: 'success' },
  { key: 'cancelled', label: 'Project dibatalkan', tone: 'negative' },
]

const number = new Intl.NumberFormat('id-ID')

function projectPhase(currentStage: string | null, status: string): ProjectCardProps['phase'] {
  if (status === 'completed') return 'Selesai'
  if (currentStage === 'recording' || currentStage === 'editing' || currentStage === 'mixing' || currentStage === 'mastering') {
    return stages.find((stage) => stage.key === currentStage)!.label as ProjectCardProps['phase']
  }
  return status === 'cancelled' ? 'Revisi' : 'Recording'
}

function projectStatusLabel(status: string, statusLabel: string) {
  if (status === 'in_progress') return 'Sedang dikerjakan'
  if (status === 'awaiting_assignment') return 'Menunggu penugasan operator'
  if (status === 'pending_payment') return 'Menunggu pembayaran PO'
  if (status === 'awaiting_final_payment') return 'Menunggu pelunasan'
  if (status === 'completed') return 'Project selesai'
  if (status === 'cancelled') return 'Project dibatalkan'
  return statusLabel
}

function projectStatusTone(status: string): BadgeTone {
  if (status === 'completed') return 'success'
  if (status === 'cancelled') return 'negative'
  if (status === 'pending_payment' || status === 'awaiting_final_payment') return 'warning'
  if (status === 'in_progress') return 'primary'
  return 'neutral'
}

function projectClientInitial(name: string) {
  return name.trim().charAt(0).toLocaleUpperCase('id-ID') || '?'
}

function formatStartDate(value: string | null | undefined) {
  if (!value) return 'Tanggal mulai belum tersedia'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return 'Tanggal mulai belum tersedia'
  return new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }).format(date)
}

function stageCounts(projects: ManagerProject[]) {
  return projects.reduce<Record<ProjectStage, number>>((counts, project) => {
    const stage = project.current_stage?.stage
    if (stage === 'recording' || stage === 'editing' || stage === 'mixing' || stage === 'mastering') {
      counts[stage] += 1
    }
    return counts
  }, { recording: 0, editing: 0, mixing: 0, mastering: 0 })
}

export default function ManagerProjectsPage() {
  const navigate = useNavigate()
  const [user, setUser] = useState<ManagerUser | null>(null)
  const [data, setData] = useState<ManagerProjectPage | null>(null)
  const [error, setError] = useState('')
  const [query, setQuery] = useState('')
  const [stageFilter, setStageFilter] = useState<StageFilter>('all')
  const [statusFilter, setStatusFilter] = useState<ProjectStatus>('all')
  const [statusSearch, setStatusSearch] = useState('')
  const [statusPopoverOpen, setStatusPopoverOpen] = useState(false)
  const [sort, setSort] = useState<SortOrder>('newest')
  const [sortPopoverOpen, setSortPopoverOpen] = useState(false)
  const [view, setView] = useState<'Kartu' | 'Tabel'>('Kartu')
  const [viewportWidth, setViewportWidth] = useState(1440)
  const [page, setPage] = useState(1)
  const [initialDataLoading, setInitialDataLoading] = useState(true)
  const [searchOpen, setSearchOpen] = useState(false)
  const searchContainerRef = useRef<HTMLDivElement>(null)
  const sortButtonRef = useRef<HTMLButtonElement>(null)
  const sortPopoverRef = useRef<HTMLDivElement>(null)
  const [sortPopoverPosition, setSortPopoverPosition] = useState({ top: 0, right: 0 })
  const statusButtonRef = useRef<HTMLButtonElement>(null)
  const statusPopoverRef = useRef<HTMLDivElement>(null)
  const [statusPopoverPosition, setStatusPopoverPosition] = useState({ top: 0, left: 0, mobile: false })
  const isMobile = viewportWidth <= 740

  useEffect(() => {
    let active = true
    getCurrentUser().then((currentUser) => { if (active) setUser(currentUser) }).catch(() => undefined)
    return () => { active = false }
  }, [])
  useEffect(() => {
    let active = true
    getManagerProjects({ page: 1, per_page: 100, sort: 'newest' }).then((projectsPage) => {
      if (active) setData(projectsPage)
    }).catch((cause: unknown) => {
      if (active) setError(cause instanceof Error ? cause.message : 'Daftar project tidak dapat dimuat.')
    }).finally(() => {
      if (active) setInitialDataLoading(false)
    })
    return () => { active = false }
  }, [])

  useEffect(() => {
    const onResize = () => setViewportWidth(window.innerWidth)
    onResize()
    window.addEventListener('resize', onResize)
    return () => window.removeEventListener('resize', onResize)
  }, [])

  useEffect(() => {
    if (!searchOpen) return
    const onOutsidePointer = (event: PointerEvent) => {
      if (event.target instanceof Node && !searchContainerRef.current?.contains(event.target)) setSearchOpen(false)
    }
    const onEscape = (event: KeyboardEvent) => { if (event.key === 'Escape') setSearchOpen(false) }
    document.addEventListener('pointerdown', onOutsidePointer)
    document.addEventListener('keydown', onEscape)
    return () => {
      document.removeEventListener('pointerdown', onOutsidePointer)
      document.removeEventListener('keydown', onEscape)
    }
  }, [searchOpen])

  useEffect(() => {
    if (!sortPopoverOpen) return

    const updateSortPopoverPosition = () => {
      const trigger = sortButtonRef.current
      if (!trigger) return
      const rect = trigger.getBoundingClientRect()
      setSortPopoverPosition({
        top: rect.bottom + 8,
        right: Math.max(12, window.innerWidth - rect.right),
      })
    }

    const closeOnOutsidePointer = (event: PointerEvent) => {
      const target = event.target
      if (!(target instanceof Node)) return
      if (sortButtonRef.current?.contains(target) || sortPopoverRef.current?.contains(target)) return
      setSortPopoverOpen(false)
    }
    const closeOnEscape = (event: KeyboardEvent) => {
      if (event.key !== 'Escape') return
      setSortPopoverOpen(false)
      sortButtonRef.current?.focus()
    }

    document.addEventListener('pointerdown', closeOnOutsidePointer)
    document.addEventListener('keydown', closeOnEscape)
    window.addEventListener('resize', updateSortPopoverPosition)
    window.addEventListener('scroll', updateSortPopoverPosition, true)
    updateSortPopoverPosition()
    return () => {
      document.removeEventListener('pointerdown', closeOnOutsidePointer)
      document.removeEventListener('keydown', closeOnEscape)
      window.removeEventListener('resize', updateSortPopoverPosition)
      window.removeEventListener('scroll', updateSortPopoverPosition, true)
    }
  }, [sortPopoverOpen])

  useEffect(() => {
    if (!statusPopoverOpen) return

    const updateStatusPopoverPosition = () => {
      const trigger = statusButtonRef.current
      if (!trigger) return
      const rect = trigger.getBoundingClientRect()
      const mobile = window.innerWidth <= 740
      setStatusPopoverPosition({
        top: mobile ? window.innerHeight - 12 : rect.bottom + 8,
        left: mobile ? 12 : Math.max(12, Math.min(rect.left, window.innerWidth - 332)),
        mobile,
      })
    }
    const closeOnOutsidePointer = (event: PointerEvent) => {
      const target = event.target
      if (!(target instanceof Node)) return
      if (statusButtonRef.current?.contains(target) || statusPopoverRef.current?.contains(target)) return
      setStatusPopoverOpen(false)
    }
    const closeOnEscape = (event: KeyboardEvent) => {
      if (event.key !== 'Escape') return
      setStatusPopoverOpen(false)
      statusButtonRef.current?.focus()
    }

    document.addEventListener('pointerdown', closeOnOutsidePointer)
    document.addEventListener('keydown', closeOnEscape)
    window.addEventListener('resize', updateStatusPopoverPosition)
    window.addEventListener('scroll', updateStatusPopoverPosition, true)
    updateStatusPopoverPosition()
    return () => {
      document.removeEventListener('pointerdown', closeOnOutsidePointer)
      document.removeEventListener('keydown', closeOnEscape)
      window.removeEventListener('resize', updateStatusPopoverPosition)
      window.removeEventListener('scroll', updateStatusPopoverPosition, true)
    }
  }, [statusPopoverOpen])

  useEffect(() => {
    const frame = window.requestAnimationFrame(() => { if (isMobile && view !== 'Kartu') setView('Kartu') })
    return () => window.cancelAnimationFrame(frame)
  }, [isMobile, view])

  const projects = useMemo(() => data?.data ?? [], [data?.data])
  const counts = useMemo(() => stageCounts(projects), [projects])
  const filteredProjects = useMemo(() => {
    const normalizedQuery = query.trim().toLocaleLowerCase('id-ID')
    return [...projects].filter((project) => {
      const matchesQuery = `${project.name} ${project.client?.name ?? ''}`.toLocaleLowerCase('id-ID').includes(normalizedQuery)
      const matchesStage = stageFilter === 'all' || project.current_stage?.stage === stageFilter
      const matchesStatus = statusFilter === 'all' || project.status === statusFilter
      return matchesQuery && matchesStage && matchesStatus
    }).sort((a, b) => {
      if (sort === 'az') return a.name.localeCompare(b.name, 'id')
      if (sort === 'za') return b.name.localeCompare(a.name, 'id')
      const delta = new Date(a.created_at).getTime() - new Date(b.created_at).getTime()
      return sort === 'newest' ? -delta : delta
    })
  }, [projects, query, sort, stageFilter, statusFilter])

  const summary = [
    { label: 'Semua Project', value: data?.meta?.total ?? projects.length, icon: 'folder' as const, tone: 'primary' as const, key: 'all' as const },
    { label: 'Recording', value: counts.recording, icon: 'microphone' as const, tone: 'blue' as const, key: 'recording' as const },
    { label: 'Editing', value: counts.editing, icon: 'scissors' as const, tone: 'success' as const, key: 'editing' as const },
    { label: 'Mixing', value: counts.mixing, icon: 'sliders' as const, tone: 'warning' as const, key: 'mixing' as const },
    { label: 'Mastering', value: counts.mastering, icon: 'activity' as const, tone: 'mastering' as const, key: 'mastering' as const },
  ]

  const stageFilters: Array<{ key: StageFilter; label: string; count: number }> = [
    { key: 'all', label: 'Semua tahap', count: data?.meta?.total ?? projects.length },
    ...stages.map((stage) => ({ key: stage.key, label: stage.label, count: counts[stage.key] })),
  ]
  const statusFilters: Array<{ key: ProjectStatus; label: string; tone?: BadgeTone; count: number }> = [
    { key: 'all', label: 'Semua status', count: projects.length },
    ...projectStatuses.map((status) => ({
      ...status,
      count: projects.filter((project) => project.status === status.key).length,
    })),
  ]
  const cardsPerPage = isMobile ? 5 : 6
  const tablePageSize = 7
  const pageSize = view === 'Tabel' ? tablePageSize : cardsPerPage
  const pageCount = Math.max(1, Math.ceil(filteredProjects.length / pageSize))
  const currentPage = Math.min(page, pageCount)
  const visibleProjects = filteredProjects.slice((currentPage - 1) * pageSize, currentPage * pageSize)

  const updateFilters = (callback: () => void) => {
    callback()
    if (page !== 1) setPage(1)
  }

  if (initialDataLoading) return <LoadingScreen label="Memuat project manager" />

  const pendingProjectAssignments = data && data.meta.total === data.data.length
    ? data.data.filter((project) => project.status === 'awaiting_assignment').length
    : undefined

  return (
    <div className="manager-shell">
      <ManagerSideNav managerName={user?.name ?? 'Manager'} pendingProjectAssignments={pendingProjectAssignments} />
      <main className="manager-main manager-projects-page">
        <UpperNavBar path="/project" title="Project" tools={<>
          <Button aria-label="Project Baru" className="manager-create-button" leadingIcon="plus" onClick={() => navigate('/project/buat')}>Project Baru</Button>
          <div className={`manager-search-wrap${searchOpen ? ' is-open' : ''}`} ref={searchContainerRef}>
            <Input aria-label="Cari project, klien, operator" className="manager-search" leadingIcon="search" onChange={(event) => updateFilters(() => setQuery(event.target.value))} placeholder="Cari project, klien, operator..." value={query} />
            <button aria-expanded={searchOpen} aria-label={searchOpen ? 'Tutup pencarian' : 'Buka pencarian'} className="manager-topbar__icon manager-search-toggle" onClick={() => setSearchOpen((open) => !open)} type="button"><Icon name="search" size={20} /></button>
          </div>
          <button aria-label="Pengaturan" className="manager-topbar__icon manager-topbar__settings" type="button"><Icon name="settings" size={20} /></button>
          <button aria-label="Notifikasi" className="manager-topbar__icon" type="button"><Icon name="bell" size={20} /></button>
        </>} />

        <div className="manager-projects-content">
          <section aria-label="Ringkasan project" className="manager-project-stats">
            {summary.map((stat) => <button className="manager-project-stat" key={stat.key} onClick={() => updateFilters(() => setStageFilter(stat.key))} type="button"><StatCard icon={stat.icon} label={stat.label} tone={stat.tone} value={number.format(stat.value)} /></button>)}
          </section>

          <section aria-label="Daftar project" className="manager-project-main">
            <div className="manager-project-toolbar">
              <label className="manager-project-search"><Icon name="search" size={20} /><input aria-label="Cari project atau klien" onChange={(event) => updateFilters(() => setQuery(event.target.value))} placeholder="Cari project atau klien..." value={query} /></label>
              <div className="manager-project-toolbar__actions">
                <div className="manager-project-sort">
                  <button aria-expanded={sortPopoverOpen} aria-haspopup="dialog" aria-label={`Urutkan project, saat ini ${sort === 'az' ? 'Abjad A ke Z' : sort === 'za' ? 'Abjad Z ke A' : sort === 'newest' ? 'Tanggal terbaru' : 'Tanggal terlama'}`} className={`manager-project-sort__trigger${sortPopoverOpen ? ' is-open' : ''}`} onClick={() => setSortPopoverOpen((open) => !open)} ref={sortButtonRef} type="button"><Icon name="filter" size={18} /><Icon name="chevron-down" size={14} /></button>
                  {sortPopoverOpen && createPortal(<div className={`manager-project-sort-layer${isMobile ? ' is-mobile' : ''}`}>
                    <div aria-hidden="true" className="manager-project-sort-layer__backdrop" onClick={() => setSortPopoverOpen(false)} />
                    {isMobile && <div aria-hidden="true" className="manager-project-sort-layer__scrim" onClick={() => setSortPopoverOpen(false)} />}
                    <section aria-label="Urutkan project" aria-modal={isMobile || undefined} className={`manager-project-sort-popover${isMobile ? ' is-mobile' : ''}`} ref={sortPopoverRef} role="dialog" style={{ '--manager-project-sort-top': `${sortPopoverPosition.top}px`, '--manager-project-sort-right': `${sortPopoverPosition.right}px` } as CSSProperties}>
                      <fieldset className="manager-project-sort-group"><legend>Nama project</legend><div role="group" aria-label="Urutkan nama project"><button aria-pressed={sort === 'az'} className={sort === 'az' ? 'is-selected' : ''} onClick={() => updateFilters(() => setSort('az'))} type="button"><span>Abjad A ke Z</span><span aria-hidden="true" className="manager-project-sort-option__indicator">{sort === 'az' && <Icon name="check" size={14} />}</span></button><button aria-pressed={sort === 'za'} className={sort === 'za' ? 'is-selected' : ''} onClick={() => updateFilters(() => setSort('za'))} type="button"><span>Abjad Z ke A</span><span aria-hidden="true" className="manager-project-sort-option__indicator">{sort === 'za' && <Icon name="check" size={14} />}</span></button></div></fieldset>
                      <span aria-hidden="true" className="manager-project-sort-popover__separator" />
                      <fieldset className="manager-project-sort-group"><legend>Tanggal dibuat</legend><div role="group" aria-label="Urutkan tanggal project"><button aria-pressed={sort === 'newest'} className={sort === 'newest' ? 'is-selected' : ''} onClick={() => updateFilters(() => setSort('newest'))} type="button"><span>Tanggal terbaru</span><span aria-hidden="true" className="manager-project-sort-option__indicator">{sort === 'newest' && <Icon name="check" size={14} />}</span></button><button aria-pressed={sort === 'oldest'} className={sort === 'oldest' ? 'is-selected' : ''} onClick={() => updateFilters(() => setSort('oldest'))} type="button"><span>Tanggal terlama</span><span aria-hidden="true" className="manager-project-sort-option__indicator">{sort === 'oldest' && <Icon name="check" size={14} />}</span></button></div></fieldset>
                    </section>
                  </div>, document.body)}
                </div>
                <div aria-label="Mode lihat" className="manager-project-view">
                  <span>Mode Lihat:</span>
                  <div className="manager-project-view__toggle" role="group">
                  <button aria-label="Kartu" aria-pressed={view === 'Kartu'} className={view === 'Kartu' ? 'is-active' : ''} onClick={() => setView('Kartu')} type="button"><span className="manager-project-view__cards-icon" />{view === 'Kartu' && <span>Kartu</span>}</button>
                    <button aria-label="Tabel" aria-pressed={view === 'Tabel'} className={view === 'Tabel' ? 'is-active' : ''} disabled={isMobile} onClick={() => setView('Tabel')} type="button"><span className="manager-project-view__table-icon" />{view === 'Tabel' && <span>Tabel</span>}</button>
                  </div>
                </div>
              </div>
            </div>
            <div aria-label="Filter tahap project" className="manager-project-filters">
              {stageFilters.map((filter) => <button aria-pressed={stageFilter === filter.key} className={`manager-project-filter${stageFilter === filter.key ? ' is-active' : ''}`} key={filter.key} onClick={() => updateFilters(() => setStageFilter(filter.key))} type="button"><span>{filter.label}</span><span>{number.format(filter.count)}</span></button>)}
              <span aria-hidden="true" className="manager-project-filters__separator" />
              <div className="manager-project-status-filter">
                <button aria-expanded={statusPopoverOpen} aria-haspopup="dialog" aria-label={statusFilter === 'all' ? 'Filter status' : `Filter status aktif: ${statusFilters.find((filter) => filter.key === statusFilter)?.label ?? ''}`} className={`manager-project-filter-trigger${statusFilter !== 'all' ? ' is-active' : ''}${statusPopoverOpen ? ' is-open' : ''}`} onClick={() => setStatusPopoverOpen((open) => !open)} ref={statusButtonRef} type="button"><Icon name="filter" size={18} /><span>Filter status</span>{statusFilter !== 'all' && <span className="manager-project-filter-trigger__count">1</span>}</button>
                {statusPopoverOpen && createPortal(<div className={`manager-project-status-layer${statusPopoverPosition.mobile ? ' is-mobile' : ''}`}>
                  <div aria-hidden="true" className="manager-project-status-layer__backdrop" onClick={() => setStatusPopoverOpen(false)} />
                  {statusPopoverPosition.mobile && <div aria-hidden="true" className="manager-project-status-layer__scrim" onClick={() => setStatusPopoverOpen(false)} />}
                  <section aria-label="Filter status project" aria-modal={statusPopoverPosition.mobile || undefined} className={`manager-project-status-popover${statusPopoverPosition.mobile ? ' is-mobile' : ''}`} ref={statusPopoverRef} role="dialog" style={{ '--manager-project-status-top': `${statusPopoverPosition.top}px`, '--manager-project-status-left': `${statusPopoverPosition.left}px` } as CSSProperties}>
                    <header className="manager-project-status-popover__header"><strong>Status project</strong>{statusFilter !== 'all' && <button onClick={() => { updateFilters(() => setStatusFilter('all')); setStatusSearch('') }} type="button">Reset</button>}</header>
                    <label className="manager-project-status-search"><Icon name="search" size={18} /><input aria-label="Cari status project" onChange={(event) => setStatusSearch(event.target.value)} placeholder="Cari status..." value={statusSearch} />{statusSearch && <button aria-label="Hapus pencarian status" onClick={() => setStatusSearch('')} type="button"><Icon name="close" size={16} /></button>}</label>
                    <div aria-label="Status project" className="manager-project-status-options" role="group">
                      {statusFilters.filter((filter) => filter.label.toLocaleLowerCase('id-ID').includes(statusSearch.trim().toLocaleLowerCase('id-ID'))).map((filter) => <button aria-pressed={statusFilter === filter.key} className={`manager-project-status-option${statusFilter === filter.key ? ' is-selected' : ''}`} key={filter.key} onClick={() => updateFilters(() => setStatusFilter(filter.key))} type="button"><span className="manager-project-status-option__label">{filter.tone ? <Badge tone={filter.tone}>{filter.label}</Badge> : filter.label}</span><span className="manager-project-status-option__count">{number.format(filter.count)}</span>{statusFilter === filter.key && <Icon aria-hidden="true" className="manager-project-status-option__check" name="check" size={16} />}</button>)}
                      {statusFilters.filter((filter) => filter.label.toLocaleLowerCase('id-ID').includes(statusSearch.trim().toLocaleLowerCase('id-ID'))).length === 0 && <p className="manager-project-status-empty">Status tidak ditemukan.</p>}
                    </div>
                  </section>
                </div>, document.body)}
              </div>
            </div>
            {error ? <p className="manager-project-state" role="alert">{error}</p> : visibleProjects.length === 0 ? <p className="manager-project-state">Tidak ada project yang sesuai dengan filter.</p> : view === 'Kartu' ? (
              <div className="manager-project-cards-view">
                <div className="manager-project-grid">
                  {visibleProjects.map((project) => {
                    const phase = projectPhase(project.current_stage?.stage ?? null, project.status)
                    const progress = project.progress_percent
                    const stageAction = project.current_stage?.stage_label ? `${project.current_stage.stage_label} berlangsung` : projectStatusLabel(project.status, project.status_label)
                    const projectOperator = project.current_stage?.operator
                    return <ProjectCard className="manager-project-card" client={project.client?.name ?? 'Klien tidak tersedia'} clientAvatarUrl={project.client?.avatar_url ?? undefined} date={formatStartDate(project.started_at ?? project.created_at)} key={project.id} name={project.name} onDetails={() => navigate(`/project/${project.id}`)} operators={projectOperator ? [{ name: projectOperator.name, avatarUrl: projectOperator.avatar_url ?? undefined }] : []} phase={phase} progress={progress} progressLabel={stageAction} />
                  })}
                </div>
                <div aria-label="Navigasi halaman project" className="manager-project-pagination"><button aria-label="Halaman sebelumnya" disabled={currentPage <= 1} onClick={() => setPage(Math.max(1, currentPage - 1))} type="button"><Icon name="chevron-left" size={18} /></button><span>{Array.from({ length: pageCount }, (_, index) => <button aria-current={currentPage === index + 1 ? 'page' : undefined} className={currentPage === index + 1 ? 'is-current' : ''} key={index} onClick={() => setPage(index + 1)} type="button">{number.format(index + 1)}</button>)}</span><button aria-label="Halaman berikutnya" disabled={currentPage >= pageCount} onClick={() => setPage(Math.min(pageCount, currentPage + 1))} type="button"><Icon name="chevron-right" size={18} /></button></div>
              </div>
            ) : <><div className="manager-project-table-wrap"><table className="manager-project-table"><thead><tr><th>Nama project</th><th>Klien</th><th>Tahap</th><th>Status</th><th>Progress</th></tr></thead><tbody>{visibleProjects.map((project) => {
              const clientName = project.client?.name ?? 'Klien tidak tersedia'
              const stage = stages.find((item) => item.key === project.current_stage?.stage)
              const status = projectStatusLabel(project.status, project.status_label)
              return <tr key={project.id} onClick={() => navigate(`/project/${project.id}`)}><td>{project.name}</td><td><span className="manager-project-client"><span aria-hidden="true" className="manager-project-client__avatar">{project.client?.avatar_url ? <img alt="" src={project.client.avatar_url} /> : projectClientInitial(clientName)}</span><span>{clientName}</span></span></td><td>{stage ? <Badge icon={stage.icon} tone={stage.tone}>{stage.label}</Badge> : <span>—</span>}</td><td><Badge tone={projectStatusTone(project.status)}>{status}</Badge></td><td><span className="manager-project-progress-cell"><span aria-hidden="true" className="manager-project-progress-cell__track"><span style={{ width: `${Math.min(100, Math.max(0, project.progress_percent))}%` }} /></span><span>{project.progress_percent}%</span></span></td></tr>
            })}</tbody></table></div><div aria-label="Navigasi halaman project" className="manager-project-pagination"><button aria-label="Halaman sebelumnya" disabled={currentPage <= 1} onClick={() => setPage(Math.max(1, currentPage - 1))} type="button"><Icon name="chevron-left" size={18} /></button><span>{Array.from({ length: pageCount }, (_, index) => <button aria-current={currentPage === index + 1 ? 'page' : undefined} className={currentPage === index + 1 ? 'is-current' : ''} key={index} onClick={() => setPage(index + 1)} type="button">{number.format(index + 1)}</button>)}</span><button aria-label="Halaman berikutnya" disabled={currentPage >= pageCount} onClick={() => setPage(Math.min(pageCount, currentPage + 1))} type="button"><Icon name="chevron-right" size={18} /></button></div></>}
          </section>
        </div>
      </main>
    </div>
  )
}
