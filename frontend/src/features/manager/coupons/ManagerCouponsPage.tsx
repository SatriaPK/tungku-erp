import { useRef, useState, type FormEvent } from 'react'
import { Badge, Button, Input, SegmentedButton } from '../../../components/ui'
import './ManagerCouponsPage.css'

type Coupon = { id: string; code: string; name: string; type: 'percent' | 'fixed'; value: number; minimum: number; maximum: number; start: string; end: string; limit: number; enabled: boolean }
const storageKey = 'tungku.manager.coupons.v1'
const rupiah = (value: number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value)
const today = () => { const date = new Date(); return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}` }
const status = (coupon: Coupon) => !coupon.enabled ? 'Nonaktif' : coupon.end < today() ? 'Kedaluwarsa' : coupon.start > today() ? 'Terjadwal' : 'Aktif'
const emptyCoupon = (): Coupon => ({ id: crypto.randomUUID(), code: '', name: '', type: 'percent', value: 10, minimum: 0, maximum: 0, start: today(), end: '', limit: 100, enabled: true })
function loadCoupons(): Coupon[] {
  try {
    const data: unknown = JSON.parse(localStorage.getItem(storageKey) ?? '[]')
    if (!Array.isArray(data)) return []
    return data.filter((item): item is Coupon => item && typeof item.id === 'string' && typeof item.code === 'string' && typeof item.name === 'string' && ['percent', 'fixed'].includes(item.type) && ['value', 'minimum', 'maximum', 'limit'].every(key => typeof item[key] === 'number' && Number.isFinite(item[key])) && typeof item.start === 'string' && typeof item.end === 'string' && typeof item.enabled === 'boolean')
  } catch { return [] }
}
export default function ManagerCouponsPage() {
  const [coupons, setCoupons] = useState(loadCoupons)
  const [query, setQuery] = useState('')
  const [filter, setFilter] = useState('Semua')
  const [draft, setDraft] = useState<Coupon | null>(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const dialog = useRef<HTMLDialogElement>(null)
  const trigger = useRef<HTMLButtonElement | null>(null)
  function persist(next: Coupon[]) {
    try { localStorage.setItem(storageKey, JSON.stringify(next)); setCoupons(next); return true }
    catch { setNotice('Penyimpanan browser tidak tersedia. Perubahan belum disimpan.'); return false }
  }
  function open(coupon: Coupon, button: HTMLButtonElement) { trigger.current = button; setDraft({ ...coupon }); setError(''); dialog.current?.showModal() }
  function close() { dialog.current?.close(); setDraft(null); trigger.current?.focus() }
  function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!draft) return
    const next = { ...draft, code: draft.code.trim().toUpperCase(), name: draft.name.trim() }
    if (!next.name || !/^[A-Z0-9_-]{3,24}$/.test(next.code)) { setError('Isi nama dan kode 3–24 karakter berupa huruf, angka, tanda hubung, atau garis bawah.'); return }
    if (coupons.some(item => item.id !== next.id && item.code === next.code)) { setError('Kode kupon sudah digunakan. Gunakan kode lain.'); return }
    if (next.end < next.start) { setError('Tanggal berakhir harus sama atau setelah tanggal mulai.'); return }
    if (next.value <= 0 || (next.type === 'percent' && next.value > 100) || next.minimum < 0 || next.maximum < 0 || next.limit < 1) { setError('Periksa nilai diskon dan batas penggunaan. Persentase maksimal 100%.'); return }
    if (persist(coupons.some(item => item.id === next.id) ? coupons.map(item => item.id === next.id ? next : item) : [next, ...coupons])) { setNotice(`Kupon ${next.code} berhasil disimpan di browser ini.`); close() }
  }
  const visible = coupons.filter(item => `${item.code} ${item.name}`.toLowerCase().includes(query.toLowerCase().trim()) && (filter === 'Semua' || status(item) === filter))
  return <section className="coupons-page" aria-label="Manajemen kupon diskon">
    <div className="coupons-intro"><div><h2>Kupon Diskon</h2><p>Kelola promo untuk membantu klien memulai karya berikutnya.</p></div><Button leadingIcon="plus" onClick={event => open(emptyCoupon(), event.currentTarget)}>Buat Kupon</Button></div>
    <p className="coupons-note">Pratinjau frontend · Kupon tersimpan hanya di browser ini. Belum berlaku pada transaksi sampai terhubung ke backend.</p>
    <div className="coupons-summary">{[['Total kupon', coupons.length], ['Aktif', coupons.filter(item => status(item) === 'Aktif').length], ['Terjadwal', coupons.filter(item => status(item) === 'Terjadwal').length]].map(([label, value]) => <article key={label}><span>{label}</span><strong>{value}</strong></article>)}</div>
    <p className="coupons-notice" role="status">{notice}</p>
    <div className="coupons-panel"><div className="coupons-toolbar"><Input aria-label="Cari kupon" leadingIcon="search" placeholder="Cari kode atau nama kupon..." value={query} onChange={event => setQuery(event.target.value)} /><span>{visible.length} kupon</span></div>
      <div className="coupons-filters"><SegmentedButton options={['Semua', 'Aktif', 'Terjadwal', 'Nonaktif', 'Kedaluwarsa']} value={filter} onChange={setFilter} /></div>
      {visible.length ? <div className="coupons-table-wrap"><table><thead><tr><th>Kupon</th><th>Diskon</th><th>Masa berlaku</th><th>Ketentuan</th><th>Status</th><th>Aksi</th></tr></thead><tbody>{visible.map(coupon => <tr key={coupon.id}><td><strong className="coupon-code">{coupon.code}</strong><small>{coupon.name}</small></td><td><strong>{coupon.type === 'percent' ? `${coupon.value}%` : rupiah(coupon.value)}</strong><small>{coupon.type === 'percent' && coupon.maximum > 0 ? `Maks. ${rupiah(coupon.maximum)}` : 'Tanpa batas potongan tambahan'}</small></td><td>{new Date(`${coupon.start}T00:00:00`).toLocaleDateString('id-ID')}<small>s.d. {new Date(`${coupon.end}T00:00:00`).toLocaleDateString('id-ID')}</small></td><td>Min. {rupiah(coupon.minimum)}<small>Kuota {coupon.limit} penggunaan</small></td><td><Badge tone={status(coupon) === 'Aktif' ? 'success' : status(coupon) === 'Terjadwal' ? 'warning' : 'neutral'}>{status(coupon)}</Badge></td><td><div className="coupon-actions"><Button variant="text" onClick={event => open(coupon, event.currentTarget)} aria-label={`Edit ${coupon.code}`}>Edit</Button><Button variant="outlined" onClick={() => { if (persist(coupons.map(item => item.id === coupon.id ? { ...item, enabled: !item.enabled } : item))) setNotice(`Kupon ${coupon.code} ${coupon.enabled ? 'dinonaktifkan' : 'diaktifkan'}.`) }} aria-label={`${coupon.enabled ? 'Nonaktifkan' : 'Aktifkan'} ${coupon.code}`}>{coupon.enabled ? 'Nonaktifkan' : 'Aktifkan'}</Button></div></td></tr>)}</tbody></table></div> : <div className="coupons-empty"><h3>{coupons.length ? 'Kupon tidak ditemukan' : 'Mulai promo pertamamu'}</h3><p>{coupons.length ? 'Coba kata kunci atau filter status lainnya.' : 'Buat kode kupon dengan diskon persen atau nominal untuk klien studio.'}</p>{!coupons.length && <Button variant="tonal" leadingIcon="plus" onClick={event => open(emptyCoupon(), event.currentTarget)}>Buat Kupon Pertama</Button>}</div>}
    </div>
    <dialog ref={dialog} className="coupon-dialog" onCancel={close} aria-labelledby="coupon-dialog-title">
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
        </div>{error && <p className="coupon-error" role="alert">{error}</p>}<footer><Button variant="outlined" onClick={close}>Batal</Button><Button type="submit">Simpan Kupon</Button></footer>
      </form>}
    </dialog>
  </section>
}

