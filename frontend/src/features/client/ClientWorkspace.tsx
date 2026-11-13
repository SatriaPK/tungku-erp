import { useEffect, useState, type FormEvent, type ReactNode } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { Badge, Button } from '../../components/ui'
import { getApiErrorMessage } from '../../lib/auth-api'
import {
  previewCoupon, type CouponPreview, checkAvailability, getPackages, getBookings, getDelivery, getInvoice, getInvoices, getProjects, getQuotations, getRooms, holdBooking, initiateInvoicePayment, offerDate, rupiah,
  type BookingAvailability, type ClientBooking, type ClientInvoice, type ClientProject, type Collection, type Delivery, type Quotation,
} from './client-api'
import './client-workspace.css'

type View = 'dashboard' | 'booking' | 'penawaran' | 'project' | 'transaksi'

function useCollection<T>(fetcher: (page: number) => Promise<Collection<T>>, page = 1) {
  const [data, setData] = useState<T[]>([])
  const [meta, setMeta] = useState<Collection<T>['meta']>()
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  useEffect(() => {
    let active = true
    void fetcher(page).then((result) => {
      if (active) { setData(result.data); setMeta(result.meta); setLoading(false) }
    }).catch((failure: unknown) => {
      if (active) { setError(getApiErrorMessage(failure)); setLoading(false) }
    })
    return () => { active = false }
  }, [fetcher, page, version])
  return { data, meta, loading, error, reload: () => { setError(''); setLoading(true); setVersion((current) => current + 1) } }
}

function State({ loading, error, reload, empty, count }: { loading: boolean; error: string; reload: () => void; empty: string; count: number }) {
  if (loading) return <p className="client-workspace-state" role="status">Memuat data…</p>
  if (error) return <div className="client-workspace-state"><p role="alert">{error}</p><Button variant="text" onClick={reload}>Coba lagi</Button></div>
  if (!count) return <p className="client-workspace-state">{empty}</p>
  return null
}

function PageHead({ title, description, action }: { title: string; description: string; action?: ReactNode }) {
  return <div className="client-workspace-head"><div><h1>{title}</h1><p>{description}</p></div>{action}</div>
}

function Pager({ current, last, onChange }: { current: number; last: number; onChange: (next: number) => void }) {
  if (last <= 1) return null
  return <nav className="client-workspace-pager" aria-label="Halaman data"><button disabled={current <= 1} onClick={() => onChange(current - 1)}>Sebelumnya</button><span>{current} / {last}</span><button disabled={current >= last} onClick={() => onChange(current + 1)}>Berikutnya</button></nav>
}

function Dashboard() {
  const projects = useCollection(getProjects)
  const bookings = useCollection(getBookings)
  const quotes = useCollection(getQuotations)
  const invoices = useCollection(getInvoices)
  const active = projects.data.filter((project) => !['completed', 'cancelled'].includes(project.status))
  const pending = quotes.data.filter((quote) => quote.status === 'awaiting_client')
  const unpaid = invoices.data.filter((invoice) => !['paid', 'cancelled'].includes(invoice.status))
  return <>
    <PageHead title="Selamat Datang di Tungku Studio" description="Pantau jadwal rekaman, penawaran, proyek, dan transaksi Anda dari satu tempat." action={<Link className="ts-button" to="/client/packages">Lihat Paket</Link>} />
    <div className="client-stat-grid"><Link to="/client/project"><span>Proyek Aktif</span><strong>{projects.loading ? '…' : active.length}</strong><small>Lihat proyek →</small></Link><Link to="/client/booking"><span>Jadwal Booking</span><strong>{bookings.loading ? '…' : bookings.data.length}</strong><small>Lihat jadwal →</small></Link><Link to="/client/penawaran"><span>Perlu Tanggapan</span><strong>{quotes.loading ? '…' : pending.length}</strong><small>Lihat penawaran →</small></Link><Link to="/client/transaksi"><span>Invoice Belum Lunas</span><strong>{invoices.loading ? '…' : unpaid.length}</strong><small>Lihat transaksi →</small></Link></div>
    <div className="client-workspace-columns"><section className="client-workspace-panel"><header><h2>Proyek Terbaru</h2><Link to="/client/project">Lihat Semua</Link></header><State loading={projects.loading} error={projects.error} reload={projects.reload} empty="Belum ada proyek. Pilih paket untuk memulai produksi." count={projects.data.length} /><ul className="client-simple-list">{projects.data.slice(0, 3).map((project) => <li key={project.id}><div><strong>{project.name}</strong><small>{project.code}</small></div><Badge tone={project.status === 'completed' ? 'success' : 'primary'}>{project.status_label}</Badge></li>)}</ul></section>
      <section className="client-workspace-panel"><header><h2>Jadwal Terdekat</h2><Link to="/client/booking">Lihat Semua</Link></header><State loading={bookings.loading} error={bookings.error} reload={bookings.reload} empty="Belum ada jadwal booking." count={bookings.data.length} /><ul className="client-simple-list">{bookings.data.slice(0, 3).map((booking) => <li key={booking.id}><div><strong>{booking.room_name}</strong><small>{offerDate(booking.start_at)}</small></div><Badge tone="neutral">{booking.status_label}</Badge></li>)}</ul></section></div>
  </>
}

