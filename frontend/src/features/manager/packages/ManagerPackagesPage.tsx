import { useEffect, useMemo, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { useNavigate } from 'react-router-dom'
import { ManagerSideNav, UpperNavBar } from '../../../components/layout'
import { Badge, Button, Icon, Input, LoadingScreen, PackageCreateModal } from '../../../components/ui'
import { getCurrentUser } from '../../../lib/auth-api'
import type { ManagerUser } from '../../../lib/manager-dashboard-api'
import type { ApiError } from '../../../lib/auth-api'
import { calculateManagerPackagePrice, createManagerPackage, deleteManagerPackage, getManagerBasePrices, getManagerPackages, getManagerPackageSummary, updateManagerPackageActive } from '../../../lib/manager-packages-api'
import type { ManagerPackage, ManagerPackageSummary } from '../../../lib/manager-packages-api'
import { PackageCard } from './PackageCard'
import { PackageStatCard } from './PackageStatCard'
import { PackageStageFilter } from './PackageStageFilter'
import '../dashboard/ManagerDashboard.css'
import './ManagerPackagesPage.css'

const currency = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 })
const number = new Intl.NumberFormat('id-ID')

const stageInfo = [
  { key: 'recording', label: 'Recording', icon: 'microphone', tone: 'recording' },
  { key: 'editing', label: 'Editing', icon: 'scissors', tone: 'editing' },
  { key: 'mixing', label: 'Mixing', icon: 'sliders', tone: 'mixing' },
  { key: 'mastering', label: 'Mastering', icon: 'activity', tone: 'mastering' },
] as const

type ViewMode = 'Kartu' | 'Tabel'
type StageFilter = 'all' | 'recording' | 'editing' | 'mixing' | 'mastering'
type PackageStatTone = 'primary' | 'blue' | 'success' | 'warning' | 'mastering'

