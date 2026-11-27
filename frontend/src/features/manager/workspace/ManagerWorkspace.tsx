import { useEffect, useState, type FormEvent } from 'react'
import { Button } from '../../../components/ui'
import { managerRequest, money, value, display, type Row, type Collection } from './manager-api'
import './ManagerWorkspace.css'

export type Section = 'booking' | 'quotation' | 'project' | 'inventory' | 'packages' | 'crm' | 'invoice' | 'reports' | 'expenses' | 'operator' | 'settings'
type Config = { title: string; description: string; path: string; columns: { title: string; field: string; money?: boolean }[] }
const configs: Record<Exclude<Section, 'reports'>, Config> = {
  booking: { title: 'Booking', description: 'Jadwal rekaman dan status reservasi.', path: 'bookings', columns: [{ title: 'Ruang', field: 'room_name' }, { title: 'Klien', field: 'client_name' }, { title: 'Mulai', field: 'start_at' }, { title: 'Status', field: 'status_label' }] },
  quotation: { title: 'Quotation', description: 'Penawaran klien yang perlu ditinjau dan ditanggapi.', path: 'quotations', columns: [{ title: 'Penawaran', field: 'name' }, { title: 'Klien', field: 'client.name' }, { title: 'Harga', field: 'latest_offer.price', money: true }, { title: 'Status', field: 'status_label' }] },
  project: { title: 'Project', description: 'Pantau proyek dan tugaskan operator pada setiap tahap.', path: 'projects', columns: [{ title: 'Kode', field: 'code' }, { title: 'Project', field: 'name' }, { title: 'Klien', field: 'client.name' }, { title: 'Nilai', field: 'price', money: true }, { title: 'Status', field: 'status_label' }] },
  inventory: { title: 'Inventaris', description: 'Peralatan studio dan nilai bukunya.', path: 'inventories', columns: [{ title: 'Kode', field: 'code' }, { title: 'Nama', field: 'name' }, { title: 'Kategori', field: 'category.name' }, { title: 'Nilai Buku', field: 'book_value', money: true }, { title: 'Status', field: 'status' }] },
  packages: { title: 'Manajemen Paket', description: 'Paket layanan yang dapat dipilih klien.', path: 'packages', columns: [{ title: 'Nama Paket', field: 'name' }, { title: 'Lagu', field: 'track_count' }, { title: 'Harga', field: 'price', money: true }, { title: 'Tersedia', field: 'is_active' }] },
  crm: { title: 'CRM', description: 'Kampanye dan hubungan dengan klien.', path: 'crm/campaigns', columns: [{ title: 'Kampanye', field: 'name' }, { title: 'Status', field: 'status' }, { title: 'Penerima', field: 'recipient_counts.total' }, { title: 'Dibuat', field: 'created_at' }] },
  invoice: { title: 'Invoice', description: 'Tagihan dan pembayaran klien.', path: 'invoices', columns: [{ title: 'Nomor Invoice', field: 'invoice_number' }, { title: 'Tanggal', field: 'issue_date' }, { title: 'Project', field: 'project.name' }, { title: 'Total', field: 'total', money: true }, { title: 'Status', field: 'billing_status_label' }] },
  expenses: { title: 'Pengeluaran', description: 'Biaya operasional studio.', path: 'expenses', columns: [{ title: 'Tanggal', field: 'spent_on' }, { title: 'Keterangan', field: 'description' }, { title: 'Kategori', field: 'category.name' }, { title: 'Jumlah', field: 'amount', money: true }] },
  operator: { title: 'Operator', description: 'Kelola akun operator dan akses ke pekerjaan studio.', path: 'operator-accounts', columns: [{ title: 'Nama', field: 'name' }, { title: 'Email', field: 'email' }, { title: 'Telepon', field: 'phone' }, { title: 'Status', field: 'is_active' }] },
  settings: { title: 'Pengaturan', description: 'Konfigurasi studio yang tersimpan.', path: 'settings', columns: [{ title: 'Pengaturan', field: 'key' }, { title: 'Nilai', field: 'value' }, { title: 'Keterangan', field: 'description' }] },
}
const paymentConfig: Config = { title: 'Pembayaran', description: 'Status pembayaran klien dan konfirmasi pembayaran manual.', path: 'payments', columns: [{ title: 'Order ID', field: 'order_id' }, { title: 'Klien', field: 'client.name' }, { title: 'Project', field: 'project.name' }, { title: 'Jumlah', field: 'amount', money: true }, { title: 'Status', field: 'status_label' }] }
const stageName: Record<string, string> = { recording: 'Recording', editing: 'Editing', mixing: 'Mixing', mastering: 'Mastering' }
function useList(path: string, page: number, version: number) {
  const key = `${path}:${page}:${version}`
  const [state, setState] = useState<{ key: string; rows: Row[]; meta?: Collection['meta']; error: string }>({ key: '', rows: [], error: '' })
  useEffect(() => {
    let active = true
    void managerRequest<Collection>(`${path}${path.includes('?') ? '&' : '?'}page=${page}`).then((response) => { if (active) setState({ key, rows: response.data ?? [], meta: response.meta, error: '' }) }).catch((cause: unknown) => { if (active) setState({ key, rows: [], error: cause instanceof Error ? cause.message : 'Data tidak dapat dimuat.' }) })
    return () => { active = false }
  }, [key, path, page])
  return { rows: state.rows, meta: state.meta, loading: state.key !== key, error: state.error }
}
function AssignOperatorModal({ project, close, saved }: { project: Row; close: () => void; saved: () => void }) {
  const [operators, setOperators] = useState<Row[]>([])
  const [assignments, setAssignments] = useState<Record<string, number>>({})
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)
  const stages = (project.stages as Row[] ?? []).map((stage) => String(stage.stage))
  useEffect(() => { void managerRequest<Collection>('operators').then((response) => setOperators(response.data.filter((operator) => operator.is_active !== false))).catch((cause: unknown) => setError(cause instanceof Error ? cause.message : 'Operator tidak dapat dimuat.')) }, [])
  const selected = Object.values(assignments).filter(Boolean)
  const valid = stages.length > 0 && stages.every((stage) => assignments[stage]) && new Set(selected).size === stages.length
  async function submit(event: FormEvent) {
    event.preventDefault(); if (!valid || saving) return
    setSaving(true); setError('')
    try { await managerRequest(`projects/${project.id}/assignments`, 'POST', { assignments }); saved() }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Penugasan gagal.') }
    finally { setSaving(false) }
  }
  return <div className="manager-modal-backdrop" onMouseDown={(event) => { if (event.target === event.currentTarget) close() }}><section aria-labelledby="manager-assign-title" aria-modal="true" className="manager-modal" role="dialog"><header><div><span className="manager-modal-kicker">PENUGASAN PROYEK</span><h2 id="manager-assign-title">Pilih operator</h2><p>{display(project.name)} - {display(value(project, 'client.name'))}</p></div><button aria-label="Tutup" className="manager-modal-close" onClick={close} type="button">&times;</button></header><form onSubmit={(event) => void submit(event)}><p className="manager-modal-hint">Satu operator untuk tiap tahap. Pilih orang yang berbeda agar pekerjaan dan tanggung jawabnya jelas.</p><div className="manager-assignment-list">{stages.map((stage, index) => <label key={stage}><span className="manager-assignment-number">{index + 1}</span><span><strong>{stageName[stage] ?? stage}</strong><small>Tahap {index + 1} dari {stages.length}</small></span><select aria-label={`Operator ${stageName[stage] ?? stage}`} required value={assignments[stage] ?? ''} onChange={(event) => setAssignments((current) => ({ ...current, [stage]: Number(event.target.value) }))}><option value="">Pilih operator</option>{operators.map((operator) => <option key={String(operator.id)} value={Number(operator.id)} disabled={selected.includes(Number(operator.id)) && assignments[stage] !== Number(operator.id)}>{display(operator.name)}</option>)}</select></label>)}</div>{operators.length < stages.length && !error && <p className="manager-form-error">Operator aktif belum cukup untuk semua tahap.</p>}{error && <p className="manager-form-error" role="alert">{error}</p>}<footer><Button variant="text" onClick={close}>Batal</Button><Button type="submit" disabled={!valid || saving}>{saving ? 'Menyimpan...' : 'Simpan Penugasan'}</Button></footer></form></section></div>
}
function QuoteModal({ quote, close, saved }: { quote: Row; close: () => void; saved: () => void }) {
  const missingOffer = !value(quote, 'latest_offer.id')
  const [action, setAction] = useState<'accept' | 'counter' | 'decline'>('counter')
  const [price, setPrice] = useState(missingOffer ? '' : String(value(quote, 'latest_offer.price') ?? quote.auto_price ?? ''))
  const [note, setNote] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  async function submit(event: FormEvent) {
    event.preventDefault(); setBusy(true); setError('')
    try { await managerRequest(`quotations/${quote.id}/${missingOffer ? 'send-missing-offer' : 'respond'}`, 'POST', { response_type: action, latest_offer_id: value(quote, 'latest_offer.id'), ...(action === 'counter' ? { price: Number(price) } : {}), note }); saved() }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Tanggapan gagal.') }
    finally { setBusy(false) }
  }
  return <div className="manager-modal-backdrop" onMouseDown={(event) => { if (event.target === event.currentTarget) close() }}><section aria-labelledby="manager-quote-title" aria-modal="true" className="manager-modal" role="dialog"><header><div><span className="manager-modal-kicker">PENAWARAN KLIEN</span><h2 id="manager-quote-title">{display(quote.name)}</h2><p>{display(value(quote, 'client.name'))} - Tawaran terakhir {money(value(quote, 'latest_offer.price'))}</p></div><button aria-label="Tutup" className="manager-modal-close" onClick={close} type="button">&times;</button></header><form onSubmit={(event) => void submit(event)}>{missingOffer && <p role="status">Riwayat harga belum tersedia. Masukkan harga studio untuk dikirim kepada klien.</p>}<label>Keputusan<select disabled={missingOffer} value={action} onChange={(event) => setAction(event.target.value as typeof action)}><option value="accept">Terima tawaran</option><option value="counter">Ajukan harga baru</option><option value="decline">Tolak tawaran</option></select></label>{action === 'counter' && <label>Harga yang diajukan<input type="number" min="1" required value={price} onChange={(event) => setPrice(event.target.value)} /></label>}<label>Catatan untuk klien<textarea rows={3} maxLength={1000} value={note} onChange={(event) => setNote(event.target.value)} /></label>{error && <p className="manager-form-error" role="alert">{error}</p>}<footer><Button variant="text" onClick={close}>Batal</Button><Button type="submit" disabled={busy}>{busy ? 'Mengirim...' : 'Kirim Tanggapan'}</Button></footer></form></section></div>
}
function PackageModal({ item, close, saved }: { item: Row | null; close: () => void; saved: () => void }) {
  const [name, setName] = useState(String(item?.name ?? ''))
  const [description, setDescription] = useState(String(item?.description ?? ''))
  const [tracks, setTracks] = useState(Number(item?.track_count ?? 1))
  const [hours, setHours] = useState(Number(item?.recording_hours ?? 3))
  const [stages, setStages] = useState<Record<string, boolean>>(Object.fromEntries(Object.keys(stageName).map((stage) => [stage, Boolean(item?.[`include_${stage}`] ?? true)])))
  const [manual, setManual] = useState(String(item?.manual_price ?? ''))
  const [active, setActive] = useState(Boolean(item?.is_active ?? true))
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  async function submit(event: FormEvent) {
    event.preventDefault(); setBusy(true); setError('')
    try { await managerRequest(`packages${item ? `/${item.id}` : ''}`, item ? 'PUT' : 'POST', { name, description, track_count: tracks, recording_hours: stages.recording ? hours : null, ...Object.fromEntries(Object.keys(stageName).map((stage) => [`include_${stage}`, stages[stage]])), manual_price: manual === '' ? null : Number(manual), is_active: active }); saved() }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Paket gagal disimpan.') }
    finally { setBusy(false) }
  }
  return <div className="manager-modal-backdrop" onMouseDown={(event) => { if (event.target === event.currentTarget) close() }}><section aria-labelledby="manager-package-title" aria-modal="true" className="manager-modal" role="dialog"><header><div><span className="manager-modal-kicker">MANAJEMEN PAKET</span><h2 id="manager-package-title">{item ? 'Ubah paket' : 'Paket baru'}</h2><p>Isi layanan yang akan muncul di katalog klien.</p></div><button aria-label="Tutup" className="manager-modal-close" onClick={close} type="button">&times;</button></header><form onSubmit={(event) => void submit(event)}><label>Nama paket<input required maxLength={100} value={name} onChange={(event) => setName(event.target.value)} /></label><label>Deskripsi<textarea maxLength={255} rows={2} value={description} onChange={(event) => setDescription(event.target.value)} /></label><div className="manager-form-pair"><label>Jumlah lagu<input type="number" min="1" max="50" required value={tracks} onChange={(event) => setTracks(Number(event.target.value))} /></label><label>Jam rekaman<input type="number" min="1" max="100" required={stages.recording} disabled={!stages.recording} value={hours} onChange={(event) => setHours(Number(event.target.value))} /></label></div><fieldset><legend>Tahap produksi</legend>{Object.entries(stageName).map(([stage, label]) => <label className="manager-check" key={stage}><input type="checkbox" checked={stages[stage]} onChange={(event) => setStages((current) => ({ ...current, [stage]: event.target.checked }))} />{label}</label>)}</fieldset><label>Harga manual <small>(kosongkan untuk harga otomatis)</small><input type="number" min="0" value={manual} onChange={(event) => setManual(event.target.value)} /></label><label className="manager-check"><input type="checkbox" checked={active} onChange={(event) => setActive(event.target.checked)} />Tampilkan di katalog</label>{error && <p className="manager-form-error" role="alert">{error}</p>}<footer><Button variant="text" onClick={close}>Batal</Button><Button type="submit" disabled={busy || !Object.values(stages).some(Boolean)}>{busy ? 'Menyimpan...' : 'Simpan Paket'}</Button></footer></form></section></div>
}
function ExpenseModal({ item, close, saved }: { item: Row | null; close: () => void; saved: () => void }) {
  const [categoryName, setCategoryName] = useState('')
  const [categoryBusy, setCategoryBusy] = useState(false)
  const [categoriesLoading, setCategoriesLoading] = useState(true)
  const [categories, setCategories] = useState<Row[]>([])
  const [category, setCategory] = useState(String(item?.expense_category_id ?? ''))
  const [description, setDescription] = useState(String(item?.description ?? ''))
  const [amount, setAmount] = useState(String(item?.amount ?? ''))
  const [date, setDate] = useState(String(item?.spent_on ?? new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Jakarta' })))
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  useEffect(() => { let active = true; void (async () => {
    const entries: Row[] = []; let page = 1; let last = 1
    do { const result = await managerRequest<Collection>(`expense-categories?active=1&page=${page}`); entries.push(...result.data); last = result.meta?.last_page ?? 1; page++ } while (page <= last)
    if (active) setCategories(entries)
  })().catch((cause: unknown) => { if (active) setError(cause instanceof Error ? cause.message : 'Kategori gagal dimuat.') }).finally(() => { if (active) setCategoriesLoading(false) }); return () => { active = false } }, [])
  async function addCategory() {
    if (!categoryName.trim() || categoryBusy) return
    setCategoryBusy(true); setError('')
    try {
      const result = await managerRequest<{ data: Row }>('expense-categories', 'POST', { name: categoryName.trim(), is_active: true })
      setCategories(current => [...current, result.data].sort((a, b) => String(a.name).localeCompare(String(b.name))))
      setCategory(String(result.data.id)); setCategoryName('')
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Kategori gagal ditambahkan.') }
    finally { setCategoryBusy(false) }
  }
  async function submit(event: FormEvent) {
    event.preventDefault(); setBusy(true); setError('')
    try { await managerRequest(`expenses${item ? `/${item.id}` : ''}`, item ? 'PUT' : 'POST', { expense_category_id: Number(category), description, amount: Number(amount), spent_on: date }); saved() }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Pengeluaran gagal disimpan.') }
    finally { setBusy(false) }
  }
  return <div className="manager-modal-backdrop" onMouseDown={(event) => { if (event.target === event.currentTarget) close() }}><section aria-modal="true" aria-labelledby="manager-expense-title" className="manager-modal" role="dialog"><header><div><span className="manager-modal-kicker">PENGELUARAN</span><h2 id="manager-expense-title">{item ? 'Ubah pengeluaran' : 'Catat pengeluaran'}</h2><p>Catat biaya sesuai tanggal pengeluaran.</p></div><button aria-label="Tutup" className="manager-modal-close" onClick={close} type="button">×</button></header><form onSubmit={(event) => void submit(event)}><label>Kategori<select required value={category} onChange={(event) => setCategory(event.target.value)}><option value="">{categoriesLoading ? 'Memuat kategori…' : 'Pilih kategori'}</option>{categories.map((entry) => <option value={Number(entry.id)} key={String(entry.id)}>{display(entry.name)}</option>)}</select></label>{!categoriesLoading && !categories.length && <p role="status">Belum ada kategori. Tambahkan kategori terlebih dahulu.</p>}<div className="manager-form-pair"><label>Kategori baru<input maxLength={50} value={categoryName} placeholder="Contoh: Listrik dan Air" onChange={event => setCategoryName(event.target.value)} /></label><Button type="button" variant="outlined" disabled={categoryBusy || busy || !categoryName.trim()} onClick={() => void addCategory()}>{categoryBusy ? 'Menambahkan…' : 'Tambah Kategori'}</Button></div><label>Keterangan<input required maxLength={255} value={description} onChange={(event) => setDescription(event.target.value)} /></label><div className="manager-form-pair"><label>Jumlah (Rp)<input type="number" min="1" required value={amount} onChange={(event) => setAmount(event.target.value)} /></label><label>Tanggal<input type="date" required value={date} onChange={(event) => setDate(event.target.value)} /></label></div>{error && <p className="manager-form-error" role="alert">{error}</p>}<footer><Button variant="text" onClick={close}>Batal</Button><Button type="submit" disabled={busy || categoryBusy || categoriesLoading || !category}>{busy ? 'Menyimpan…' : 'Simpan Pengeluaran'}</Button></footer></form></section></div>
}
function SettingModal({ item, close, saved }: { item: Row; close: () => void; saved: () => void }) {
  const [entry, setEntry] = useState(typeof item.value === 'string' ? item.value : JSON.stringify(item.value))
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  async function submit(event: FormEvent) {
    event.preventDefault(); setBusy(true); setError('')
    try { await managerRequest(`settings/${encodeURIComponent(String(item.key))}`, 'PUT', { value: entry }); saved() }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Pengaturan gagal disimpan.') }
    finally { setBusy(false) }
  }
  return <div className="manager-modal-backdrop" onMouseDown={(event) => { if (event.target === event.currentTarget) close() }}><section aria-modal="true" aria-labelledby="manager-setting-title" className="manager-modal" role="dialog"><header><div><span className="manager-modal-kicker">PENGATURAN STUDIO</span><h2 id="manager-setting-title">{display(item.key)}</h2><p>{display(item.description)}</p></div><button aria-label="Tutup" className="manager-modal-close" onClick={close} type="button">×</button></header><form onSubmit={(event) => void submit(event)}><label>Nilai<input required value={entry} onChange={(event) => setEntry(event.target.value)} /></label>{error && <p className="manager-form-error" role="alert">{error}</p>}<footer><Button variant="text" onClick={close}>Batal</Button><Button type="submit" disabled={busy}>Simpan Perubahan</Button></footer></form></section></div>
}
function InventoryModal({ item, close, saved }: { item: Row | null; close: () => void; saved: () => void }) {
  const [categories, setCategories] = useState<Row[]>([])
  const [code, setCode] = useState(String(item?.code ?? ''))
  const [name, setName] = useState(String(item?.name ?? ''))
  const [category, setCategory] = useState(String(item?.inventory_category_id ?? ''))
  const [brand, setBrand] = useState(String(item?.brand ?? ''))
  const [model, setModel] = useState(String(item?.model ?? ''))
  const [date, setDate] = useState(String(item?.purchase_date ?? new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Jakarta' })))
  const [price, setPrice] = useState(String(item?.purchase_price ?? ''))
  const [residual, setResidual] = useState(String(item?.residual_value ?? '0'))
  const [months, setMonths] = useState(String(item?.useful_life_months ?? '60'))
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [categoriesLoading, setCategoriesLoading] = useState(true)
  const [categoryName, setCategoryName] = useState('')
  const [categoryBusy, setCategoryBusy] = useState(false)
  useEffect(() => { let active = true; void (async () => {
    const entries: Row[] = []; let page = 1; let last = 1
    do { const result = await managerRequest<Collection>(`inventory-categories?active=1&page=${page}`); entries.push(...result.data); last = result.meta?.last_page ?? 1; page++ } while (page <= last)
    if (active) setCategories(entries)
  })().catch((cause: unknown) => { if (active) setError(cause instanceof Error ? cause.message : 'Kategori gagal dimuat.') }).finally(() => { if (active) setCategoriesLoading(false) }); return () => { active = false } }, [])
  async function addCategory() {
    if (!categoryName.trim() || categoryBusy) return
    setCategoryBusy(true); setError('')
    try {
      const result = await managerRequest<{ data: Row }>('inventory-categories', 'POST', { name: categoryName.trim(), is_active: true })
      setCategories(current => [...current, result.data].sort((a, b) => String(a.name).localeCompare(String(b.name))))
      setCategory(String(result.data.id)); setCategoryName('')
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Kategori gagal ditambahkan.') }
    finally { setCategoryBusy(false) }
  }
  async function submit(event: FormEvent) {
    event.preventDefault(); setBusy(true); setError('')
    try { await managerRequest(`inventories${item ? `/${item.id}` : ''}`, item ? 'PUT' : 'POST', { code, name, inventory_category_id: Number(category), brand, model, purchase_date: date, purchase_price: Number(price), residual_value: Number(residual), useful_life_months: Number(months) }); saved() }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Inventaris gagal disimpan.') }
    finally { setBusy(false) }
  }
  return <div className="manager-modal-backdrop" onMouseDown={(event) => { if (event.target === event.currentTarget) close() }}><section aria-modal="true" aria-labelledby="manager-inventory-title" className="manager-modal" role="dialog"><header><div><span className="manager-modal-kicker">INVENTARIS STUDIO</span><h2 id="manager-inventory-title">{item ? 'Ubah inventaris' : 'Tambah inventaris'}</h2><p>Data peralatan dan harga pembeliannya.</p></div><button aria-label="Tutup" className="manager-modal-close" onClick={close} type="button">×</button></header><form onSubmit={(event) => void submit(event)}><div className="manager-form-pair"><label>Kode<input required maxLength={30} value={code} onChange={(event) => setCode(event.target.value)} /></label><label>Nama alat<input required maxLength={100} value={name} onChange={(event) => setName(event.target.value)} /></label></div><label>Kategori<select required value={category} onChange={(event) => setCategory(event.target.value)}><option value="">{categoriesLoading ? 'Memuat kategori…' : 'Pilih kategori'}</option>{categories.map((entry) => <option key={String(entry.id)} value={Number(entry.id)}>{display(entry.name)}</option>)}</select></label>{!categoriesLoading && !categories.length && <p role="status">Belum ada kategori. Tambahkan kategori terlebih dahulu.</p>}<div className="manager-form-pair"><label>Kategori baru<input maxLength={50} value={categoryName} placeholder="Contoh: Mikrofon" onChange={event => setCategoryName(event.target.value)} /></label><Button type="button" variant="outlined" disabled={categoryBusy || busy || !categoryName.trim()} onClick={() => void addCategory()}>{categoryBusy ? 'Menambahkan…' : 'Tambah Kategori'}</Button></div><div className="manager-form-pair"><label>Merek<input value={brand} onChange={(event) => setBrand(event.target.value)} /></label><label>Model<input value={model} onChange={(event) => setModel(event.target.value)} /></label></div><div className="manager-form-pair"><label>Tanggal beli<input type="date" required value={date} onChange={(event) => setDate(event.target.value)} /></label><label>Harga beli<input type="number" min="0" required value={price} onChange={(event) => setPrice(event.target.value)} /></label></div><div className="manager-form-pair"><label>Nilai sisa<input type="number" min="0" required value={residual} onChange={(event) => setResidual(event.target.value)} /></label><label>Umur pakai (bulan)<input type="number" min="1" required value={months} onChange={(event) => setMonths(event.target.value)} /></label></div>{error && <p className="manager-form-error" role="alert">{error}</p>}<footer><Button variant="text" onClick={close}>Batal</Button><Button type="submit" disabled={busy || categoryBusy || categoriesLoading || !category}>Simpan Inventaris</Button></footer></form></section></div>
}
function CampaignModal({ close, saved }: { close: () => void; saved: () => void }) {
  const [clients, setClients] = useState<Row[]>([])
  const [selected, setSelected] = useState<number[]>([])
  const [name, setName] = useState('')
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  useEffect(() => { void managerRequest<Collection>('clients').then((result) => setClients(result.data)).catch((cause: unknown) => setError(cause instanceof Error ? cause.message : 'Klien gagal dimuat.')) }, [])
  async function submit(event: FormEvent) {
    event.preventDefault(); setBusy(true); setError('')
    try { await managerRequest('crm/campaigns', 'POST', { name, message, channels: ['email'], client_ids: selected }); saved() }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Kampanye gagal disimpan.') }
    finally { setBusy(false) }
  }
  return <div className="manager-modal-backdrop" onMouseDown={(event) => { if (event.target === event.currentTarget) close() }}><section aria-modal="true" aria-labelledby="manager-campaign-title" className="manager-modal" role="dialog"><header><div><span className="manager-modal-kicker">CRM</span><h2 id="manager-campaign-title">Kampanye baru</h2><p>Siapkan email dan pilih klien penerima.</p></div><button aria-label="Tutup" className="manager-modal-close" onClick={close} type="button">×</button></header><form onSubmit={(event) => void submit(event)}><label>Nama kampanye<input required maxLength={150} value={name} onChange={(event) => setName(event.target.value)} /></label><label>Pesan email<textarea required maxLength={5000} rows={5} value={message} onChange={(event) => setMessage(event.target.value)} /></label><fieldset className="manager-client-choices"><legend>Klien penerima ({selected.length})</legend>{clients.map((client) => <label className="manager-check" key={String(client.id)}><input type="checkbox" checked={selected.includes(Number(client.id))} onChange={(event) => setSelected((current) => event.target.checked ? [...current, Number(client.id)] : current.filter((id) => id !== Number(client.id)))} />{display(client.name)}</label>)}</fieldset>{error && <p className="manager-form-error" role="alert">{error}</p>}<footer><Button variant="text" onClick={close}>Batal</Button><Button type="submit" disabled={busy || selected.length === 0}>Simpan Kampanye</Button></footer></form></section></div>
}
function PaymentConfirmModal({ payment, close, saved }: { payment: Row; close: () => void; saved: () => void }) {
  const [note, setNote] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  async function submit(event: FormEvent) {
    event.preventDefault(); setBusy(true); setError('')
    try { await managerRequest(`payments/${payment.id}/confirm`, 'POST', { note }); saved() }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Konfirmasi gagal.') }
    finally { setBusy(false) }
  }
  return <div className="manager-modal-backdrop" onMouseDown={(event) => { if (event.target === event.currentTarget) close() }}><section aria-modal="true" aria-labelledby="manager-payment-title" className="manager-modal" role="dialog"><header><div><span className="manager-modal-kicker">PEMBAYARAN MANUAL</span><h2 id="manager-payment-title">Konfirmasi pembayaran</h2><p>{display(payment.order_id)} - {money(payment.amount)}</p></div><button aria-label="Tutup" className="manager-modal-close" onClick={close} type="button">×</button></header><form onSubmit={(event) => void submit(event)}><p className="manager-modal-hint">Pastikan dana sudah diterima sebelum mengonfirmasi.</p><label>Catatan verifikasi<textarea required maxLength={500} rows={3} value={note} onChange={(event) => setNote(event.target.value)} /></label>{error && <p className="manager-form-error" role="alert">{error}</p>}<footer><Button variant="text" onClick={close}>Batal</Button><Button type="submit" disabled={busy}>Konfirmasi</Button></footer></form></section></div>
}
function OperatorAccountModal({ item, close, saved }: { item: Row | null; close: () => void; saved: () => void }) {
  const [name, setName] = useState(String(item?.name ?? ''))
  const [email, setEmail] = useState(String(item?.email ?? ''))
  const [phone, setPhone] = useState(String(item?.phone ?? ''))
  const [password, setPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const [active, setActive] = useState(Boolean(item?.is_active ?? true))
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  async function submit(event: FormEvent) {
    event.preventDefault()
    if (password !== confirmation) { setError('Konfirmasi kata sandi tidak cocok.'); return }
    setBusy(true); setError('')
    try {
      await managerRequest(`operator-accounts${item ? `/${item.id}` : ''}`, item ? 'PUT' : 'POST', {
        name, email, phone, ...(password ? { password, password_confirmation: confirmation } : {}), ...(item ? { is_active: active } : {}),
      })
      saved()
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Akun operator gagal disimpan.') }
    finally { setBusy(false) }
  }
  return <div className="manager-modal-backdrop" onMouseDown={(event) => { if (event.target === event.currentTarget) close() }}><section aria-modal="true" aria-labelledby="operator-account-title" className="manager-modal" role="dialog"><header><div><span className="manager-modal-kicker">AKUN STUDIO</span><h2 id="operator-account-title">{item ? 'Edit operator' : 'Tambah operator'}</h2><p>{item ? 'Perbarui akun dan akses kerja.' : 'Buat akun baru untuk tim produksi.'}</p></div><button aria-label="Tutup" className="manager-modal-close" onClick={close} type="button">&times;</button></header><form onSubmit={(event) => void submit(event)}><label>Nama<input required maxLength={255} value={name} onChange={(event) => setName(event.target.value)} /></label><label>Email<input type="email" required value={email} onChange={(event) => setEmail(event.target.value)} /></label><label>Nomor telepon<input type="tel" required placeholder="081234567890" value={phone} onChange={(event) => setPhone(event.target.value)} /></label><div className="manager-form-pair"><label>{item ? 'Password baru (opsional)' : 'Password'}<input type="password" minLength={8} required={!item} value={password} onChange={(event) => setPassword(event.target.value)} /></label><label>Ulangi password<input type="password" minLength={8} required={Boolean(password)} value={confirmation} onChange={(event) => setConfirmation(event.target.value)} /></label></div>{item && <label className="manager-check"><input type="checkbox" checked={active} onChange={(event) => setActive(event.target.checked)} />Akun aktif</label>}{error && <p className="manager-form-error" role="alert">{error}</p>}<footer><Button variant="text" onClick={close}>Batal</Button><Button type="submit" disabled={busy}>{busy ? 'Menyimpan...' : 'Simpan Operator'}</Button></footer></form></section></div>
}
function ProjectStagesModal({ project, close, saved }: { project: Row; close: () => void; saved: () => void }) {
  const [review, setReview] = useState<{ progressId: number; submissionId: number; title: string } | null>(null)
  const [decision, setDecision] = useState<'approve' | 'request_revision'>('approve')
  const [notes, setNotes] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const stages = (project.stages as Row[]) ?? []
  async function submit(event: FormEvent) {
    event.preventDefault(); if (!review) return
    setBusy(true); setError('')
    try { await managerRequest(`track-progress/${review.progressId}/submissions/${review.submissionId}/review`, 'POST', { decision, review_notes: decision === 'request_revision' ? notes : null }); saved() }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Review gagal disimpan.') }
    finally { setBusy(false) }
  }
  return <div className="manager-modal-backdrop" onMouseDown={(event) => { if (event.target === event.currentTarget) close() }}><section aria-modal="true" aria-labelledby="manager-stages-title" className="manager-modal manager-modal-wide" role="dialog"><header><div><span className="manager-modal-kicker">TAHAP PRODUKSI</span><h2 id="manager-stages-title">{display(project.name)}</h2><p>{display(value(project, 'client.name'))} - {display(project.status_label)}</p></div><button aria-label="Tutup" className="manager-modal-close" onClick={close} type="button">&times;</button></header><div className="manager-stage-detail">{stages.map((stage) => <section key={String(stage.stage)}><h3>{stageName[String(stage.stage)] ?? display(stage.stage)} <small>{display(stage.status)}</small></h3><p>Operator: {display(value(stage, 'operator.name'))}</p>{((stage.tracks as Row[]) ?? []).map((track) => <div className="manager-stage-track" key={String(track.progress_id)}><span><strong>{display(track.title)}</strong><small>{display(track.status)}</small></span>{Boolean(track.result_url) && <a href={String(track.result_url)} target="_blank" rel="noopener noreferrer">Buka Hasil</a>}{track.status === 'in_review' && Boolean(track.submission_id) && <button onClick={() => { setReview({ progressId: Number(track.progress_id), submissionId: Number(track.submission_id), title: String(track.title) }); setError('') }}>Tinjau</button>}</div>)}</section>)}{stages.length === 0 && <p>Belum ada tahap produksi.</p>}{review && <form onSubmit={(event) => void submit(event)}><h3>Tinjau hasil {review.title}</h3><label>Keputusan<select value={decision} onChange={(event) => setDecision(event.target.value as typeof decision)}><option value="approve">Setujui hasil</option><option value="request_revision">Minta revisi</option></select></label>{decision === 'request_revision' && <label>Catatan revisi<textarea required minLength={10} maxLength={5000} rows={3} value={notes} onChange={(event) => setNotes(event.target.value)} /></label>}{error && <p className="manager-form-error" role="alert">{error}</p>}<footer><Button variant="text" onClick={() => setReview(null)}>Batal</Button><Button type="submit" disabled={busy}>{busy ? 'Menyimpan...' : 'Simpan Review'}</Button></footer></form>}</div></section></div>
}
function Reports() {
  const now = new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Jakarta', year: 'numeric', month: 'numeric' }).formatToParts(new Date())
  const [year, setYear] = useState(Number(now.find((part) => part.type === 'year')?.value))
  const [month, setMonth] = useState(Number(now.find((part) => part.type === 'month')?.value))
  const [result, setResult] = useState<Row | null>(null)
  const [error, setError] = useState('')
  useEffect(() => { let active = true; void managerRequest<{ data: Row }>(`finance/monthly-summary?year=${year}&month=${month}`).then((response) => { if (active) { setResult(response.data); setError('') } }).catch((cause: unknown) => { if (active) setError(cause instanceof Error ? cause.message : 'Laporan gagal dimuat.') }); return () => { active = false } }, [year, month])
  return <section className="manager-workspace"><header><div><h2>Laporan Keuangan</h2><p>Ringkasan pendapatan dan pengeluaran bulanan.</p></div><div className="manager-report-period"><select aria-label="Bulan laporan" value={month} onChange={(event) => { setResult(null); setMonth(Number(event.target.value)) }}>{Array.from({ length: 12 }, (_, index) => <option key={index + 1} value={index + 1}>{new Intl.DateTimeFormat('id-ID', { month: 'long' }).format(new Date(2020, index, 1))}</option>)}</select><input aria-label="Tahun laporan" type="number" min="2020" max="9999" value={year} onChange={(event) => { setResult(null); setYear(Number(event.target.value)) }} /></div></header>{error && <p role="alert">{error}</p>}{!result && !error && <p>Memuat laporan...</p>}{result && <><div className="manager-report-grid">{Object.entries((result.totals as Row) ?? {}).map(([key, amount]) => <article key={key}><span>{key.replaceAll('_', ' ')}</span><strong>{money(amount)}</strong></article>)}</div><h3>Pengeluaran per kategori</h3><ul className="manager-report-categories">{((result.expenses_by_category as Row[]) ?? []).map((entry) => <li key={String(entry.category_id)}><span>{display(entry.category_name)}</span><strong>{money(entry.amount)}</strong></li>)}</ul></>}</section>
}
export default function ManagerWorkspace({ section }: { section: Section }) {
  const [page, setPage] = useState(1)
  const [payments, setPayments] = useState(false)
  const [version, setVersion] = useState(0)
  const [search, setSearch] = useState('')
  const [modal, setModal] = useState<'assign' | 'quote' | 'package' | 'expense' | 'setting' | 'inventory' | 'campaign' | 'payment' | 'stages' | 'operator' | null>(null)
  const [selected, setSelected] = useState<Row | null>(null)
  const [actionError, setActionError] = useState('')
  const config = section === 'reports' ? null : section === 'invoice' && payments ? paymentConfig : configs[section]
  const list = useList(config?.path ?? 'projects', page, version)
  if (!config) return <Reports />
  const filtered = list.rows.filter((row) => config.columns.some((column) => display(value(row, column.field)).toLocaleLowerCase('id').includes(search.toLocaleLowerCase('id'))))
  function open(kind: typeof modal, row: Row | null) { setSelected(row); setModal(kind) }
  function saved() { setModal(null); setSelected(null); setVersion((current) => current + 1) }
  async function perform(path: string, method: 'POST' | 'DELETE', body?: unknown) {
    setActionError('')
    try { await managerRequest(path, method, body); setVersion((current) => current + 1) }
    catch (cause) { setActionError(cause instanceof Error ? cause.message : 'Aksi gagal.') }
  }
  return <section className="manager-workspace"><header><div><h2>{config.title}</h2><p>{config.description}</p></div>{section === 'packages' && <Button onClick={() => open('package', null)}>Tambah Paket</Button>}{section === 'expenses' && <Button onClick={() => open('expense', null)}>Catat Pengeluaran</Button>}{section === 'inventory' && <Button onClick={() => open('inventory', null)}>Tambah Inventaris</Button>}{section === 'crm' && <Button onClick={() => open('campaign', null)}>Kampanye Baru</Button>}{section === 'operator' && <Button onClick={() => open('operator', null)}>Tambah Operator</Button>}</header>{actionError && <p className="manager-form-error" role="alert">{actionError}</p>}{section === 'invoice' && <div className="manager-section-tabs"><button className={!payments ? 'is-active' : ''} onClick={() => { setPayments(false); setPage(1) }}>Invoice</button><button className={payments ? 'is-active' : ''} onClick={() => { setPayments(true); setPage(1) }}>Pembayaran</button></div>}<div className="manager-table-toolbar"><label>Cari di halaman<input type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder={`Cari ${config.title.toLowerCase()}...`} /></label><span>{list.meta?.total ?? list.rows.length} data</span></div>{list.loading ? <p className="manager-table-state" role="status">Memuat data...</p> : list.error ? <div className="manager-table-state"><p role="alert">{list.error}</p><Button variant="text" onClick={() => setVersion((current) => current + 1)}>Coba lagi</Button></div> : filtered.length === 0 ? <p className="manager-table-state">{search ? 'Tidak ada data yang cocok.' : `Belum ada data ${config.title.toLowerCase()}.`}</p> : <div className="manager-table-scroll"><table><thead><tr>{config.columns.map((column) => <th key={column.field}>{column.title}</th>)}{(['project', 'quotation', 'packages', 'expenses', 'settings', 'inventory', 'crm', 'booking', 'operator'].includes(section) || (section === 'invoice' && payments)) && <th>Aksi</th>}</tr></thead><tbody>{filtered.map((row, index) => <tr key={String(row.id ?? row.key ?? index)}>{config.columns.map((column) => <td key={column.field}>{column.field === 'is_active' ? (value(row, column.field) ? 'Aktif' : 'Nonaktif') : column.money ? money(value(row, column.field)) : display(value(row, column.field))}</td>)}{section === 'project' && <td><button onClick={() => open('stages', row)}>Lihat Tahap</button>{row.status === 'awaiting_assignment' && <button onClick={() => open('assign', row)}>Pilih Operator</button>}</td>}{section === 'quotation' && <td>{(row.status === 'awaiting_studio' || (row.status === 'awaiting_client' && !value(row, 'latest_offer.id'))) && <button onClick={() => open('quote', row)}>{!value(row, 'latest_offer.id') ? 'Kirim Harga Studio' : 'Tanggapi'}</button>}</td>}{section === 'packages' && <td><button onClick={() => open('package', row)}>Ubah</button></td>}{section === 'expenses' && <td><button onClick={() => open('expense', row)}>Ubah</button></td>}{section === 'settings' && <td><button onClick={() => open('setting', row)}>Ubah</button></td>}{section === 'inventory' && <td><button onClick={() => open('inventory', row)}>Ubah</button></td>}{section === 'crm' && <td>{row.status === 'draft' && <button onClick={() => { if (window.confirm(`Kirim kampanye ${display(row.name)} ke klien yang dipilih?`)) void perform(`crm/campaigns/${row.id}/dispatch`, 'POST', {}) }}>Kirim Email</button>}</td>}{section === 'booking' && <td>{!['cancelled', 'completed'].includes(String(row.status)) && <button onClick={() => { if (window.confirm('Batalkan booking ini?')) void perform(`bookings/${row.id}`, 'DELETE') }}>Batalkan</button>}</td>}{section === 'operator' && <td><button onClick={() => open('operator', row)}>Edit</button></td>}{section === 'invoice' && payments && <td>{row.status === 'pending_manual_confirmation' && <button onClick={() => open('payment', row)}>Konfirmasi</button>}</td>}</tr>)}</tbody></table></div>}{(list.meta?.last_page ?? 1) > 1 && <nav className="manager-table-pages" aria-label="Halaman data"><button disabled={page === 1} onClick={() => setPage(page - 1)}>Sebelumnya</button><span>{page} / {list.meta?.last_page}</span><button disabled={page === list.meta?.last_page} onClick={() => setPage(page + 1)}>Berikutnya</button></nav>}{modal === 'assign' && selected && <AssignOperatorModal project={selected} close={() => setModal(null)} saved={saved} />}{modal === 'quote' && selected && <QuoteModal quote={selected} close={() => setModal(null)} saved={saved} />}{modal === 'package' && <PackageModal item={selected} close={() => setModal(null)} saved={saved} />}{modal === 'expense' && <ExpenseModal item={selected} close={() => setModal(null)} saved={saved} />}{modal === 'setting' && selected && <SettingModal item={selected} close={() => setModal(null)} saved={saved} />}{modal === 'inventory' && <InventoryModal item={selected} close={() => setModal(null)} saved={saved} />}{modal === 'campaign' && <CampaignModal close={() => setModal(null)} saved={saved} />}{modal === 'payment' && selected && <PaymentConfirmModal payment={selected} close={() => setModal(null)} saved={saved} />}{modal === 'stages' && selected && <ProjectStagesModal project={selected} close={() => setModal(null)} saved={saved} />}{modal === 'operator' && <OperatorAccountModal item={selected} close={() => setModal(null)} saved={saved} />}</section>
}