function BookingPage() {
  const [page, setPage] = useState(1)
  const bookings = useCollection(getBookings, page)
  const rooms = useCollection(getRooms)
  const quotes = useCollection(getQuotations)
  const [params] = useSearchParams()
  const [roomId, setRoomId] = useState('')
  const [start, setStart] = useState('')
  const [end, setEnd] = useState('')
  const [target, setTarget] = useState(params.get('package') ? `package:${params.get('package')}` : '')
  const [couponCode, setCouponCode] = useState('')
  const [coupon, setCoupon] = useState<CouponPreview | null>(null)
  const [couponError, setCouponError] = useState('')
  async function applyCoupon() {
    if (!target || !couponCode.trim() || busy) return
    setBusy(true); setCouponError(''); setCoupon(null)
    const [type, id] = target.split(':')
    try { setCoupon((await previewCoupon(couponCode.trim().toUpperCase(), type === 'package' ? { package_id: Number(id) } : { quotation_id: Number(id) })).data) }
    catch (failure) { setCouponError(getApiErrorMessage(failure)) }
    finally { setBusy(false) }
  }
  const [available, setAvailable] = useState<BookingAvailability | null>(null)
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [created, setCreated] = useState(false)
  const packages = useCollection(getPackages)
  const accepted = quotes.data.filter((quote) => quote.status === 'accepted')
  function timestamp(value: string) { return `${value}:00+07:00` }
  async function check(event: FormEvent) {
    event.preventDefault(); setBusy(true); setError(''); setMessage(''); setAvailable(null)
    try {
      const result = await checkAvailability(Number(roomId), timestamp(start), timestamp(end))
      setAvailable(result.data)
      setMessage(result.data.available ? 'Slot tersedia. Lanjutkan untuk menahan jadwal sementara.' : (result.data.reason ?? 'Slot tidak tersedia.'))
    } catch (failure) { setError(getApiErrorMessage(failure)) }
    finally { setBusy(false) }
  }
  async function hold() {
    if (!available?.available || !target || busy) return
    const [type, id] = target.split(':')
    setBusy(true); setError(''); setMessage('')
    try {
      const result = await holdBooking(Number(roomId), timestamp(start), timestamp(end), { ...(type === 'package' ? { package_id: Number(id) } : { quotation_id: Number(id) }), ...(coupon ? { coupon_code: coupon.code } : {}) })
      setCreated(true); bookings.reload()
      setMessage(`Jadwal berhasil ditahan${result.data.held_until ? ` sampai ${offerDate(result.data.held_until)}` : ''}. Selesaikan pembayaran melalui invoice agar booking dikonfirmasi.`)
    } catch (failure) { setError(getApiErrorMessage(failure)); setAvailable(null) }
    finally { setBusy(false) }
  }
  const invalidDates = !start || !end || new Date(timestamp(end)).getTime() - new Date(timestamp(start)).getTime() < 60 * 60 * 1000
  return <><PageHead title="Jadwal Booking" description="Pilih ruang dan waktu rekaman. Periksa ketersediaan sebelum menahan jadwal." />
    <section className="client-workspace-panel client-booking-form"><h2>Buat Booking</h2><form onSubmit={check}><label>Paket atau penawaran<select disabled={busy || created} value={target} required onChange={(event) => { setTarget(event.target.value); setAvailable(null); setCoupon(null); setCouponError(''); setCreated(false) }}><option value="">Pilih paket atau penawaran</option>{packages.data.map((item) => <option key={item.id} value={`package:${item.id}`}>{item.name} · {rupiah(item.price)}</option>)}{accepted.map((item) => <option key={item.id} value={`quotation:${item.id}`}>{item.name} · Disetujui</option>)}</select></label>
      <label>Ruang rekaman<select value={roomId} required onChange={(event) => { setRoomId(event.target.value); setAvailable(null) }}><option value="">Pilih ruang</option>{rooms.data.map((room) => <option key={room.id} value={room.id}>{room.name}</option>)}</select></label>
      <label>Mulai<input type="datetime-local" value={start} required onChange={(event) => { setStart(event.target.value); setAvailable(null) }} /></label><label>Selesai<input type="datetime-local" value={end} required onChange={(event) => { setEnd(event.target.value); setAvailable(null) }} /></label>
      <div className="client-coupon-field"><label>Kode kupon (opsional)<input value={couponCode} maxLength={24} placeholder="Masukkan kode kupon" disabled={busy || created} onChange={event => { setCouponCode(event.target.value.toUpperCase()); setCoupon(null); setCouponError('') }} /></label><Button variant="outlined" disabled={busy || created || !target || !couponCode.trim()} onClick={() => void applyCoupon()}>Terapkan Kupon</Button>{coupon && <Button variant="text" disabled={busy || created} onClick={() => { setCoupon(null); setCouponCode('') }}>Hapus Kupon</Button>}{couponError && <p role="alert" className="client-workspace-error">{couponError}</p>}{coupon && <div className="client-coupon-summary" role="status"><p>Kupon <strong>{coupon.code}</strong> berhasil diterapkan.</p><dl><dt>Harga awal</dt><dd>{rupiah(coupon.subtotal)}</dd><dt>Potongan kupon</dt><dd>-{rupiah(coupon.discount)}</dd><dt>Total setelah diskon</dt><dd><strong>{rupiah(coupon.total)}</strong></dd></dl><small>Kupon diperiksa ulang saat booking dibuat. Tagihan minimal Rp1.</small></div>}</div>
      <div className="client-booking-actions"><Button type="submit" disabled={busy || invalidDates || !target || !roomId}>Periksa Ketersediaan</Button><Button disabled={busy || !available?.available || created || (!!couponCode.trim() && !coupon)} onClick={() => void hold()}>Tahan Jadwal</Button></div></form>
      {error && <p className="client-workspace-error" role="alert">{error}</p>}{message && <p className={available?.available || created ? 'client-workspace-success' : 'client-workspace-error'} role="status">{message}</p>}{created && <Link className="client-inline-link" to="/client/transaksi">Lihat Transaksi →</Link>}
      {(packages.error || rooms.error || quotes.error) && <p className="client-workspace-error" role="alert">{packages.error || rooms.error || quotes.error}</p>}
    </section>
    <section className="client-workspace-panel"><header><h2>Booking Saya</h2></header><State loading={bookings.loading} error={bookings.error} reload={bookings.reload} empty="Belum ada booking." count={bookings.data.length} /><div className="client-record-grid">{bookings.data.map((booking: ClientBooking) => <article key={booking.id}><div><h3>{booking.room_name}</h3><Badge tone={booking.status === 'confirmed' ? 'success' : 'primary'}>{booking.status_label}</Badge></div><p>{offerDate(booking.start_at)}</p><small>{new Intl.DateTimeFormat('id-ID', { timeStyle: 'short', timeZone: 'Asia/Jakarta' }).format(new Date(booking.start_at))}–{new Intl.DateTimeFormat('id-ID', { timeStyle: 'short', timeZone: 'Asia/Jakarta' }).format(new Date(booking.end_at))} WIB</small></article>)}</div><Pager current={page} last={bookings.meta?.last_page ?? 1} onChange={setPage} /></section>
  </>
}

