import { useEffect, useRef, useState, type FormEvent, type ReactNode } from 'react'
import { Link, Navigate, NavLink, useLocation, useNavigate } from 'react-router-dom'
import { Badge, Button, Input, LoadingScreen, PackageCard, Switch } from '../../components/ui'
import { getCurrentUser, getApiErrorMessage, logout, type AuthUser } from '../../lib/auth-api'
import ProfileDialog from '../auth/ProfileDialog'
import { assets, stages } from './assets'
import { ClientWorkspace } from './ClientWorkspace'
import {
  estimatePrice, getPackages, getPrices, getQuotation, getQuotations, offerDate, respondToQuotation, rupiah, submitQuotation,
  type BasePrice, type ClientPackage, type Offer, type Quotation, type Specifications,
} from './client-api'
import './client-portal.css'

const initialSpec: Specifications = {
  track_count: 1, recording_hours: 3,
  include_recording: true, include_editing: true, include_mixing: true, include_mastering: true,
}

function Notice({ children, success = false }: { children: ReactNode; success?: boolean }) {
  return <p className={`client-notice${success ? ' is-success' : ''}`} role={success ? 'status' : 'alert'}>{children}</p>
}

function Modal({ title, children, onClose }: { title: string; children: ReactNode; onClose: () => void }) {
  const ref = useRef<HTMLDialogElement>(null)
  useEffect(() => {
    const dialog = ref.current
    dialog?.showModal()
    return () => dialog?.close()
  }, [])
  return <dialog ref={ref} className="client-modal" aria-labelledby="client-modal-title" onCancel={(event) => { event.preventDefault(); onClose() }}>
    <header><h2 id="client-modal-title">{title}</h2><button className="client-close" type="button" aria-label="Tutup" onClick={onClose}>×</button></header>
    {children}
  </dialog>
}

function Stepper({ label, value, min = 1, max = 255, disabled = false, onChange }: {
  label: string; value: number; min?: number; max?: number; disabled?: boolean; onChange: (value: number) => void
}) {
  return <label className="client-stepper-label">{label}
    <span className="client-stepper">
      <button type="button" aria-label={`Kurangi ${label}`} disabled={disabled || value <= min} onClick={() => onChange(value - 1)}><img src={assets.minus} alt="" /></button>
      <input aria-label={label} type="number" value={value} min={min} max={max} step={1} disabled={disabled} onChange={(event) => onChange(Math.max(min, Math.min(max, Math.trunc(Number(event.target.value) || min))))} />
      <button type="button" aria-label={`Tambah ${label}`} disabled={disabled || value >= max} onClick={() => onChange(value + 1)}><img src={assets.add} alt="" /></button>
    </span>
  </label>
}

