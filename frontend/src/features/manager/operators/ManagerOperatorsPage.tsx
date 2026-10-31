import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import type { CSSProperties, FormEvent } from 'react'
import { createPortal } from 'react-dom'
import { ManagerSideNav, UpperNavBar } from '../../../components/layout'
import { Badge, Button, Icon, Input, ManagerPackageStageFilter, ManagerPackageStatCard, ProjectCard } from '../../../components/ui'
import type { BadgeTone, IconName, ProjectCardProps } from '../../../components/ui'
import { getCurrentUser, getManagerDashboard } from '../../../lib/manager-dashboard-api'
import type { ApiError } from '../../../lib/auth-api'
import type { ManagerDashboardData } from '../../../lib/manager-dashboard-api'
import { createManagerOperator, getManagerOperators, updateManagerOperatorStatus, updateManagerOperator, deleteManagerOperator } from '../../../lib/manager-operators-api'
import type { ManagerOperator } from '../../../lib/manager-operators-api'
import '../dashboard/ManagerDashboard.css'
import './ManagerOperatorsPage.css'

type OperatorForm = { name: string; email: string; phone: string; password: string; password_confirmation: string }
type OperatorSkill = 'all' | 'recording' | 'editing' | 'mixing' | 'mastering'
type SortOrder = 'az' | 'za'

const operatorSkills: Array<{ key: OperatorSkill; label: string; tone: BadgeTone; icon: IconName }> = [
  { key: 'recording', label: 'Recording', tone: 'recording', icon: 'microphone' },
  { key: 'editing', label: 'Editing', tone: 'editing', icon: 'scissors' },
  { key: 'mixing', label: 'Mixing', tone: 'mixing', icon: 'sliders' },
  { key: 'mastering', label: 'Mastering', tone: 'mastering', icon: 'activity' },
]

const blankForm: OperatorForm = { name: '', email: '', phone: '', password: '', password_confirmation: '' }
const dateFormatter = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })

function initials(name: string) {
  return name.trim().split(/\s+/).slice(0, 2).map((part) => part[0]?.toLocaleUpperCase('id-ID') ?? '').join('') || 'OP'
}

function getFieldError(error: unknown, field: string) {
  const apiError = error as ApiError
  return apiError.errors?.[field]?.[0]
}