function QuotationsPage() {
  const [page, setPage] = useState(1)
  const quotations = useCollection(getQuotations, page)
  return <><PageHead title="Penawaran Saya" description="Lihat penawaran, tanggapi studio, dan pantau harga yang disepakati." action={<Link className="ts-button" to="/client/packages?buat=1">Buat Penawaran</Link>} />
    <State loading={quotations.loading} error={quotations.error} reload={quotations.reload} empty="Belum ada penawaran. Buat penawaran dari halaman paket." count={quotations.data.length} />
    <div className="client-record-grid">{quotations.data.map((item: Quotation) => <article key={item.id}><div><h3>{item.name}</h3><Badge tone={item.status === 'accepted' ? 'success' : item.status === 'declined' ? 'error' : 'primary'}>{item.status_label}</Badge></div><p>{offerDate(item.created_at)}</p><strong>{rupiah(item.agreed_price ?? item.latest_offer?.price ?? item.auto_price)}</strong><Link to={`/client/packages?penawaran=${item.id}#riwayat-penawaran`}>{item.status === 'awaiting_client' ? 'Tanggapi Penawaran' : 'Lihat Revisi'} →</Link></article>)}</div>
    <Pager current={page} last={quotations.meta?.last_page ?? 1} onChange={setPage} />
  </>
}