function QuoteHistory({ quote, loading, onSelect, onReload, error, quotations, page, lastPage, onPage }: {
  quote: Quotation | null; loading: boolean; onSelect: (id: number) => void; onReload: () => void; error: string
  quotations: Quotation[]; page: number; lastPage: number; onPage: (page: number) => void
}) {
  const [ascending, setAscending] = useState(false)
  const [showAll, setShowAll] = useState(false)
  const [collapsed, setCollapsed] = useState(false)
  const allOffers = [...(quote?.offers ?? (quote?.latest_offer ? [quote.latest_offer] : []))].sort((a, b) => ascending ? a.id - b.id : b.id - a.id)
  const offers = showAll ? allOffers : allOffers.slice(0, 4)
  const latestId = Math.max(0, ...allOffers.map((offer) => offer.id))
  const offerLabel = (offer: Offer) => offer.side === 'client' ? 'Client menawar harga' : 'Studio menawarkan harga'
  return <aside className="client-history" id="riwayat-penawaran" aria-labelledby="history-heading">
    <header><h3 id="history-heading">Riwayat Revisi Penawaran</h3><div>
      <button type="button" aria-label={ascending ? 'Urutkan revisi terbaru' : 'Urutkan revisi terlama'} aria-pressed={ascending} onClick={() => setAscending(!ascending)}><img src={assets.filter} alt="" /></button>
      <button type="button" aria-label={collapsed ? 'Buka riwayat revisi' : 'Tutup riwayat revisi'} aria-expanded={!collapsed} onClick={() => setCollapsed(!collapsed)}>{collapsed ? '+' : '−'}</button>
    </div></header>
    {!collapsed && <div className="client-history-body">
      {quotations.length > 0 && <label className="client-quote-select">Penawaran<select aria-label="Pilih penawaran" value={quote?.id ?? ''} onChange={(event) => onSelect(Number(event.target.value))}>
        {!quote && <option value="">Pilih penawaran</option>}
        {quotations.map((item) => <option key={item.id} value={item.id}>{item.name} · {item.status_label}</option>)}
      </select></label>}
      {lastPage > 1 && <div className="client-pagination"><button type="button" disabled={page === 1 || loading} onClick={() => onPage(page - 1)}>Sebelumnya</button><span>{page} / {lastPage}</span><button type="button" disabled={page === lastPage || loading} onClick={() => onPage(page + 1)}>Berikutnya</button></div>}
      {loading && <p role="status">Memuat riwayat…</p>}
      {error && <><Notice>{error}</Notice><Button variant="text" onClick={onReload}>Coba lagi</Button></>}
      {!loading && !error && offers.length === 0 && <p className="client-empty">Belum ada penawaran. Kirim penawaran pertama untuk memulai diskusi dengan studio.</p>}
      {!loading && !error && <ol className="client-history-list">{offers.map((offer) => <li className={offer.id === latestId ? 'is-latest' : ''} key={offer.id}>
        <span className="client-history-dot" /><div><div className="client-history-date"><time dateTime={offer.created_at}>{offerDate(offer.created_at)}</time>{offer.id === latestId && <span>Terbaru</span>}</div><p>{offerLabel(offer)}</p>{offer.note && <p className="client-history-note">{offer.note}</p>}</div><strong>{rupiah(offer.price)}</strong>
      </li>)}</ol>}
      {allOffers.length > 4 && <Button className="client-history-more" variant="outlined" onClick={() => setShowAll(!showAll)}>{showAll ? 'Tampilkan Lebih Sedikit' : 'Lihat Semua Revisi'}</Button>}
    </div>}
  </aside>
}