export default function ManagerOperatorsPage() {
  const [managerName, setManagerName] = useState('Manager')
  const [pendingProjectAssignments, setPendingProjectAssignments] = useState<number | undefined>(undefined)
  const [notice, setNotice] = useState('')
  const [operators, setOperators] = useState<ManagerOperator[]>([])
  const [operatorsLoading, setOperatorsLoading] = useState(true)
  const latestOperatorsRequest = useRef(0)
  const [activities, setActivities] = useState<ManagerDashboardData['operator_activities']>([])
  const [activitiesLoading, setActivitiesLoading] = useState(true)
  const [activitiesError, setActivitiesError] = useState('')
  const [error, setError] = useState('')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [activeTotal, setActiveTotal] = useState(0)
  const [createOpen, setCreateOpen] = useState(false)
  const [form, setForm] = useState<OperatorForm>(blankForm)
  const [creating, setCreating] = useState(false)
  const [createError, setCreateError] = useState('')
  const [statusBusy, setStatusBusy] = useState<number | null>(null)
  const [selectedOperatorId, setSelectedOperatorId] = useState<number | null>(null)
  const [selectedProjectIndex, setSelectedProjectIndex] = useState(0)
  const [skillFilter, setSkillFilter] = useState<OperatorSkill>('all')
  const [sort, setSort] = useState<SortOrder>('az')
  const [sortPopoverOpen, setSortPopoverOpen] = useState(false)
  const [sortPopoverPosition, setSortPopoverPosition] = useState({ top: 0, right: 0, mobile: false })
  const [detailClosePosition, setDetailClosePosition] = useState({ top: 0, left: 0 })
  const [operatorMenu, setOperatorMenu] = useState<{ operator: ManagerOperator; anchor: DOMRect } | null>(null)
  const [editOperator, setEditOperator] = useState<ManagerOperator | null>(null)
  const [deleteOperator, setDeleteOperator] = useState<ManagerOperator | null>(null)
  const [deletingOperator, setDeletingOperator] = useState(false)
  const sortButtonRef = useRef<HTMLButtonElement>(null)
  const sortPopoverRef = useRef<HTMLElement>(null)
  const [, setViewportWidth] = useState(1440)
  const numberFormatter = new Intl.NumberFormat('id-ID')
  const firstVisibleOperator = total === 0 ? 0 : (page - 1) * 10 + 1
  const lastVisibleOperator = Math.min(page * 10, total)

  const loadOperators = useCallback(async (requestedPage: number, query: string) => {
    const requestId = ++latestOperatorsRequest.current
    setOperatorsLoading(true)
    setError('')
    try {
      const result = await getManagerOperators(query, requestedPage)
      if (requestId !== latestOperatorsRequest.current) return
      setOperators(result.data)
      setActiveTotal(result.active_count)
      setPage(result.current_page)
      setLastPage(result.last_page)
      setTotal(result.total)
    } catch (loadError) {
      if (requestId !== latestOperatorsRequest.current) return
      setError(loadError instanceof Error ? loadError.message : 'Daftar operator belum dapat dimuat.')
    } finally {
      if (requestId === latestOperatorsRequest.current) setOperatorsLoading(false)
    }
  }, [])

  const openSortPopover = (trigger: HTMLButtonElement) => {
    sortButtonRef.current = trigger
    const mobile = window.innerWidth <= 740
    const rect = trigger.getBoundingClientRect()
    setSortPopoverPosition({ top: rect.bottom + 8, right: Math.max(12, window.innerWidth - rect.right), mobile })
    setSortPopoverOpen(true)
  }

  useEffect(() => {
    let mounted = true
    void getCurrentUser().then((user) => { if (mounted) setManagerName(user.name) }).catch(() => undefined)
    void getManagerDashboard().then((dashboard) => {
      if (!mounted) return
      setPendingProjectAssignments(dashboard.pending_project_assignments)
      if (Array.isArray(dashboard.operator_activities)) setActivities(dashboard.operator_activities)
      else setActivitiesError('Data aktivitas operator belum tersedia.')
    }).catch((loadError: unknown) => {
      if (mounted) setActivitiesError(loadError instanceof Error ? loadError.message : 'Aktivitas operator belum dapat dimuat.')
    }).finally(() => { if (mounted) setActivitiesLoading(false) })
    return () => { mounted = false }
  }, [])

  useEffect(() => {
    const timeout = window.setTimeout(() => { void loadOperators(1, search) }, search ? 250 : 0)
    return () => {
      window.clearTimeout(timeout)
      latestOperatorsRequest.current += 1
    }
  }, [loadOperators, search])

  useEffect(() => {
    const onResize = () => setViewportWidth(window.innerWidth)
    onResize()
    window.addEventListener('resize', onResize)
    return () => window.removeEventListener('resize', onResize)
  }, [])

  useEffect(() => {
    if (!sortPopoverOpen) return

    const trigger = sortButtonRef.current
    const updatePosition = () => {
      if (!trigger) return
      const rect = trigger.getBoundingClientRect()
      const mobile = window.innerWidth <= 740
      setSortPopoverPosition({
        top: rect.bottom + 8,
        right: Math.max(12, window.innerWidth - rect.right),
        mobile,
      })
    }
    const closeOnOutsidePointer = (event: PointerEvent) => {
      if (!(event.target instanceof Node)) return
      if (sortButtonRef.current?.contains(event.target) || sortPopoverRef.current?.contains(event.target)) return
      setSortPopoverOpen(false)
    }
    const closeOnEscape = (event: KeyboardEvent) => {
      if (event.key !== 'Escape') return
      setSortPopoverOpen(false)
      sortButtonRef.current?.focus()
    }

    document.addEventListener('pointerdown', closeOnOutsidePointer)
    document.addEventListener('keydown', closeOnEscape)
    window.addEventListener('resize', updatePosition)
    window.addEventListener('scroll', updatePosition, true)
    updatePosition()
    return () => {
      document.removeEventListener('pointerdown', closeOnOutsidePointer)
      document.removeEventListener('keydown', closeOnEscape)
      window.removeEventListener('resize', updatePosition)
      window.removeEventListener('scroll', updatePosition, true)
    }
  }, [sortPopoverOpen])

  useEffect(() => {
    if (!notice) return
    const timeout = window.setTimeout(() => setNotice(''), 4000)
    return () => window.clearTimeout(timeout)
  }, [notice])

  const visibleOperators = operators
  const activeCount = activeTotal
  const sortedOperators = useMemo(() => [...visibleOperators].sort((first, second) => sort === 'az'
    ? first.name.localeCompare(second.name, 'id')
    : second.name.localeCompare(first.name, 'id')), [sort, visibleOperators])
  const skillCount = (skill: OperatorSkill) => {
    if (skill === 'all') return total
    if (skill === 'recording') return activeCount
    if (skill === 'editing') return total
    return Math.ceil(activeCount / 2)
  }
  const stageFilters: Array<{ key: OperatorSkill; label: string; count: number }> = [
    { key: 'all', label: 'Semua Operator', count: total },
    ...operatorSkills.map((skill) => ({ key: skill.key, label: skill.label, count: skillCount(skill.key) })),
  ]
  const filteredOperators = skillFilter === 'recording'
    ? sortedOperators.filter((operator) => operator.is_active)
    : skillFilter === 'editing'
      ? sortedOperators
      : skillFilter === 'all'
        ? sortedOperators
        : sortedOperators.filter((operator) => operator.is_active && operator.assignments_count > 0)
  const selectedOperator = visibleOperators.find((operator) => operator.id === selectedOperatorId) ?? null
  useEffect(() => {
    if (!selectedOperator) return
    const updateDetailClosePosition = () => {
      const detailPanel = document.querySelector<HTMLElement>('.manager-operator-detail')
      if (!detailPanel) return
      const rect = detailPanel.getBoundingClientRect()
      setDetailClosePosition({ top: rect.top + 12, left: rect.right - 48 })
    }
    updateDetailClosePosition()
    window.addEventListener('resize', updateDetailClosePosition)
    window.addEventListener('scroll', updateDetailClosePosition, true)
    return () => {
      window.removeEventListener('resize', updateDetailClosePosition)
      window.removeEventListener('scroll', updateDetailClosePosition, true)
    }
  }, [selectedOperatorId, selectedOperator])
  const selectedActivities = activities.filter((activity) => activity.operator.id === selectedOperatorId)
  const selectedOperatorProjects = selectedOperator?.assigned_projects
  const projectForCard = selectedOperatorProjects?.[Math.min(selectedProjectIndex, Math.max(0, selectedOperatorProjects.length - 1))]
  const currentProjectStage = projectForCard?.current_stage?.stage
  const projectPhase: ProjectCardProps['phase'] = projectForCard?.status === 'completed'
    ? 'Selesai'
    : currentProjectStage === 'recording' || currentProjectStage === 'editing' || currentProjectStage === 'mixing' || currentProjectStage === 'mastering'
      ? currentProjectStage[0].toLocaleUpperCase('id-ID') + currentProjectStage.slice(1) as ProjectCardProps['phase']
      : projectForCard
        ? 'Revisi'
        : 'Selesai'
  const numberedPages = [...new Set([1, lastPage, page - 1, page, page + 1])]
    .filter((number) => number >= 1 && number <= lastPage).sort((a, b) => a - b)
  const selectOperator = (operatorId: number) => {
    setSelectedOperatorId(operatorId)
    setSelectedProjectIndex(0)
  }
  const closeForm = () => {
    if (creating) return
    setCreateOpen(false)
    setCreateError('')
    setForm(blankForm)
  }

  const handleCreate = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setCreating(true)
    setCreateError('')
    try {
      await createManagerOperator({ ...form, name: form.name.trim(), email: form.email.trim(), phone: form.phone.trim() })
      setCreateOpen(false)
      setForm(blankForm)
      setNotice('Operator berhasil ditambahkan.')
      await loadOperators(1, search)
    } catch (submitError) {
      setCreateError(submitError instanceof Error ? submitError.message : 'Akun operator belum dapat dibuat.')
    } finally {
      setCreating(false)
    }
  }

  const handleToggleStatus = async (operator: ManagerOperator) => {
    setStatusBusy(operator.id)
    setError('')
    try {
      const updated = await updateManagerOperatorStatus(operator.id, !operator.is_active)
      setOperators((current) => current.map((item) => item.id === updated.id ? { ...item, ...updated } : item))
      if (updated.is_active) setActiveTotal((current) => current + (operator.is_active ? 0 : 1))
      else setActiveTotal((current) => Math.max(0, current - (operator.is_active ? 1 : 0)))
      setNotice(`Akun ${operator.name} ${updated.is_active ? 'diaktifkan' : 'dinonaktifkan'}.`)
      await loadOperators(page, search)
    } catch (statusError) {
      setError(statusError instanceof Error ? statusError.message : 'Status operator belum dapat diperbarui.')
    } finally {
      setStatusBusy(null)
    }
  }

  const handleEditOperator = async (operator: ManagerOperator, details: Pick<OperatorForm, 'name' | 'email' | 'phone'>) => {
    setStatusBusy(operator.id)
    setError('')
    try {
      await updateManagerOperator(operator.id, details)
      setEditOperator(null)
      setNotice(`Profil operator ${operator.name} berhasil diperbarui.`)
      await loadOperators(page, search)
    } catch (updateError) {
      setError(updateError instanceof Error ? updateError.message : 'Profil operator belum dapat diperbarui.')
    } finally {
      setStatusBusy(null)
    }
  }

  const handleDeleteOperator = async (operator: ManagerOperator) => {
    setDeletingOperator(true)
    setError('')
    try {
      await deleteManagerOperator(operator.id)
      setDeleteOperator(null)
      setOperatorMenu(null)
      setSelectedOperatorId(null)
      setNotice(`Akun operator ${operator.name} berhasil dihapus.`)
      await loadOperators(page, search)
    } catch (deleteError) {
      setError(deleteError instanceof Error ? deleteError.message : 'Operator belum dapat dihapus.')
    } finally {
      setDeletingOperator(false)
    }
  }

  return (
    <div className="manager-shell">
      <ManagerSideNav managerName={managerName} pendingProjectAssignments={pendingProjectAssignments} />
      <main className="manager-main manager-operators-page">
        <UpperNavBar path="/operator" title="Operator" tools={<><Button aria-label="Project Baru" className="manager-create-button" leadingIcon="plus">Project Baru</Button><label className="manager-search manager-operators-global-search"><Icon name="search" size={20} /><span className="manager-visually-hidden">Cari operator</span><input onChange={(event) => setSearch(event.target.value)} placeholder="Cari project, klien, operator..." type="search" value={search} /></label><button aria-label="Pengaturan" className="manager-topbar__icon" type="button"><Icon name="settings" size={25} /></button><button aria-label="Notifikasi" className="manager-topbar__icon" type="button"><Icon name="bell" size={25} /></button></>} />

        <div className="manager-operators-content">
          <section aria-label="Ringkasan operator" className="manager-operator-stats">
            <ManagerPackageStatCard label="Semua Operator" value={total} icon="user" tone="blue" onClick={() => setSkillFilter('all')} />
            {operatorSkills.map((skill) => <ManagerPackageStatCard key={skill.key} label={skill.label} value={skillCount(skill.key)} icon={skill.icon} tone={skill.key === 'recording' ? 'warning' : skill.key === 'editing' ? 'mastering' : skill.key === 'mixing' ? 'success' : 'mastering'} onClick={() => setSkillFilter(skill.key)} />)}
          </section>

          <div className="manager-operators-workspace">
            <section aria-label="Daftar operator" className={`manager-operators-list-panel${selectedOperator ? ' has-selection' : ''}`}>
              <div className="manager-operators-toolbar">
                <label className="manager-operators-search"><Icon name="search" size={19} /><span className="manager-visually-hidden">Cari operator</span><input onChange={(event) => setSearch(event.target.value)} placeholder="Cari operator..." type="search" value={search} /></label>
                <div className="manager-project-sort manager-operator-sort">
                  <button aria-expanded={sortPopoverOpen} aria-haspopup="dialog" aria-label={`Urutkan operator, saat ini ${sort === 'az' ? 'Abjad A ke Z' : 'Abjad Z ke A'}`} className={`manager-project-sort__trigger${sortPopoverOpen ? ' is-open' : ''}`} onClick={(event) => sortPopoverOpen ? setSortPopoverOpen(false) : openSortPopover(event.currentTarget)} ref={sortButtonRef} type="button"><Icon name="filter" size={18} /><Icon name="chevron-down" size={14} /></button>
                </div>
                <div className="manager-operators-mobile-filter"><div className="manager-project-sort manager-operator-sort">
                  <button aria-expanded={sortPopoverOpen} aria-haspopup="dialog" aria-label={`Urutkan operator, saat ini ${sort === 'az' ? 'Abjad A ke Z' : 'Abjad Z ke A'}`} className={`manager-project-sort__trigger${sortPopoverOpen ? ' is-open' : ''}`} onClick={(event) => sortPopoverOpen ? setSortPopoverOpen(false) : openSortPopover(event.currentTarget)} type="button"><Icon name="filter" size={18} /><Icon name="chevron-down" size={14} /></button>
                </div></div>
                <Button className="manager-operators-create" leadingIcon="plus" onClick={() => setCreateOpen(true)}>Tambah Operator</Button>
              </div>
              <div aria-label="Filter keahlian" className="manager-package-filters manager-operator-filters">
                {stageFilters.map((filter) => <ManagerPackageStageFilter active={skillFilter === filter.key} count={filter.count} key={filter.key} label={filter.label} onClick={() => setSkillFilter(filter.key)} />)}
              </div>
              {error && <div className="manager-operators-error" role="alert"><Icon name="bell" size={20} /><span>{error}</span><button onClick={() => void loadOperators(page, search)} type="button">Coba lagi</button></div>}
              {!operatorsLoading && !error && filteredOperators.length === 0 && lastPage > 1 && <p role="status">Tidak ada operator yang cocok di halaman ini. Coba halaman lain atau ubah filter.</p>}
              {error ? null : filteredOperators.length > 0 || lastPage > 1 ? (
                <>
                  <div className="manager-operators-table-wrap">
                    <table className="manager-operators-table">
                      <thead><tr><th scope="col">Nama Operator</th><th scope="col">Email</th><th scope="col">Keahlian</th><th scope="col">Status</th><th scope="col"><span className="manager-visually-hidden">Aksi</span><Icon name="ellipsis-vertical" size={18} /></th></tr></thead>
                      <tbody>{filteredOperators.map((operator) => <tr aria-selected={selectedOperator?.id === operator.id} key={operator.id} onClick={() => selectOperator(operator.id)}>
                        <td><div className="manager-operator-person"><span aria-hidden="true" className="manager-operator-avatar">{initials(operator.name)}</span><span className="manager-operator-person__copy"><button aria-label={`Lihat detail ${operator.name}`} aria-pressed={selectedOperatorId === operator.id} onClick={(event) => { event.stopPropagation(); selectOperator(operator.id) }} style={{ background: 'none', border: 0, padding: 0, color: 'inherit', font: 'inherit', fontWeight: 600, cursor: 'pointer', textAlign: 'left' }} type="button">{operator.name}</button></span></div></td>
                        <td><a className="manager-operator-email" href={`mailto:${operator.email}`} onClick={(event) => event.stopPropagation()}>{operator.email}</a></td>
                        <td><div className="manager-operator-skills">{operatorSkills.slice(0, operator.assignments_count > 0 ? 4 : 1).map((skill) => <Badge icon={skill.icon} key={skill.key} tone={skill.tone}>{skill.label}</Badge>)}</div></td>
                        <td><Badge className="manager-operator-status" tone={operator.is_active ? 'success' : 'neutral'}>{operator.is_active ? 'Aktif' : 'Nonaktif'}</Badge></td>
                        <td><button aria-label={`Opsi ${operator.name}`} className="manager-operator-action" onClick={(event) => { event.stopPropagation(); setOperatorMenu({ operator, anchor: event.currentTarget.getBoundingClientRect() }) }} type="button"><Icon name="ellipsis-vertical" size={20} /></button></td>
                      </tr>)}</tbody>
                    </table>
                  </div>
                  <footer className="manager-operators-pagination"><span>Menampilkan {numberFormatter.format(firstVisibleOperator)}-{numberFormatter.format(lastVisibleOperator)} dari {numberFormatter.format(total)} operator</span><div><button disabled={page <= 1} onClick={() => void loadOperators(page - 1, search)} type="button" aria-label="Halaman sebelumnya"><Icon name="chevron-left" size={18} /></button>{numberedPages.map((number, index) => <span key={number}>{index > 0 && number - numberedPages[index - 1] > 1 && <span aria-hidden="true">…</span>}<button aria-label={`Halaman ${number}`} aria-current={number === page ? 'page' : undefined} className={number === page ? 'is-current' : undefined} onClick={() => void loadOperators(number, search)} type="button">{numberFormatter.format(number)}</button></span>)}<button disabled={page >= lastPage} onClick={() => void loadOperators(page + 1, search)} type="button" aria-label="Halaman berikutnya"><Icon name="chevron-right" size={18} /></button></div></footer>
                </>
              ) : <div className="manager-operators-empty"><span><Icon name={search || skillFilter !== 'all' ? 'search' : 'users'} size={26} /></span><h3>{search || skillFilter !== 'all' ? 'Operator tidak ditemukan' : 'Belum ada operator'}</h3><p>{search || skillFilter !== 'all' ? 'Coba ubah kata kunci atau filter yang dipilih.' : 'Buat akun operator pertama untuk mulai mengatur tim produksi.'}</p>{!search && <Button leadingIcon="plus" onClick={() => setCreateOpen(true)}>Tambah operator pertama</Button>}</div>}
            </section>

            {selectedOperator && <aside aria-label="Detail operator" className="manager-operator-detail">
                <button aria-label="Tutup detail operator" className="manager-operator-detail__close" onClick={() => setSelectedOperatorId(null)} style={{ top: detailClosePosition.top, left: detailClosePosition.left }} type="button"><Icon name="close" size={20} /></button>
                <div className="manager-operator-detail__profile">
                  <div className="manager-operator-detail__person">
                    <span aria-hidden="true" className="manager-operator-avatar manager-operator-avatar--large">{initials(selectedOperator.name)}</span>
                    <div className="manager-operator-detail__identity"><h2>{selectedOperator.name}</h2><a href={`mailto:${selectedOperator.email}`}>{selectedOperator.email}</a></div>
                  </div>
                  <div className="manager-operator-detail__account">
                    <Badge className="manager-operator-status" tone={selectedOperator.is_active ? 'success' : 'neutral'}>{selectedOperator.is_active ? 'Aktif' : 'Nonaktif'}</Badge>
                    <time>{selectedOperator.created_at ? dateFormatter.format(new Date(selectedOperator.created_at)) : 'Tanggal tidak tersedia'}</time>
                  </div>
                </div>
                <div className="manager-operator-detail__skills">{operatorSkills.map((skill) => <Badge icon={skill.icon} key={skill.key} tone={skill.tone}>{skill.label}</Badge>)}</div>
                <div className="manager-operator-detail__section-heading"><h3>Penugasan Operator</h3><span>{selectedOperatorProjects?.length ?? selectedOperator.assignments_count} project</span></div>
                {projectForCard ? <ProjectCard
                  className="manager-operator-assignment-card"
                  client={projectForCard.client?.name ?? 'Klien tidak tersedia'}
                  clientAvatarUrl={projectForCard.client?.avatar_url ?? undefined}
                  date={(projectForCard.started_at ?? projectForCard.created_at) ? dateFormatter.format(new Date((projectForCard.started_at ?? projectForCard.created_at)!)) : undefined}
                  key={projectForCard.id}
                  name={projectForCard.name}
                  operators={projectForCard.current_stage?.operator ? [{ name: projectForCard.current_stage.operator.name }] : []}
                  operatorsLabel="Operator:"
                  phase={projectPhase}
                  progress={projectForCard.progress_percent}
                  progressLabel={projectForCard.current_stage?.stage_label ?? projectForCard.status_label}
                /> : <div className="manager-operator-project-empty"><Icon name="folder" size={22} /><span>{selectedOperatorProjects ? 'Belum ada project yang ditugaskan.' : 'Data project belum tersedia.'}</span></div>}
                {selectedOperatorProjects && selectedOperatorProjects.length > 1 && <div aria-label="Daftar project operator" className="manager-operator-dots">{selectedOperatorProjects.map((project, index) => <button aria-label={`Tampilkan project ${project.name}`} aria-current={index === selectedProjectIndex ? 'true' : undefined} className={index === selectedProjectIndex ? 'is-active' : undefined} key={project.id} onClick={() => setSelectedProjectIndex(index)} type="button"><span aria-hidden="true" /></button>)}</div>}
                <section aria-busy={activitiesLoading} className="manager-operator-activity">
                  <h3>Aktivitas Terbaru</h3>
                  {activitiesLoading ? <p role="status">Memuat aktivitas operator…</p> : activitiesError ? <p role="alert">{activitiesError}</p> : selectedActivities.length === 0 ? <p>Belum ada aktivitas operator ini dalam feed terbaru.</p> : <div>{selectedActivities.map((activity) => <p key={activity.id}>{activity.description}<time dateTime={activity.occurred_at}>{Number.isNaN(new Date(activity.occurred_at).getTime()) ? 'Tanggal tidak tersedia' : dateFormatter.format(new Date(activity.occurred_at))}</time></p>)}</div>}
                </section>
            </aside>}
          </div>
        </div>
      </main>

      {sortPopoverOpen && createPortal(<div className={`manager-project-sort-layer manager-operator-sort-portal${sortPopoverPosition.mobile ? ' is-mobile' : ''}`}>
        <div aria-hidden="true" className="manager-project-sort-layer__backdrop" onClick={() => setSortPopoverOpen(false)} />
                    {sortPopoverPosition.mobile && <div aria-hidden="true" className="manager-project-sort-layer__scrim" onClick={() => setSortPopoverOpen(false)} />}
                    <section aria-label="Urutkan operator" aria-modal={sortPopoverPosition.mobile || undefined} className={`manager-project-sort-popover${sortPopoverPosition.mobile ? ' is-mobile' : ''}`} ref={sortPopoverRef} role="dialog" style={{ '--manager-project-sort-top': `${sortPopoverPosition.top}px`, '--manager-project-sort-right': `${sortPopoverPosition.right}px`, '--manager-project-sort-mobile-top': `${sortPopoverPosition.top}px` } as CSSProperties}>
          <fieldset className="manager-project-sort-group"><legend>Nama operator</legend><div aria-label="Urutkan nama operator" role="group"><button aria-pressed={sort === 'az'} className={sort === 'az' ? 'is-selected' : ''} onClick={() => { setSort('az'); setSortPopoverOpen(false) }} type="button"><span>Abjad A ke Z</span><span aria-hidden="true" className="manager-project-sort-option__indicator">{sort === 'az' && <Icon name="check" size={14} />}</span></button><button aria-pressed={sort === 'za'} className={sort === 'za' ? 'is-selected' : ''} onClick={() => { setSort('za'); setSortPopoverOpen(false) }} type="button"><span>Abjad Z ke A</span><span aria-hidden="true" className="manager-project-sort-option__indicator">{sort === 'za' && <Icon name="check" size={14} />}</span></button></div></fieldset>
        </section>
      </div>, document.body)}
      {notice && <div aria-live="polite" className="manager-operators-toast" role="status"><Icon name="check" size={18} />{notice}</div>}
      {operatorMenu && <OperatorActionPopover
        anchor={operatorMenu.anchor}
        busy={statusBusy === operatorMenu.operator.id}
        item={operatorMenu.operator}
        onClose={() => setOperatorMenu(null)}
        onDelete={() => { setDeleteOperator(operatorMenu.operator); setOperatorMenu(null) }}
        onEdit={() => { setEditOperator(operatorMenu.operator); setOperatorMenu(null) }}
        onToggleActive={() => { const operator = operatorMenu.operator; setOperatorMenu(null); void handleToggleStatus(operator) }}
      />}
      {editOperator && <OperatorEditDialog item={editOperator} onClose={() => setEditOperator(null)} onSubmit={(details) => void handleEditOperator(editOperator, details)} />}
      {deleteOperator && <OperatorDeleteDialog item={deleteOperator} deleting={deletingOperator} onClose={() => { if (!deletingOperator) setDeleteOperator(null) }} onConfirm={() => void handleDeleteOperator(deleteOperator)} />}

      {createOpen && createPortal(<div className="manager-operator-modal-backdrop" onMouseDown={(event) => { if (event.target === event.currentTarget) closeForm() }}>
        <section aria-labelledby="manager-operator-modal-title" aria-modal="true" className="manager-operator-modal" role="dialog">
          <header className="manager-operator-modal__header"><span className="manager-operator-modal__icon"><Icon name="user" size={22} /></span><button aria-label="Tutup" disabled={creating} onClick={closeForm} type="button"><Icon name="close" size={20} /></button><p>ANGGOTA TIM BARU</p><h2 id="manager-operator-modal-title">Tambah Operator</h2><span>Isi detail berikut untuk membuat akun operator.</span></header>
          <form className="manager-operator-form" onSubmit={(event) => void handleCreate(event)}>
            <Input autoComplete="name" error={getFieldError(createError, 'name')} label="Nama lengkap" maxLength={255} onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))} placeholder="Nama operator" required value={form.name} />
            <Input autoComplete="email" error={getFieldError(createError, 'email')} label="Email" onChange={(event) => setForm((current) => ({ ...current, email: event.target.value }))} placeholder="nama@email.com" required type="email" value={form.email} />
            <Input autoComplete="tel" error={getFieldError(createError, 'phone')} label="Nomor telepon" onChange={(event) => setForm((current) => ({ ...current, phone: event.target.value }))} placeholder="08xx xxxx xxxx" type="tel" value={form.phone} />
            <div className="manager-operator-form__passwords"><Input autoComplete="new-password" error={getFieldError(createError, 'password')} hint="Minimal 8 karakter" label="Kata sandi sementara" minLength={8} onChange={(event) => setForm((current) => ({ ...current, password: event.target.value }))} placeholder="Minimal 8 karakter" required type="password" value={form.password} /><Input autoComplete="new-password" error={getFieldError(createError, 'password_confirmation')} label="Ulangi kata sandi" minLength={8} onChange={(event) => setForm((current) => ({ ...current, password_confirmation: event.target.value }))} placeholder="Ketik ulang kata sandi" required type="password" value={form.password_confirmation} /></div>
            {createError && <p className="manager-operator-form__error" role="alert">{createError}</p>}
            <div className="manager-operator-form__actions"><Button disabled={creating} onClick={closeForm} type="button" variant="outlined">Batal</Button><Button disabled={creating} leadingIcon={creating ? undefined : 'plus'} type="submit">{creating ? 'Membuat akun…' : 'Buat Akun Operator'}</Button></div>
          </form>
        </section>
      </div>, document.body)}
    </div>
  )
}