function ProjectsPage() {
  const [page, setPage] = useState(1)
  const projects = useCollection(getProjects, page)
  const [deliveryId, setDeliveryId] = useState<number | null>(null)
  const [delivery, setDelivery] = useState<Delivery[]>([])
  const [deliveryError, setDeliveryError] = useState('')
  const [deliveryLoading, setDeliveryLoading] = useState(false)
  async function openDelivery(id: number) {
    setDeliveryId(id); setDeliveryLoading(true); setDeliveryError(''); setDelivery([])
    try { setDelivery((await getDelivery(id)).data) }
    catch (failure) { setDeliveryError(getApiErrorMessage(failure)) }
    finally { setDeliveryLoading(false) }
  }
  return <><PageHead title="Project Saya" description="Pantau progres produksi dan akses hasil akhir ketika proyek selesai serta pelunasan terverifikasi." action={<Link className="ts-button" to="/client/packages">Lihat Paket</Link>} />
    <State loading={projects.loading} error={projects.error} reload={projects.reload} empty="Belum ada proyek. Proyek dibuat setelah proses booking dan pembayaran awal." count={projects.data.length} />
    <div className="client-record-grid">{projects.data.map((item: ClientProject) => { const total = item.stages.length; const done = item.stages.filter((stage) => stage.status === 'completed').length; return <article key={item.id}><div><h3>{item.name}</h3><Badge tone={item.status === 'completed' ? 'success' : 'primary'}>{item.status_label}</Badge></div><p>{item.code} · {item.package_name ?? 'Paket Kustom'}</p><div className="client-progress"><div style={{ width: `${total ? Math.round(done / total * 100) : 0}%` }} /></div><small>{done} dari {total} tahap selesai</small><strong>{rupiah(item.price)}</strong>{item.status === 'completed' && <Button variant="text" onClick={() => void openDelivery(item.id)}>Lihat Hasil Akhir</Button>}</article> })}</div>
    <Pager current={page} last={projects.meta?.last_page ?? 1} onChange={setPage} />
    {deliveryId !== null && <section className="client-workspace-panel"><header><h2>Hasil Akhir</h2><button className="client-inline-link" onClick={() => setDeliveryId(null)}>Tutup</button></header>{deliveryLoading && <p role="status">Memuat hasil…</p>}{deliveryError && <p role="alert" className="client-workspace-error">{deliveryError}</p>}{!deliveryLoading && !deliveryError && delivery.length === 0 && <p>Belum ada hasil akhir yang tersedia.</p>}<ul className="client-simple-list">{delivery.map((item) => <li key={`${item.track_id}-${item.version}`}><div><strong>{item.track_title}</strong><small>Versi {item.version} · {item.stage}</small></div>{item.result_url && <a className="client-inline-link" href={item.result_url} target="_blank" rel="noopener noreferrer">Buka Hasil ↗</a>}</li>)}</ul></section>}
  </>
}