function ClientPackages({ user, onUserChange }: { user: AuthUser; onUserChange: (user: AuthUser) => void }) {
  const navigate = useNavigate()
  const location = useLocation()
  const isPackagePage = location.pathname === '/client/packages'
  const view = location.pathname === '/client/booking' ? 'booking' : location.pathname === '/client/penawaran' ? 'penawaran' : location.pathname === '/client/project' ? 'project' : location.pathname === '/client/transaksi' ? 'transaksi' : 'dashboard'
  const [packages, setPackages] = useState<ClientPackage[]>([])
  const [prices, setPrices] = useState<BasePrice[]>([])
  const [quotations, setQuotations] = useState<Quotation[]>([])
  const [quote, setQuote] = useState<Quotation | null>(null)
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [historyError, setHistoryError] = useState('')
  const [historyLoading, setHistoryLoading] = useState(false)
  const [version, setVersion] = useState(0)
  const [spec, setSpec] = useState<Specifications>(initialSpec)
  const [manual, setManual] = useState(true)
  const [manualPrice, setManualPrice] = useState('1250000')
  const [name, setName] = useState('')
  const [notice, setNotice] = useState('')
  const [actionError, setActionError] = useState('')
  const [busy, setBusy] = useState(false)
  const [confirming, setConfirming] = useState(false)
  const [responseAction, setResponseAction] = useState<'accept' | 'decline' | 'counter' | null>(null)
  const [counterPrice, setCounterPrice] = useState('')
  const [accountOpen, setAccountOpen] = useState(false)
  const [editingProfile, setEditingProfile] = useState(false)
  const [updatesOpen, setUpdatesOpen] = useState(false)
  const [search, setSearch] = useState('')
  const [showAllPackages, setShowAllPackages] = useState(false)
  const [selectedPackage, setSelectedPackage] = useState<ClientPackage | null>(null)
  const historyRequest = useRef(0)
  const composer = useRef<HTMLElement>(null)
  const account = useRef<HTMLDivElement>(null)
  const updates = useRef<HTMLDivElement>(null)

  useEffect(() => {
    let cancelled = false
    if (!isPackagePage) return
    void Promise.all([getPackages(), getPrices(), getQuotations()]).then(async ([catalog, rates, history]) => {
      const details = history.data[0] ? await getQuotation(history.data[0].id) : null
      if (cancelled) return
      setPackages(catalog.data)
      setPrices(rates.data)
      setQuotations(history.data)
      setLastPage(history.meta?.last_page ?? 1)
      setQuote(details?.data ?? null)
      setLoading(false)
    }).catch((failure: unknown) => {
      if (!cancelled) { setError(getApiErrorMessage(failure)); setLoading(false) }
    })
    return () => { cancelled = true }
  }, [version, isPackagePage])

  useEffect(() => {
    if (!isPackagePage || loading || error) return
    const params = new URLSearchParams(location.search)
    const selected = Number(params.get('penawaran'))
    if (selected > 0) void selectQuote(selected)
    if (params.has('buat')) scrollToComposer()
    if (location.hash === '#riwayat-penawaran') document.getElementById('riwayat-penawaran')?.scrollIntoView({ block: 'center' })
  }, [isPackagePage, loading, error, location.search, location.hash])

  useEffect(() => {
    function dismiss(event: PointerEvent) {
      if (!account.current?.contains(event.target as Node)) setAccountOpen(false)
      if (!updates.current?.contains(event.target as Node)) setUpdatesOpen(false)
    }
    function close(event: KeyboardEvent) { if (event.key === 'Escape') { setAccountOpen(false); setUpdatesOpen(false) } }
    document.addEventListener('pointerdown', dismiss)
    document.addEventListener('keydown', close)
    return () => { document.removeEventListener('pointerdown', dismiss); document.removeEventListener('keydown', close) }
  }, [])

  async function selectQuote(id: number) {
    const requestId = ++historyRequest.current
    setHistoryLoading(true); setHistoryError('')
    try { const result = await getQuotation(id); if (requestId === historyRequest.current) setQuote(result.data) }
    catch (failure) { if (requestId === historyRequest.current) setHistoryError(getApiErrorMessage(failure)) }
    finally { if (requestId === historyRequest.current) setHistoryLoading(false) }
  }
  async function changePage(next: number) {
    const requestId = ++historyRequest.current
    setHistoryLoading(true); setHistoryError('')
    try {
      const result = await getQuotations(next)
      const detail = result.data[0] ? await getQuotation(result.data[0].id) : null
      if (requestId !== historyRequest.current) return
      setQuotations(result.data); setPage(next); setLastPage(result.meta?.last_page ?? 1); setQuote(detail?.data ?? null)
    } catch (failure) { if (requestId === historyRequest.current) setHistoryError(getApiErrorMessage(failure)) }
    finally { if (requestId === historyRequest.current) setHistoryLoading(false) }
  }
  function retry() { setLoading(true); setError(''); setVersion((value) => value + 1) }
  function scrollToComposer() { composer.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }) }
  function resetDraft() { setSpec(initialSpec); setName(''); setManual(true); setManualPrice('1250000'); setNotice(''); setActionError('') }
  function choosePackage(item: ClientPackage) {
    setSpec({ track_count: item.track_count, recording_hours: item.recording_hours, include_recording: item.include_recording, include_editing: item.include_editing, include_mixing: item.include_mixing, include_mastering: item.include_mastering })
    setManual(true); setManualPrice(String(item.price)); setName(item.name)
    setNotice(`Spesifikasi ${item.name} sudah dipilih. Periksa ringkasan sebelum mengirim penawaran.`)
    setActionError(''); scrollToComposer()
  }
  const count = stages.filter((stage) => spec[`include_${stage.key}`]).length
  const estimated = estimatePrice(spec, prices)
  const total = manual ? Number(manualPrice) : estimated
  const ratesReady = stages.every((stage) => prices.some((rate) => rate.stage === stage.key))
  const valid = count > 0 && Number.isSafeInteger(total) && total > 0 && ratesReady
  const filteredPackages = packages.filter((item) => `${item.name} ${item.description ?? ''}`.toLocaleLowerCase('id').includes(search.toLocaleLowerCase('id')))
  const visiblePackages = showAllPackages || search ? filteredPackages : filteredPackages.slice(0, 3)
  const pending = quotations.filter((item) => item.status === 'awaiting_client')
  const initials = user.name.split(' ').slice(0, 2).map((part) => part[0]).join('').toUpperCase()

  async function sendQuote(event: FormEvent) {
    event.preventDefault()
    if (!valid || busy) return
    setBusy(true); setActionError(''); setNotice('')
    try {
      const result = await submitQuotation({ ...spec, name: name.trim(), offer_price: total })
      historyRequest.current += 1
      setHistoryLoading(false)
      setQuotations((items) => [result.data, ...items.filter((item) => item.id !== result.data.id)])
      setQuote(result.data); setConfirming(false); setNotice(`Penawaran “${result.data.name}” berhasil dikirim ke studio.`)
    } catch (failure) { setActionError(getApiErrorMessage(failure)) }
    finally { setBusy(false) }
  }
  async function sendResponse(event: FormEvent) {
    event.preventDefault()
    const latest = [...(quote?.offers ?? [])].sort((a, b) => b.id - a.id)[0] ?? quote?.latest_offer
    if (!quote || !latest || !responseAction || busy) return
    setBusy(true); setActionError('')
    try {
      const result = await respondToQuotation(quote.id, responseAction, latest.id, responseAction === 'counter' ? Number(counterPrice) : undefined)
      const updated = await getQuotation(result.data.id)
      setQuote(updated.data); setQuotations((items) => items.map((item) => item.id === updated.data.id ? updated.data : item))
      setResponseAction(null); setNotice('Tanggapan penawaran berhasil dikirim.')
    } catch (failure) { setActionError(getApiErrorMessage(failure)) }
    finally { setBusy(false) }
  }
  async function leave() {
    setBusy(true)
    try { await logout(); navigate('/login', { replace: true }) }
    catch (failure) { setActionError(getApiErrorMessage(failure)) }
    finally { setBusy(false) }
  }

  return <div className="client-portal">
    <a className="client-skip" href="#client-content">Lewati navigasi</a>
    <header className="client-navbar" data-figma-node="656:5546">
      <div className="client-navbar-left"><Link className="client-brand" to="/client"><span className="client-brand-mark"><img src={assets.logo} alt="" /></span><span>Tungku Studio</span></Link>
        <nav aria-label="Navigasi klien">
          <NavLink to="/client" end>Dashboard</NavLink>
          <NavLink to="/client/booking">Jadwal Booking</NavLink>
          <NavLink to="/client/penawaran">Penawaran</NavLink>
          <NavLink to="/client/project">Project</NavLink>
          <NavLink to="/client/transaksi">Transaksi</NavLink>
        </nav>
      </div>
      <div className="client-navbar-actions"><Button onClick={() => isPackagePage ? scrollToComposer() : navigate('/client/packages?buat=1')}>Buat Proyek</Button>
        <label className="client-search"><img src={assets.search} alt="" /><input aria-label="Cari paket" placeholder="Cari paket..." type="search" value={search} onChange={(event) => { setSearch(event.target.value); if (!isPackagePage) navigate('/client/packages') }} /></label>
        <div className="client-popover-anchor" ref={updates}><button className="client-icon-button" type="button" aria-label="Pembaruan penawaran" aria-expanded={updatesOpen} onClick={() => setUpdatesOpen(!updatesOpen)}><img src={assets.notifications} alt="" />{pending.length > 0 && <span className="client-notification-dot" />}</button>
          {updatesOpen && <div className="client-popover"><h3>Pembaruan penawaran</h3>{pending.length ? pending.map((item) => <button type="button" key={item.id} onClick={() => { setUpdatesOpen(false); navigate(`/client/packages?penawaran=${item.id}#riwayat-penawaran`) }}>{item.name}<small>Studio menunggu tanggapanmu</small></button>) : <p>Belum ada penawaran yang perlu ditanggapi.</p>}</div>}
        </div>
        <div className="client-popover-anchor" ref={account}><button className="client-avatar" type="button" aria-label={`Akun ${user.name}`} aria-expanded={accountOpen} onClick={() => setAccountOpen(!accountOpen)}>{initials}</button>
          {accountOpen && <div className="client-popover"><h3>{user.name}</h3><p>{user.email}</p><Button variant="text" onClick={() => { setAccountOpen(false); setEditingProfile(true) }}>Edit Profil</Button><Button variant="text" disabled={busy} onClick={() => void leave()}>Logout</Button></div>}
        </div>
      </div>
    </header>

    <main className="client-content" id="client-content">
      {!isPackagePage ? <ClientWorkspace view={view} /> : <>
      <div className="client-page-heading"><h1>Paket yang Kami Sediakan</h1><p>Pilih paket siap pakai atau buat penawaran kustom untuk proyek Anda. Setiap langkah sudah jelas agar tim dan manager bisa melanjutkan ke tahap berikutnya tanpa keraguan.</p></div>
      {notice && <Notice success>{notice}</Notice>}
      {actionError && !confirming && !responseAction && <Notice>{actionError}</Notice>}
      {error ? <div className="client-load-state"><Notice>{error}</Notice><Button onClick={retry}>Coba lagi</Button></div> : loading ? <p className="client-load-state" role="status">Memuat paket dan penawaran…</p> : <>
        <section aria-labelledby="packages-heading"><div className="client-section-heading"><div><h2 id="packages-heading">Paket Siap Pakai</h2><p>Pilih paket yang sudah jadi sebagai awal penawaran proyek Anda.</p></div><Badge tone="primary" className="client-section-badge">Siap Pakai</Badge></div>
          {visiblePackages.length > 0 ? <div className="client-packages">{visiblePackages.map((item) => <PackageCard key={item.id} name={item.name} description={item.description ?? ''} price={rupiah(item.price)} recordingHours={item.recording_hours} songCount={item.track_count}
            className="client-package" phases={stages.filter((stage) => item[`include_${stage.key}`]).map((stage) => stage.name)}
            phaseIcons={Object.fromEntries(stages.map((stage) => [stage.name, <img key={stage.key} src={stage.icon} alt="" />]))} menuIcon={<img src={assets.more} alt="" />}
            separator={<img src={assets.separator} alt="" />}
            onMenuClick={() => setSelectedPackage(item)} />)}</div> : <p className="client-empty">{search ? 'Tidak ada paket yang cocok dengan pencarianmu.' : 'Belum ada paket yang tersedia. Kamu tetap bisa membuat penawaran kustom.'}</p>}
          {!search && packages.length > 3 && <Button className="client-show-packages" variant="text" onClick={() => setShowAllPackages(!showAllPackages)}>{showAllPackages ? 'Tampilkan Lebih Sedikit' : `Lihat Semua ${packages.length} Paket`}</Button>}
        </section>

        <section ref={composer} className="client-custom-section" aria-labelledby="custom-heading"><div className="client-section-heading"><div><h2 id="custom-heading">Penawaran Kustom</h2><p>Buat penawaran sesuai kebutuhan proyek Anda. Tim akan meninjau harga, mengirimkan penawaran, dan melanjutkan ke checkout jika disetujui.</p></div><Badge tone="primary" className="client-section-badge">Kustom</Badge></div>
          <div className="client-custom-card">
            <div className="client-composer"><div className="client-composer-heading"><h3>Buat Penawaran Kustom</h3><p>Pilih tahap yang dibutuhkan, tentukan durasi rekaman dan jumlah lagu, lalu masukkan harga manual jika diperlukan. Penawaran akan dikirim ke manager untuk ditinjau.</p></div>
              <div className="client-phases" role="group" aria-label="Tahap produksi">{stages.map((stage) => <button type="button" key={stage.key} aria-pressed={spec[`include_${stage.key}`]} className={spec[`include_${stage.key}`] ? 'is-selected' : ''} onClick={() => setSpec((current) => {
                const selected = !current[`include_${stage.key}`]
                return { ...current, [`include_${stage.key}`]: selected, ...(stage.key === 'recording' ? { recording_hours: selected ? 3 : 0 } : {}) }
              })}><img src={stage.largeIcon} alt="" /><span>{stage.name}</span><small>{rupiah(Number(prices.find((rate) => rate.stage === stage.key)?.price ?? 0))} / {stage.unit}</small></button>)}</div>
              <div className="client-quantities"><Stepper label="Durasi Rekaman (Total Jam)" value={spec.recording_hours} min={spec.include_recording ? 1 : 0} disabled={!spec.include_recording} onChange={(value) => setSpec({ ...spec, recording_hours: value })} /><Stepper label="Jumlah Lagu" value={spec.track_count} onChange={(value) => setSpec({ ...spec, track_count: value })} /></div>
              <label className="client-price-label">Input Harga Tawaran<span className="client-price-input"><span>RP</span><input aria-label="Input Harga Tawaran" type="number" min={1} step={1} disabled={!manual} value={manual ? manualPrice : estimated} onChange={(event) => setManualPrice(event.target.value)} /></span></label>
              <div className="client-manual"><span>Atur Harga Manual</span><Switch checked={manual} onChange={setManual} aria-label="Atur Harga Manual" className="client-manual-switch" /></div>
              {count === 0 && <Notice>Pilih minimal satu tahap produksi.</Notice>}
              {manual && (!Number.isSafeInteger(total) || total <= 0) && <Notice>Masukkan harga tawaran berupa bilangan bulat lebih dari nol.</Notice>}
              {!ratesReady && <Notice>Tarif produksi belum lengkap. Hubungi studio sebelum mengirim penawaran.</Notice>}
            </div>
            <aside className="client-summary" aria-labelledby="summary-heading"><div><h3 id="summary-heading">Ringkasan Penawaran</h3><p>Total estimasi dihitung berdasarkan pilihan Anda. Harga akhir ditinjau oleh manager.</p></div>
              <dl><div><dt>Tahap Produksi</dt><dd>{count} Tahap</dd></div><div><dt>Durasi Rekaman</dt><dd>{spec.recording_hours} Jam Total</dd></div><div><dt>Jumlah Lagu</dt><dd>{spec.track_count} Lagu</dd></div><div><dt>Status</dt><dd className="client-primary">Belum Dikirim</dd></div></dl>
              <div className="client-summary-total" aria-live="polite"><h3>Estimasi Total</h3><strong>{Number.isFinite(total) ? rupiah(total) : '—'}</strong><p>Harga akhir akan dikonfirmasi setelah manager menyetujui penawaran.</p></div>
              <div className="client-summary-actions"><Button disabled={!valid || busy} onClick={() => { setActionError(''); setConfirming(true) }}>Kirim Penawaran</Button><Button variant="text" onClick={resetDraft}>Batal</Button></div>
            </aside>
            <div className="client-history-column"><QuoteHistory quote={quote} quotations={quotations} loading={historyLoading} error={historyError} onSelect={(id) => void selectQuote(id)} onReload={() => void changePage(page)} page={page} lastPage={lastPage} onPage={(next) => void changePage(next)} />
              {quote && <div className="client-quote-status"><span>{quote.name}</span><Badge tone={quote.status === 'accepted' ? 'success' : quote.status === 'declined' ? 'error' : 'primary'}>{quote.status_label}</Badge>
                {quote.status === 'awaiting_client' && <div className="client-response-buttons"><Button onClick={() => { setActionError(''); setResponseAction('accept') }}>Terima</Button><Button variant="outlined" onClick={() => { setActionError(''); setCounterPrice(String(quote.latest_offer?.price ?? quote.auto_price)); setResponseAction('counter') }}>Tawar Lagi</Button><Button variant="text" onClick={() => { setActionError(''); setResponseAction('decline') }}>Tolak</Button></div>}
              </div>}
            </div>
          </div>
        </section>
      </>}
      </>}
    </main>
    <footer className="client-footer">© {new Date().getFullYear()} Studio Recording Tungku. All Rights Reserved</footer>

    {editingProfile && <ProfileDialog user={user} onClose={() => setEditingProfile(false)} onSaved={(saved) => { onUserChange(saved); setEditingProfile(false) }} />}
    {selectedPackage && <Modal title={selectedPackage.name} onClose={() => setSelectedPackage(null)}><p>Pilih langkah berikutnya untuk paket {selectedPackage.name}.</p><div className="client-modal-actions"><Button variant="outlined" onClick={() => { choosePackage(selectedPackage); setSelectedPackage(null) }}>Sesuaikan Penawaran</Button><Button onClick={() => { navigate(`/client/booking?package=${selectedPackage.id}`); setSelectedPackage(null) }}>Booking Paket</Button></div></Modal>}
    {confirming && <Modal title="Kirim Penawaran" onClose={() => { if (!busy) setConfirming(false) }}><form onSubmit={sendQuote}><p>Beri nama proyek agar studio mudah mengenali penawaranmu.</p><Input label="Nama Proyek" placeholder="Contoh: Single Pertama" value={name} onChange={(event) => setName(event.target.value)} maxLength={100} required autoFocus /><p className="client-modal-total">Harga tawaran <strong>{rupiah(total)}</strong></p>{actionError && <Notice>{actionError}</Notice>}<div className="client-modal-actions"><Button variant="text" disabled={busy} onClick={() => setConfirming(false)}>Kembali</Button><Button type="submit" disabled={busy || !name.trim()}>{busy ? 'Mengirim…' : 'Kirim ke Studio'}</Button></div></form></Modal>}
    {responseAction && quote && <Modal title={responseAction === 'counter' ? 'Tawar Harga Kembali' : responseAction === 'accept' ? 'Terima Penawaran Studio' : 'Tolak Penawaran Studio'} onClose={() => { if (!busy) setResponseAction(null) }}><form onSubmit={sendResponse}><p>{quote.name}</p>{responseAction === 'counter' ? <Input label="Harga Tawaran (Rp)" value={counterPrice} type="number" min={1} step={1} required autoFocus onChange={(event) => setCounterPrice(event.target.value)} /> : <p>{responseAction === 'accept' ? 'Harga dari studio akan menjadi harga yang disepakati.' : 'Penawaran ini akan ditutup setelah ditolak.'}</p>}{actionError && <Notice>{actionError}</Notice>}<div className="client-modal-actions"><Button variant="text" disabled={busy} onClick={() => setResponseAction(null)}>Batal</Button><Button type="submit" disabled={busy}>{busy ? 'Mengirim…' : 'Konfirmasi'}</Button></div></form></Modal>}
  </div>
}