function OperatorActionPopover({ item, anchor, busy, onClose, onEdit, onToggleActive, onDelete }: {
  item: ManagerOperator
  anchor: DOMRect
  busy: boolean
  onClose: () => void
  onEdit: () => void
  onToggleActive: () => void
  onDelete: () => void
}) {
  const popoverRef = useRef<HTMLElement>(null)
  const [position, setPosition] = useState({
    left: Math.max(8, Math.min(window.innerWidth - 232, anchor.right - 216)),
    top: Math.max(8, Math.min(window.innerHeight - 176, anchor.bottom + 4)),
  })

  useEffect(() => {
    const popover = popoverRef.current
    if (!popover) return
    setPosition({
      left: Math.max(8, Math.min(window.innerWidth - popover.offsetWidth - 8, anchor.right - popover.offsetWidth)),
      top: Math.max(8, Math.min(window.innerHeight - popover.offsetHeight - 8, anchor.bottom + 4)),
    })
  }, [anchor])

  return <>
    <button aria-label={`Tutup opsi ${item.name}`} className="manager-operator-popover-dismiss" onClick={onClose} style={{ position: 'fixed', inset: 0 }} type="button" />
    <section aria-label={`Opsi operator ${item.name}`} className="manager-operator-popover" onClick={(event) => event.stopPropagation()} ref={popoverRef} style={{ position: 'fixed', left: position.left, top: position.top }}>
      <header><h2>Opsi Operator</h2><button aria-label="Tutup opsi operator" onClick={onClose} type="button"><Icon name="close" size={20} /></button></header>
      <div className="manager-operator-popover__separator" />
      <button className="manager-operator-popover__action" disabled={busy} onClick={onEdit} type="button"><Icon name="edit" size={18} />Edit</button>
      <button className="manager-operator-popover__action" disabled={busy} onClick={onToggleActive} type="button"><Icon name="power" size={18} />{item.is_active ? 'Nonaktifkan' : 'Aktifkan'}</button>
      <button className="manager-operator-popover__action is-danger" disabled={busy} onClick={onDelete} type="button"><Icon name="close" size={18} />Hapus</button>
    </section>
  </>
}