function TransactionsPage() {
  const [page, setPage] = useState(1)
  const invoices = useCollection(getInvoices, page)
  const [detail, setDetail] = useState<(ClientInvoice & { payment?: { status_label: string; payment_url: string | null; provider_label: string }; items?: { id: number; description: string; total: number }[] }) | null>(null)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const [gateway, setGateway] = useState<'midtrans' | 'xendit'>('midtrans')
  const [paymentUrl, setPaymentUrl] = useState('')
  async function pay(id: number) {
    setLoading(true); setError('')
    try { const result = await initiateInvoicePayment(id, gateway); setPaymentUrl(result.data.payment_url ?? '') }
    catch (failure) { setError(getApiErrorMessage(failure)) }
    finally { setLoading(false) }
  }
  async function show(id: number) {
    setLoading(true); setError(''); setPaymentUrl(''); setDetail(null)
    try { setDetail((await getInvoice(id)).data) }
    catch (failure) { setError(getApiErrorMessage(failure)) }
    finally { setLoading(false) }
  }
  return <><PageHead title="Transaksi Saya" description="Lihat invoice, jumlah tagihan, dan status pembayaran terbaru dari studio." />
    <State loading={invoices.loading} error={invoices.error} reload={invoices.reload} empty="Belum ada invoice. Tagihan akan muncul setelah booking dibuat." count={invoices.data.length} />
    <div className="client-record-grid">{invoices.data.map((item: ClientInvoice) => <article key={item.id}><div><h3>{item.invoice_number}</h3><Badge tone={item.status === 'paid' ? 'success' : item.is_overdue ? 'error' : 'primary'}>{item.status_label}</Badge></div><p>{item.project?.name ?? 'Booking Rekaman'}</p><strong>{rupiah(item.total)}</strong><small>Terbit {item.issue_date ? offerDate(item.issue_date) : '—'} · Jatuh tempo {item.due_date ? offerDate(item.due_date) : '—'}</small><Button variant="text" onClick={() => void show(item.id)}>Lihat Rincian</Button></article>)}</div>
    <Pager current={page} last={invoices.meta?.last_page ?? 1} onChange={setPage} />
    {(loading || error || detail) && <section className="client-workspace-panel"><header><h2>Rincian Invoice</h2><button className="client-inline-link" onClick={() => { setDetail(null); setError('') }}>Tutup</button></header>{loading && <p role="status">Memuat invoice…</p>}{error && <p role="alert" className="client-workspace-error">{error}</p>}{detail && <><div className="client-invoice-top"><h3>{detail.invoice_number}</h3><strong>{rupiah(detail.total)}</strong></div><p>{detail.status_label}</p>{detail.coupon && <p className="client-workspace-success">Kupon {detail.coupon.code} ? Hemat {rupiah(detail.coupon.discount)}</p>}<ul className="client-simple-list">{detail.items?.map((item) => <li key={item.id}><span>{item.description}</span><strong>{rupiah(item.total)}</strong></li>)}</ul>{(paymentUrl || detail.payment?.payment_url) && <a className="ts-button" href={paymentUrl || detail.payment?.payment_url || '#'} target="_blank" rel="noopener noreferrer">Buka Pembayaran</a>}{detail.status === 'issued' && !detail.payment && !paymentUrl && <div className="client-payment-actions"><label>Metode pembayaran<select value={gateway} onChange={(event) => setGateway(event.target.value as 'midtrans' | 'xendit')}><option value="midtrans">Midtrans</option><option value="xendit">Xendit</option></select></label><Button disabled={loading} onClick={() => void pay(detail.id)}>Buat Pembayaran</Button></div>}{detail.payment && <p>Status pembayaran: {detail.payment.status_label} · {detail.payment.provider_label}</p>}</>}</section>}
  </>
}

export function ClientWorkspace({ view }: { view: View }) {
  if (view === 'booking') return <BookingPage />
  if (view === 'penawaran') return <QuotationsPage />
  if (view === 'project') return <ProjectsPage />
  if (view === 'transaksi') return <TransactionsPage />
  return <Dashboard />
}