export default function ManagerPackagesPage() {
  const navigate = useNavigate()
  const [manager, setManager] = useState<ManagerUser | null>(null)
  const [packages, setPackages] = useState<ManagerPackage[]>([])
  const [summary, setSummary] = useState<ManagerPackageSummary>({ total: 0, by_stage: { recording: 0, editing: 0, mixing: 0, mastering: 0 } })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [errorTitle, setErrorTitle] = useState('Paket belum dapat dimuat')
  const [summaryError, setSummaryError] = useState(false)
  const [query, setQuery] = useState('')
  const [stageFilter, setStageFilter] = useState<StageFilter>('all')
  const [view, setView] = useState<ViewMode>('Kartu')
  const [activePackageMenu, setActivePackageMenu] = useState<{ item: ManagerPackage; anchor: DOMRect } | null>(null)
  const [detailPackage, setDetailPackage] = useState<ManagerPackage | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<ManagerPackage | null>(null)
  const [deleting, setDeleting] = useState(false)
  const [createOpen, setCreateOpen] = useState(false)
  const [creating, setCreating] = useState(false)
  const [basePrices, setBasePrices] = useState<Awaited<ReturnType<typeof getManagerBasePrices>>>([])
  const [tablePage, setTablePage] = useState(1)
  const [cardPage, setCardPage] = useState(1)
  const tablePageSize = 6
  const [viewportWidth, setViewportWidth] = useState(1440)
  const isMobile = viewportWidth <= 740
  const [searchOpen, setSearchOpen] = useState(false)
  const searchContainerRef = useRef<HTMLDivElement>(null)

  const loadPackages = async (requestedPage = tablePage) => {
    try {
      const result = await getManagerPackages(requestedPage)
      setPackages((current) => {
        const inactive = current.filter((item) => !item.is_active)
        return [...result.data, ...inactive.filter((item) => !result.data.some((activeItem) => activeItem.id === item.id))]
      })
      setTablePage(result.meta.current_page)
    } catch {
      setErrorTitle('Paket belum dapat dimuat')
      setError('Paket tidak dapat dimuat sekarang. Periksa koneksi, lalu coba lagi.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    let mounted = true
    void getCurrentUser().then((user) => { if (mounted) setManager(user) }).catch(() => undefined)
    return () => { mounted = false }
  }, [])

  useEffect(() => {
    const onResize = () => {
      setViewportWidth(window.innerWidth)
    }
    onResize()
    window.addEventListener('resize', onResize)
    return () => window.removeEventListener('resize', onResize)
  }, [])

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

  useEffect(() => {
    let mounted = true
    getManagerPackages(1).then((result) => {
      if (!mounted) return
      setPackages(result.data)
      setTablePage(result.meta.current_page)
    }).catch(() => {
      if (mounted) {
        setErrorTitle('Paket belum dapat dimuat')
        setError('Paket tidak dapat dimuat sekarang. Periksa koneksi, lalu coba lagi.')
      }
    }).finally(() => {
      if (mounted) setLoading(false)
    })
    getManagerPackageSummary().then((result) => {
      if (mounted) setSummary(result)
    }).catch(() => {
      if (mounted) setSummaryError(true)
    })
    getManagerBasePrices().then((result) => {
      if (mounted) setBasePrices(result)
    }).catch(() => undefined)
    getManagerPackages(1, false).then((result) => {
      if (!mounted) return
      setPackages((current) => [...current, ...result.data.filter((item) => !current.some((loaded) => loaded.id === item.id))])
    }).catch(() => undefined)
    return () => { mounted = false }
  }, [])

  const filteredPackages = useMemo(() => packages.filter((item) => {
    const matchesQuery = `${item.name} ${item.description ?? ''}`.toLocaleLowerCase('id-ID').includes(query.trim().toLocaleLowerCase('id-ID'))
    const matchesStage = stageFilter === 'all' || item.stages?.includes(stageFilter) === true
    return matchesQuery && matchesStage
  }).sort((a, b) => Number(b.is_active) - Number(a.is_active)), [packages, query, stageFilter])

  const cardsPerPage = isMobile ? 5 : viewportWidth <= 1200 ? 6 : 8
  const cardPageCount = Math.max(1, Math.ceil(filteredPackages.length / cardsPerPage))
  const currentCardPage = Math.min(cardPage, cardPageCount)
  const visibleCardPackages = filteredPackages.slice((currentCardPage - 1) * cardsPerPage, currentCardPage * cardsPerPage)
  const tablePageCount = Math.max(1, Math.ceil(filteredPackages.length / tablePageSize))
  const currentTablePage = Math.min(tablePage, tablePageCount)
  const visibleTablePackages = filteredPackages.slice((currentTablePage - 1) * tablePageSize, currentTablePage * tablePageSize)

  const counts = useMemo(() => ({
    all: summary.total,
    ...summary.by_stage,
  }), [summary])

  const removePackage = async () => {
    if (!deleteTarget) return
    setDeleting(true)
    try {
      await deleteManagerPackage(deleteTarget.id)
      setDeleteTarget(null)
      await loadPackages(tablePage)
    } catch {
      setError('Paket tidak dapat dihapus sekarang. Periksa koneksi, lalu coba lagi.')
    } finally {
      setDeleting(false)
    }
  }

  const submitNewPackage = async (input: Parameters<typeof createManagerPackage>[0]): Promise<ManagerPackage> => {
    setCreating(true)
    setError('')
    try {
      const created = await createManagerPackage(input)
      setPackages((current) => [...current, created].sort((a, b) => Number(b.is_active) - Number(a.is_active)))
      setCreateOpen(false)
      setCardPage(1)
      void getManagerPackageSummary().then(setSummary).catch(() => setSummaryError(true))
      return created
    } catch (cause) {
      const errorCause = cause as ApiError
      setErrorTitle(`Paket tidak dapat dibuat${errorCause.status ? ` (HTTP ${errorCause.status})` : ''}`)
      const validationMessages = errorCause.errors ? Object.values(errorCause.errors).flat().join(' ') : ''
      setError(validationMessages || (cause instanceof Error ? cause.message : 'Paket tidak dapat dibuat sekarang.'))
      throw cause
    } finally {
      setCreating(false)
    }
  }

  const togglePackageActive = async (item: ManagerPackage) => {
    try {
      const updated = await updateManagerPackageActive(item, !item.is_active)
      setActivePackageMenu(null)
      setPackages((current) => {
        const reordered = current.map((entry) => entry.id === item.id ? updated : entry)
        return [...reordered.filter((entry) => entry.is_active), ...reordered.filter((entry) => !entry.is_active)]
      })
      if (!updated.is_active) setCardPage(1)
      void getManagerPackageSummary().then(setSummary).catch(() => setSummaryError(true))
    } catch (cause: unknown) {
      setActivePackageMenu(null)
      const message = cause instanceof Error ? cause.message : 'Kesalahan tak diketahui.'
      const status = (cause as ApiError).status
      setErrorTitle(`Paket tidak dapat ${item.is_active ? 'dinonaktifkan' : 'diaktifkan'}`)
      setError(`Paket tidak dapat ${item.is_active ? 'dinonaktifkan' : 'diaktifkan'}${status ? ` (HTTP ${status})` : ''}: ${message}`)
    }
  }

  useEffect(() => {
    const onResize = () => {
      setViewportWidth(window.innerWidth)
    }
    onResize()
    window.addEventListener('resize', onResize)
    return () => window.removeEventListener('resize', onResize)
  }, [])

  const statCards: Array<{ label: string; value: number; icon: 'package' | 'microphone' | 'scissors' | 'sliders' | 'activity'; tone: PackageStatTone }> = [
    { label: 'Semua Paket', value: counts.all, icon: 'package' as const, tone: 'primary' },
    { label: 'Recording', value: counts.recording, icon: 'microphone' as const, tone: 'blue' },
    { label: 'Editing', value: counts.editing, icon: 'scissors' as const, tone: 'success' },
    { label: 'Mixing', value: counts.mixing, icon: 'sliders' as const, tone: 'warning' },
    { label: 'Mastering', value: counts.mastering, icon: 'activity' as const, tone: 'mastering' },
  ]

  const selectStageFilter = (filter: StageFilter) => {
    setStageFilter(filter)
    setCardPage(1)
    setTablePage(1)
  }

  const stageFilters: Array<{ key: StageFilter; label: string; count: number }> = [
    { key: 'all', label: 'Semua Paket', count: counts.all },
    ...stageInfo.map((stage) => ({ key: stage.key, label: stage.label, count: counts[stage.key] })),
  ]

  const goTo = (path: string) => navigate(path)

  useEffect(() => {
    if (isMobile && view !== 'Kartu') setView('Kartu')
  }, [isMobile, view])

  if (loading && packages.length === 0) return <LoadingScreen label="Memuat manajemen paket" />

  return (
    <div className="manager-shell">
      <ManagerSideNav managerName={manager?.name ?? 'Manager'} />
      <main className="manager-main manager-packages-page">
        <UpperNavBar path="/manajemen-paket" title="Manajemen Paket" tools={<>
          <Button aria-label="Project Baru" className="manager-create-button" leadingIcon="plus" onClick={() => goTo('/project/buat')}>Project Baru</Button>
          <div className={`manager-search-wrap${searchOpen ? ' is-open' : ''}`} ref={searchContainerRef}>
            <Input aria-label="Cari project, klien, operator" className="manager-search" leadingIcon="search" placeholder="Cari project, klien, operator..." />
            <button aria-expanded={searchOpen} aria-label={searchOpen ? 'Tutup pencarian' : 'Buka pencarian'} className="manager-topbar__icon manager-search-toggle" onClick={() => setSearchOpen((open) => !open)} type="button">
              <Icon name="search" size={20} />
            </button>
          </div>
          <button aria-label="Pengaturan" className="manager-topbar__icon manager-topbar__settings" type="button"><Icon name="settings" size={20} /></button>
          <button aria-label="Notifikasi" className="manager-topbar__icon" type="button"><Icon name="bell" size={20} /></button>
        </>} />

        <div className="manager-packages-content">
          <section aria-label="Ringkasan paket" className="manager-package-stats">
            {statCards.map((item, index) => {
              const filter: StageFilter = index === 0 ? 'all' : stageInfo[index - 1].key
              return <PackageStatCard {...item} key={item.label} onClick={() => selectStageFilter(filter)} />
            })}
          </section>

          <section className={`manager-package-main manager-package-main--${view === 'Kartu' ? 'cards' : 'table'}`} aria-label="Paket studio">
            <div className="manager-package-main__toolbar">
              <Input aria-label="Cari paket" className="manager-package-search" leadingIcon="search" onChange={(event) => setQuery(event.target.value)} placeholder="Cari paket..." value={query} />
              <div className="manager-package-main__actions">
                {!isMobile && <div aria-label="Mode lihat" className="manager-package-view">
                  <span>Mode Lihat:</span>
                  <div aria-label="Mode lihat" className="manager-package-view__toggle" role="group">
                    <button aria-label="Kartu" aria-pressed={view === 'Kartu'} className={`manager-package-view__option${view === 'Kartu' ? ' is-active' : ''}`} onClick={() => setView('Kartu')} type="button"><span aria-hidden="true" className="manager-package-view__icon manager-package-view__icon--cards" />{view === 'Kartu' && <span>Kartu</span>}</button>
                    <button aria-label="Tabel" aria-pressed={view === 'Tabel'} className={`manager-package-view__option${view === 'Tabel' ? ' is-active' : ''}`} disabled={isMobile} onClick={() => setView('Tabel')} type="button"><span aria-hidden="true" className="manager-package-view__icon manager-package-view__icon--table" />{view === 'Tabel' && <span>Tabel</span>}</button>
                  </div>
                </div>}
              </div>
              <Button className="manager-package-create" leadingIcon="plus" onClick={() => setCreateOpen(true)}>Buat Paket</Button>
            </div>
            <div aria-label="Filter tahap paket" className="manager-package-filters">
              {stageFilters.map((filter) => <PackageStageFilter active={stageFilter === filter.key} count={filter.count} key={filter.key} label={filter.label} onClick={() => selectStageFilter(filter.key)} />)}
            </div>

            {error && <div className="manager-package-error-backdrop" onClick={() => setError('')} role="presentation">
              <section aria-labelledby="manager-package-error-title" aria-modal="true" className="manager-package-dialog" onClick={(event) => event.stopPropagation()} role="alertdialog">
                <h2 id="manager-package-error-title">{errorTitle}</h2>
                <p>{error}</p>
                <div><Button onClick={() => setError('')} variant="outlined">Tutup</Button><Button onClick={() => { setError(''); void loadPackages(tablePage) }}>Coba Lagi</Button></div>
              </section>
            </div>}
            {summaryError && <div className="manager-package-error-backdrop" onClick={() => setSummaryError(false)} role="presentation">
              <section aria-labelledby="manager-package-summary-error-title" aria-modal="true" className="manager-package-dialog" onClick={(event) => event.stopPropagation()} role="alertdialog">
                <h2 id="manager-package-summary-error-title">Ringkasan paket belum tersedia</h2>
                <p>Daftar paket tetap dapat digunakan, tetapi ringkasan jumlah belum dapat dimuat.</p>
                <div><Button onClick={() => setSummaryError(false)} variant="outlined">Tutup</Button><Button onClick={() => { setSummaryError(false); void getManagerPackageSummary().then(setSummary).catch(() => setSummaryError(true)) }}>Coba Lagi</Button></div>
              </section>
            </div>}
            {loading ? <p aria-live="polite" className="manager-packages-state">Memuat paket…</p> : filteredPackages.length === 0 ? <p className="manager-packages-state">No data</p> : view === 'Kartu' ? (
              <div className="manager-package-cards-view">
                <PackageCardGrid columns={isMobile ? 1 : cardsPerPage === 6 ? 3 : 4} items={visibleCardPackages} onMenu={(item, anchor) => setActivePackageMenu({ item, anchor })} onSelect={setDetailPackage} />
                <div className="manager-package-pagination manager-package-pagination--cards">
                  <button aria-label="Halaman sebelumnya" className="manager-package-page-chevron" disabled={currentCardPage <= 1} onClick={() => setCardPage(Math.max(1, currentCardPage - 1))} type="button"><Icon name="chevron-left" size={18} /></button>
                  <span>{Array.from({ length: cardPageCount }, (_, index) => <button aria-current={currentCardPage === index + 1 ? 'page' : undefined} className={`manager-package-page-number${currentCardPage === index + 1 ? ' is-current' : ''}`} key={index} onClick={() => setCardPage(index + 1)} type="button">{number.format(index + 1)}</button>)}</span>
                  <button aria-label="Halaman berikutnya" className="manager-package-page-chevron" disabled={currentCardPage >= cardPageCount} onClick={() => setCardPage(Math.min(cardPageCount, currentCardPage + 1))} type="button"><Icon name="chevron-right" size={18} /></button>
                </div>
              </div>
            ) : <div className="manager-package-table-page"><div className="manager-package-table-region"><ManagerPackageTable items={visibleTablePackages} onMenu={(item, anchor) => setActivePackageMenu({ item, anchor })} onSelect={setDetailPackage} /></div>
              <PackagePagination ariaLabel="Navigasi halaman paket tabel" currentPage={currentTablePage} onPageChange={setTablePage} pageCount={tablePageCount} className="manager-package-pagination--table" />
            </div>}
          </section>
        </div>

        {activePackageMenu && <PackageActionPopover
          item={activePackageMenu.item}
          anchor={activePackageMenu.anchor}
          onClose={() => setActivePackageMenu(null)}
          onDelete={() => { setActivePackageMenu(null); setDeleteTarget(activePackageMenu.item) }}
          onEdit={() => { setActivePackageMenu(null); goTo(`/manajemen-paket/${activePackageMenu.item.id}/edit`) }}
          onToggleActive={() => { void togglePackageActive(activePackageMenu.item) }}
        />}
        {createOpen && <PackageCreateModal
          basePrices={basePrices}
          error={errorTitle.startsWith('Paket tidak dapat dibuat') ? error : ''}
          onCalculatePrice={calculateManagerPackagePrice}
          onClose={() => { setCreateOpen(false); setError('') }}
          onSubmit={submitNewPackage}
          submitting={creating}
        />}
        {detailPackage && <PackageDetailDialog item={detailPackage} onClose={() => setDetailPackage(null)} />}
        {deleteTarget && <div className="manager-package-dialog-backdrop" onClick={() => !deleting && setDeleteTarget(null)} role="presentation">
          <section aria-labelledby="manager-delete-package-title" aria-modal="true" className="manager-package-dialog" onClick={(event) => event.stopPropagation()} role="dialog">
            <h2 id="manager-delete-package-title">Hapus Paket</h2>
            <p>Yakin ingin menghapus paket “{deleteTarget.name}”?</p>
            <div><Button disabled={deleting} onClick={() => setDeleteTarget(null)} variant="outlined">Batal</Button><Button disabled={deleting} onClick={() => void removePackage()}>{deleting ? 'Menghapus…' : 'Hapus Paket'}</Button></div>
          </section>
        </div>}
      </main>
    </div>
  )
}

function PackageCardGrid({ items, columns, onMenu, onSelect }: { items: ManagerPackage[]; columns: number; onMenu: (item: ManagerPackage, anchor: DOMRect) => void; onSelect: (item: ManagerPackage) => void }) {
  return <div aria-label="Daftar paket" className="manager-package-grid" style={{ gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` }}>
    {items.map((item) => <PackageCard item={item} key={item.id} onMenu={(anchor) => onMenu(item, anchor)} onSelect={() => onSelect(item)} />)}
  </div>
}

function PackagePagination({ ariaLabel, currentPage, pageCount, onPageChange, className = '' }: { ariaLabel: string; currentPage: number; pageCount: number; onPageChange: (page: number) => void; className?: string }) {
  const pages = Array.from({ length: pageCount }, (_, index) => index + 1)

  return (
    <nav aria-label={ariaLabel} className={`manager-package-pagination ${className}`}>
      <button aria-label="Halaman sebelumnya" className="manager-package-page-chevron" disabled={currentPage <= 1} onClick={() => onPageChange(Math.max(1, currentPage - 1))} type="button"><Icon name="chevron-left" size={18} /></button>
      <span>{pages.map((page) => <button aria-current={currentPage === page ? 'page' : undefined} className={`manager-package-page-number${currentPage === page ? ' is-current' : ''}`} key={page} onClick={() => onPageChange(page)} type="button">{number.format(page)}</button>)}</span>
      <button aria-label="Halaman berikutnya" className="manager-package-page-chevron" disabled={currentPage >= pageCount} onClick={() => onPageChange(Math.min(pageCount, currentPage + 1))} type="button"><Icon name="chevron-right" size={18} /></button>
    </nav>
  )
}

function ManagerPackageTable({ items, onMenu, onSelect }: { items: ManagerPackage[]; onMenu: (item: ManagerPackage, anchor: DOMRect) => void; onSelect: (item: ManagerPackage) => void }) {
  return <div className="manager-package-table-wrap"><table className="manager-package-table">
    <thead><tr><th>Nama Paket</th><th>Status</th><th>Tahap</th><th>Rekaman</th><th>Lagu</th><th>Harga</th><th><span className="manager-sr-only">Aksi</span></th></tr></thead>
    <tbody>{items.map((item) => <tr className={`manager-package-table__row${item.is_active ? '' : ' is-inactive'}`} key={item.id} onClick={() => onSelect(item)} tabIndex={0} onKeyDown={(event) => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); onSelect(item) } }}>
      <td><strong>{item.name}</strong><small>{item.description}</small></td>
      <td><Badge className="manager-package-table__status" tone={item.is_active ? 'success' : 'neutral'}>{item.is_active ? 'Aktif' : 'Nonaktif'}</Badge></td>
      <td><div aria-label="Tahap paket" className="manager-package-table__stages">{stageInfo.filter((stage) => item.stages?.includes(stage.key)).map((stage) => <Badge icon={stage.icon} key={stage.key} tone={stage.tone}>{stage.label}</Badge>)}</div></td>
      <td>{number.format(item.recording_hours)} jam</td><td>{number.format(item.track_count)} lagu</td>
      <td>{currency.format(item.price).replace(/\u00a0/g, ' ')}</td>
      <td><button aria-label={`Opsi paket ${item.name}`} className="manager-package-card__menu" onClick={(event) => { event.stopPropagation(); onMenu(item, event.currentTarget.getBoundingClientRect()) }} type="button"><Icon name="ellipsis-vertical" size={20} /></button></td>
    </tr>)}</tbody>
  </table></div>
}

function PackageActionPopover({ item, anchor, onClose, onEdit, onDelete, onToggleActive }: { item: ManagerPackage; anchor: DOMRect; onClose: () => void; onEdit: () => void; onDelete: () => void; onToggleActive: () => void }) {
  const popoverRef = useRef<HTMLElement>(null)
  const [position, setPosition] = useState({ left: Math.max(8, Math.min(window.innerWidth - 232, anchor.right - 216)), top: Math.max(8, Math.min(window.innerHeight - 176, anchor.bottom + 4)) })

  useEffect(() => {
    const popover = popoverRef.current
    if (!popover) return
    setPosition({
      left: Math.max(8, Math.min(window.innerWidth - popover.offsetWidth - 8, anchor.right - popover.offsetWidth)),
      top: Math.max(8, Math.min(window.innerHeight - popover.offsetHeight - 8, anchor.bottom + 4)),
    })
  }, [anchor])

  return <>
    <button aria-label="Tutup opsi paket" className="manager-package-popover-dismiss" onClick={onClose} style={{ position: 'fixed', inset: 0 }} type="button" />
    <section aria-label={`Opsi paket ${item.name}`} className="manager-package-popover" onClick={(event) => event.stopPropagation()} ref={popoverRef} style={{ position: 'fixed', left: position.left, top: position.top }}>
      <header><h2>Opsi Paket</h2><button aria-label="Tutup opsi paket" onClick={onClose} type="button"><Icon name="close" size={20} /></button></header>
      <div className="manager-package-popover__separator" />
      <button className="manager-package-popover__action" onClick={onEdit} type="button"><Icon name="edit" size={18} />Edit Paket</button>
      <button className="manager-package-popover__action" onClick={onToggleActive} type="button"><Icon name="power" size={18} />{item.is_active ? 'Nonaktifkan Paket' : 'Aktifkan Paket'}</button>
      <button className="manager-package-popover__action is-danger" onClick={onDelete} type="button"><Icon name="close" size={18} />Hapus Paket</button>
    </section>
  </>
}

function PackageDetailDialog({ item, onClose }: { item: ManagerPackage; onClose: () => void }) {
  const includedStages = stageInfo.filter((stage) => item.stages?.includes(stage.key))
  return createPortal(<div className="manager-package-dialog-backdrop" onClick={onClose} role="presentation">
    <section aria-labelledby="manager-package-detail-title" aria-modal="true" className="manager-package-dialog manager-package-detail" onClick={(event) => event.stopPropagation()} role="dialog">
      <header className="manager-package-detail__header"><div><p>Detail Paket</p><h2 id="manager-package-detail-title">{item.name}</h2></div><button aria-label="Tutup detail paket" onClick={onClose} type="button"><Icon name="close" size={20} /></button></header>
      <p className="manager-package-detail__description">{item.description || 'Deskripsi paket belum tersedia.'}</p>
      <div aria-label="Tahap paket" className="manager-package-detail__stages">{includedStages.length ? includedStages.map((stage) => <Badge icon={stage.icon} key={stage.key} tone={stage.tone}>{stage.label}</Badge>) : <span>Tidak ada tahap</span>}</div>
      <dl className="manager-package-detail__facts">
        <div><dt>Jam rekaman</dt><dd>{number.format(item.recording_hours)} jam</dd></div>
        <div><dt>Jumlah lagu</dt><dd>{number.format(item.track_count)} lagu</dd></div>
        <div><dt>Status</dt><dd>{item.is_active ? 'Aktif' : 'Nonaktif'}</dd></div>
        <div><dt>Tanggal dibuat</dt><dd>{item.created_at ? new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(item.created_at)) : '—'}</dd></div>
      </dl>
      <div className="manager-package-detail__actions"><div className="manager-package-detail__price"><span>Harga</span><strong>{currency.format(item.price).replace(/\u00a0/g, ' ')}</strong></div><button className="manager-package-detail__close" onClick={onClose} type="button">Tutup</button></div>
    </section>
  </div>, document.body)
}