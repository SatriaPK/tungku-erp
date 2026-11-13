import { useEffect, useRef, useState, type FormEvent } from 'react'
import { Badge, Button, Input, SegmentedButton } from '../../../components/ui'
import './ManagerCouponsPage.css'

type Coupon = { id: string; code: string; name: string; type: 'percent' | 'fixed'; value: number; minimum: number; maximum: number; start: string; end: string; limit: number; used?: number; enabled: boolean }
const apiOrigin = import.meta.env.VITE_API_ORIGIN ?? 'http://localhost:8000'
async function couponRequest(path = '', coupon?: Coupon): Promise<Coupon[] | Coupon> {
  if (coupon) {
    const session = await fetch(`${apiOrigin}/sanctum/csrf-cookie`, { credentials: 'include', headers: { Accept: 'application/json' } })
    if (!session.ok) throw new Error('Sesi tidak dapat diperbarui. Silakan masuk kembali.')
  }
  const token = document.cookie.split('; ').find(item => item.startsWith('XSRF-TOKEN='))?.slice(11)
  const response = await fetch(`${apiOrigin}/api/v1/manager/coupons${path}`, { method: coupon ? (path ? 'PUT' : 'POST') : 'GET', credentials: 'include', headers: { Accept: 'application/json', ...(coupon ? { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': token ? decodeURIComponent(token) : '' } : {}) }, ...(coupon ? { body: JSON.stringify(coupon) } : {}) })
  const result = await response.json().catch(() => null)
  if (!response.ok) throw new Error(result?.errors ? String(Object.values(result.errors).flat()[0]) : result?.message ?? 'Kupon gagal dimuat. Coba lagi.')
  return result.data
}
const rupiah = (value: number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value)
const today = () => { const date = new Date(); return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}` }
const status = (coupon: Coupon) => !coupon.enabled ? 'Nonaktif' : coupon.end < today() ? 'Kedaluwarsa' : coupon.start > today() ? 'Terjadwal' : (coupon.used ?? 0) >= coupon.limit ? 'Kuota Habis' : 'Aktif'
const emptyCoupon = (): Coupon => ({ id: crypto.randomUUID(), code: '', name: '', type: 'percent', value: 10, minimum: 0, maximum: 0, start: today(), end: '', limit: 100, enabled: true })
export default function ManagerCouponsPage() {
  const [coupons, setCoupons] = useState<Coupon[]>([])
  const [busy, setBusy] = useState(false)
  const [fetching, setFetching] = useState(true)
  const [loadError, setLoadError] = useState('')
  async function load() { setFetching(true); setLoadError(''); try { setCoupons(await couponRequest() as Coupon[]) } catch (failure) { setLoadError(failure instanceof Error ? failure.message : 'Kupon gagal dimuat.') } finally { setFetching(false) } }
  useEffect(() => {
    let active = true
    void couponRequest().then(result => { if (active) setCoupons(result as Coupon[]) }).catch(failure => { if (active) setLoadError(failure instanceof Error ? failure.message : 'Kupon gagal dimuat.') }).finally(() => { if (active) setFetching(false) })
    return () => { active = false }
  }, [])
  const [query, setQuery] = useState('')
  const [filter, setFilter] = useState('Semua')
  const [draft, setDraft] = useState<Coupon | null>(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const dialog = useRef<HTMLDialogElement>(null)
  const trigger = useRef<HTMLButtonElement | null>(null)
  async function persist(coupon: Coupon) {
    setBusy(true)
    try {
      const saved = await couponRequest(coupons.some(item => item.id === coupon.id) ? `/${coupon.id}` : '', coupon) as Coupon
      setCoupons(current => current.some(item => item.id === saved.id) ? current.map(item => item.id === saved.id ? saved : item) : [saved, ...current])
      return true
    } catch (failure) { const message = failure instanceof Error ? failure.message : 'Kupon gagal disimpan.'; setError(message); setNotice(message); return false }
    finally { setBusy(false) }
  }
  function open(coupon: Coupon, button: HTMLButtonElement) { trigger.current = button; setDraft({ ...coupon }); setError(''); dialog.current?.showModal() }
  function close() { if (busy) return; dialog.current?.close(); setDraft(null); trigger.current?.focus() }
  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!draft) return
    const next = { ...draft, code: draft.code.trim().toUpperCase(), name: draft.name.trim() }
    if (!next.name || !/^[A-Z0-9_-]{3,24}$/.test(next.code)) { setError('Isi nama dan kode 3–24 karakter berupa huruf, angka, tanda hubung, atau garis bawah.'); return }
    if (coupons.some(item => item.id !== next.id && item.code === next.code)) { setError('Kode kupon sudah digunakan. Gunakan kode lain.'); return }
    if (next.end < next.start) { setError('Tanggal berakhir harus sama atau setelah tanggal mulai.'); return }
    if (next.value <= 0 || (next.type === 'percent' && next.value > 100) || next.minimum < 0 || next.maximum < 0 || next.limit < 1) { setError('Periksa nilai diskon dan batas penggunaan. Persentase maksimal 100%.'); return }
    if (await persist(next)) { setNotice(`Kupon ${next.code} berhasil disimpan.`); close() }
  }
  const visible = coupons.filter(item => `${item.code} ${item.name}`.toLowerCase().includes(query.toLowerCase().trim()) && (filter === 'Semua' || status(item) === filter))
  return <section className="coupons-page" aria-label="Manajemen kupon diskon">
    <div className="coupons-intro"><div><h2>Kupon Diskon</h2><p>Kelola promo untuk membantu klien memulai karya berikutnya.</p></div><Button leadingIcon="plus" disabled={fetching || !!loadError || busy} onClick={event => open(emptyCoupon(), event.currentTarget)}>Buat Kupon</Button></div>
    <p className="coupons-note">Kupon dapat digunakan klien saat membuat booking. Kuota dihitung saat booking berhasil dibuat; tagihan minimal Rp1.</p>
    <div className="coupons-summary">{[['Total kupon', coupons.length], ['Aktif', coupons.filter(item => status(item) === 'Aktif').length], ['Terjadwal', coupons.filter(item => status(item) === 'Terjadwal').length]].map(([label, value]) => <article key={label}><span>{label}</span><strong>{value}</strong></article>)}</div>
    <p className="coupons-notice" role="status">{notice}</p>
    <div className="coupons-panel"><div className="coupons-toolbar"><Input aria-label="Cari kupon" leadingIcon="search" placeholder="Cari kode atau nama kupon..." value={query} onChange={event => setQuery(event.target.value)} /><span>{visible.length} kupon</span></div>
      <div className="coupons-filters"><SegmentedButton options={['Semua', 'Aktif', 'Terjadwal', 'Nonaktif', 'Kedaluwarsa', 'Kuota Habis']} value={filter} onChange={setFilter} /></div>
      {fetching ? <p role="status">Memuat kupon...</p> : loadError ? <div role="alert"><p>{loadError}</p><Button onClick={() => void load()}>Coba Lagi</Button></div> : visible.length ? <div className="coupons-table-wrap"><table><thead><tr><th>Kupon</th><th>Diskon</th><th>Masa berlaku</th><th>Ketentuan</th><th>Status</th><th>Aksi</th></tr></thead><tbody>{visible.map(coupon => <tr key={coupon.id}><td><strong className="coupon-code">{coupon.code}</strong><small>{coupon.name}</small></td><td><strong>{coupon.type === 'percent' ? `${coupon.value}%` : rupiah(coupon.value)}</strong><small>{coupon.type === 'percent' && coupon.maximum > 0 ? `Maks. ${rupiah(coupon.maximum)}` : 'Tanpa batas potongan tambahan'}</small></td><td>{new Date(`${coupon.start}T00:00:00`).toLocaleDateString('id-ID')}<small>s.d. {new Date(`${coupon.end}T00:00:00`).toLocaleDateString('id-ID')}</small></td><td>Min. {rupiah(coupon.minimum)}<small>{coupon.used ?? 0} / {coupon.limit} penggunaan</small></td><td><Badge tone={status(coupon) === 'Aktif' ? 'success' : status(coupon) === 'Terjadwal' ? 'warning' : 'neutral'}>{status(coupon)}</Badge></td><td><div className="coupon-actions"><Button variant="text" onClick={event => open(coupon, event.currentTarget)} aria-label={`Edit ${coupon.code}`}>Edit</Button><Button variant="outlined" disabled={busy} onClick={async () => { if (await persist({ ...coupon, enabled: !coupon.enabled })) setNotice(`Kupon ${coupon.code} ${coupon.enabled ? 'dinonaktifkan' : 'diaktifkan'}.`) }} aria-label={`${coupon.enabled ? 'Nonaktifkan' : 'Aktifkan'} ${coupon.code}`}>{coupon.enabled ? 'Nonaktifkan' : 'Aktifkan'}</Button></div></td></tr>)}</tbody></table></div> : <div className="coupons-empty"><h3>{coupons.length ? 'Kupon tidak ditemukan' : 'Mulai promo pertamamu'}</h3><p>{coupons.length ? 'Coba kata kunci atau filter status lainnya.' : 'Buat kode kupon dengan diskon persen atau nominal untuk klien studio.'}</p>{!coupons.length && <Button variant="tonal" leadingIcon="plus" disabled={fetching || !!loadError || busy} onClick={event => open(emptyCoupon(), event.currentTarget)}>Buat Kupon Pertama</Button>}</div>}
    </div>
    <dialog ref={dialog} className="coupon-dialog" onCancel={event => { event.preventDefault(); close() }} aria-labelledby="coupon-dialog-title">
      {draft && <form onSubmit={save}><header><div><h2 id="coupon-dialog-title">{coupons.some(item => item.id === draft.id) ? 'Edit Kupon' : 'Buat Kupon'}</h2><p>Tentukan potongan dan syarat penggunaan promo.</p></div><Button variant="text" onClick={close} aria-label="Tutup form kupon">Tutup</Button></header>
        <div className="coupon-form-grid">
          <label>Nama promo<input autoFocus required maxLength={80} value={draft.name} placeholder="Promo rekaman akhir tahun" onChange={e => setDraft({ ...draft, name: e.target.value })} /></label>
          <label>Kode kupon<input required minLength={3} maxLength={24} pattern="[A-Za-z0-9_-]+" value={draft.code} placeholder="REKAMAN10" onChange={e => setDraft({ ...draft, code: e.target.value.toUpperCase() })} /><small>3–24 huruf, angka, tanda hubung, atau garis bawah.</small></label>
          <label>Jenis diskon<select value={draft.type} onChange={e => setDraft({ ...draft, type: e.target.value as Coupon['type'], value: 10 })}><option value="percent">Persentase (%)</option><option value="fixed">Nominal (Rp)</option></select></label>
          <label>Nilai diskon {draft.type === 'percent' ? '(%)' : '(Rp)'}<input required type="number" min={1} max={draft.type === 'percent' ? 100 : undefined} step={1} value={draft.value} onChange={e => setDraft({ ...draft, value: Number(e.target.value) })} /></label>
          <label>Minimum transaksi (Rp)<input required type="number" min={0} step={1} value={draft.minimum} onChange={e => setDraft({ ...draft, minimum: Number(e.target.value) })} /><small>Isi 0 untuk tanpa minimum transaksi.</small></label>
          {draft.type === 'percent' && <label>Maksimum potongan (Rp)<input required type="number" min={0} step={1} value={draft.maximum} onChange={e => setDraft({ ...draft, maximum: Number(e.target.value) })} /><small>Isi 0 untuk tanpa batas potongan.</small></label>}
          <label>Tanggal mulai<input required type="date" value={draft.start} onChange={e => setDraft({ ...draft, start: e.target.value })} /></label>
          <label>Tanggal berakhir<input required type="date" min={draft.start} value={draft.end} onChange={e => setDraft({ ...draft, end: e.target.value })} /></label>
          <label>Kuota penggunaan<input required type="number" min={1} step={1} value={draft.limit} onChange={e => setDraft({ ...draft, limit: Number(e.target.value) })} /></label>
          <label className="coupon-enabled"><input type="checkbox" checked={draft.enabled} onChange={e => setDraft({ ...draft, enabled: e.target.checked })} />Aktifkan kupon</label>
        </div>{error && <p className="coupon-error" role="alert">{error}</p>}<footer><Button variant="outlined" onClick={close}>Batal</Button><Button type="submit" disabled={busy}>{busy ? 'Menyimpan...' : 'Simpan Kupon'}</Button></footer>
      </form>}
    </dialog>
  </section>
}