export default function ClientPortal() {
  const [user, setUser] = useState<AuthUser | null>(null)
  const [status, setStatus] = useState<'loading' | 'ready' | 'guest' | 'error'>('loading')
  const [error, setError] = useState('')
  useEffect(() => {
    let active = true
    void getCurrentUser().then((current) => { if (active) { setUser(current); setStatus('ready') } }).catch((failure: unknown) => {
      if (!active) return
      if (failure instanceof Error && 'status' in failure && failure.status === 401) setStatus('guest')
      else { setError(getApiErrorMessage(failure)); setStatus('error') }
    })
    return () => { active = false }
  }, [])
  if (status === 'guest') return <Navigate to="/login" replace />
  if (status === 'loading') return <LoadingScreen label="Memeriksa sesi klien" />
  if (status === 'error') return <div className="client-portal client-access"><Notice>{error}</Notice><Button onClick={() => window.location.reload()}>Coba lagi</Button></div>
  if (!user || user.role !== 'client') return <div className="client-portal client-access"><h1>Portal Klien</h1><p>Halaman ini untuk akun Klien. Akun Manager dan Operator dapat masuk melalui portal Studio.</p><a href="/staff/login">Buka portal Studio</a></div>
  return <ClientPackages user={user} onUserChange={setUser} />
}