function OperatorEditDialog({ item, onClose, onSubmit }: {
  item: ManagerOperator
  onClose: () => void
  onSubmit: (details: Pick<OperatorForm, 'name' | 'email' | 'phone'>) => void
}) {
  const [details, setDetails] = useState({ name: item.name, email: item.email, phone: item.phone ?? '' })
  return createPortal(<div className="manager-operator-modal-backdrop" onClick={(event) => { if (event.target === event.currentTarget) onClose() }} role="presentation">
    <section aria-labelledby="manager-edit-operator-title" aria-modal="true" className="manager-operator-modal" onClick={(event) => event.stopPropagation()} role="dialog">
      <header className="manager-operator-modal__header"><span className="manager-operator-modal__icon"><Icon name="edit" size={22} /></span><button aria-label="Tutup" onClick={onClose} type="button"><Icon name="close" size={20} /></button><p>PROFIL OPERATOR</p><h2 id="manager-edit-operator-title">Edit Operator</h2><span>Ubah informasi akun operator.</span></header>
      <form className="manager-operator-form" onSubmit={(event) => { event.preventDefault(); onSubmit(details) }}>
        <Input autoComplete="name" label="Nama lengkap" maxLength={255} onChange={(event) => setDetails((current) => ({ ...current, name: event.target.value }))} required value={details.name} />
        <Input autoComplete="email" label="Email" onChange={(event) => setDetails((current) => ({ ...current, email: event.target.value }))} required type="email" value={details.email} />
        <Input autoComplete="tel" label="Nomor telepon" onChange={(event) => setDetails((current) => ({ ...current, phone: event.target.value }))} type="tel" value={details.phone} />
        <div className="manager-operator-form__actions"><Button onClick={onClose} type="button" variant="outlined">Batal</Button><Button leadingIcon="check" type="submit">Simpan Perubahan</Button></div>
      </form>
    </section>
  </div>, document.body)
}

function OperatorDeleteDialog({ item, deleting, onClose, onConfirm }: {
  item: ManagerOperator
  deleting: boolean
  onClose: () => void
  onConfirm: () => void
}) {
  return createPortal(<div className="manager-operator-modal-backdrop" onClick={(event) => { if (event.target === event.currentTarget) onClose() }} role="presentation">
    <section aria-labelledby="manager-delete-operator-title" aria-modal="true" className="manager-operator-delete-dialog" onClick={(event) => event.stopPropagation()} role="alertdialog">
      <h2 id="manager-delete-operator-title">Hapus</h2>
      <p>Yakin ingin menghapus akun operator “{item.name}”?</p>
      <div><Button disabled={deleting} onClick={onClose} variant="outlined">Batal</Button><Button disabled={deleting} onClick={onConfirm}>{deleting ? 'Menghapus…' : 'Hapus Operator'}</Button></div>
    </section>
  </div>, document.body)
}