import { useEffect, useRef, useState, type FormEvent } from 'react'
import { Badge, Button, Input, SegmentedButton } from '../../../components/ui'
import './ManagerCouponsPage.css'

type Coupon = { id: string; code: string; name: string; type: 'percent' | 'fixed'; value: number; minimum: number; maximum: number; start: string; end: string; limit: number; used?: number; enabled: boolean; kind?: 'general' | 'parent' | 'child' | 'legacy'; parent_id?: string; effective_enabled?: boolean; parent_name?: string; minimum_enabled?: boolean; maximum_enabled?: boolean; validity_enabled?: boolean; quota_enabled?: boolean; package_mode?: 'all' | 'whitelist' | 'blacklist'; package_ids?: number[] }
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
const status = (coupon: Coupon) => !(coupon.effective_enabled ?? coupon.enabled) ? 'Nonaktif' : coupon.validity_enabled && coupon.end < today() ? 'Kedaluwarsa' : coupon.validity_enabled && coupon.start > today() ? 'Terjadwal' : ((coupon.kind === 'child' || coupon.kind === 'legacy') && (coupon.used ?? 0) >= 1) || (coupon.kind === 'general' && coupon.quota_enabled && (coupon.used ?? 0) >= coupon.limit) ? 'Sudah Digunakan' : 'Aktif'
const emptyCoupon = (): Coupon => ({ id: crypto.randomUUID(), code: `TS-${crypto.randomUUID().replaceAll('-', '').slice(0, 12).toUpperCase()}`, name: '', kind: 'general', type: 'percent', value: 10, minimum: 0, maximum: 0, start: today(), end: '2099-12-31', limit: 1, enabled: true, minimum_enabled: false, maximum_enabled: false, validity_enabled: false, quota_enabled: false, package_mode: 'all', package_ids: [] })
export default function ManagerCouponsPage() {
  const [packageOptions, setPackageOptions] = useState<{ id: number; name: string }[]>([])
  const [history, setHistory] = useState<{ id: number; payment_id: number; invoice_id: number; client_id: number; status: string; discount_amount: number; redeemed_at: string | null; coupon?: { code: string }; rule_snapshot?: { code: string } }[]>([])
  const [historyPage, setHistoryPage] = useState(1)
  const [historyLastPage, setHistoryLastPage] = useState(1)
  const [generateCount, setGenerateCount] = useState(1)
  async function extraRequest(path: string, body?: unknown) {
    if (body) {
      const csrf = await fetch(`${apiOrigin}/sanctum/csrf-cookie`, { credentials: 'include' })
      if (!csrf.ok) throw new Error('Sesi tidak dapat diperbarui.')
    }
    const token = document.cookie.split('; ').find(v => v.startsWith('XSRF-TOKEN='))?.slice(11)
    const response = await fetch(`${apiOrigin}/api/v1/manager/${path}`, { method: body ? 'POST' : 'GET', credentials: 'include', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': token ? decodeURIComponent(token) : '' }, body: body ? JSON.stringify(body) : undefined })
    const data = await response.json()
    if (!response.ok) throw new Error(data.errors ? String(Object.values(data.errors).flat()[0]) : data.message ?? 'Permintaan gagal.')
    return data
  }
  useEffect(() => { let active = true; void (async () => {
    const all: { id: number; name: string }[] = []; let page = 1; let last = 1
    do { const result = await extraRequest(`packages?page=${page}`); all.push(...result.data); last = result.meta?.last_page ?? 1; page++ } while (page <= last)
    if (active) setPackageOptions(all)
  })().catch(e => { if (active) setNotice(e.message) }); return () => { active = false } }, [])
  useEffect(() => { let active = true; void extraRequest(`coupon-history?page=${historyPage}`).then(result => { if (active) { setHistory(result.data); setHistoryLastPage(result.last_page) } }).catch(e => { if (active) setNotice(e.message) }); return () => { active = false } }, [historyPage])
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
      setCoupons(current => current.some(item => item.id === saved.id) ? current.map(item => item.id === saved.id ? saved : item) : [saved, ...current]); await load()
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
    if (next.validity_enabled && next.end < next.start) { setError('Tanggal berakhir harus sama atau setelah tanggal mulai.'); return }
    if (next.value <= 0 || (next.type === 'percent' && next.value > 100) || next.minimum < 0 || next.maximum < 0 || next.limit < 1) { setError('Periksa nilai diskon dan batas penggunaan. Persentase maksimal 100%.'); return }
    if (await persist(next)) { setNotice(`Kupon ${next.code} berhasil disimpan.`); close() }
  }
  const visible = coupons.filter(item => `${item.code} ${item.name}`.toLowerCase().includes(query.toLowerCase().trim()) && (filter === 'Semua' || status(item) === filter))
  return <section className="coupons-page" aria-label="Manajemen kupon diskon">
    <div className="coupons-intro"><div><h2>Kupon Diskon</h2><p>Kelola promo untuk membantu klien memulai karya berikutnya.</p></div><Button leadingIcon="plus" disabled={fetching || !!loadError || busy} onClick={event => open(emptyCoupon(), event.currentTarget)}>Buat Kupon</Button></div>
    <p className="coupons-note">Kupon Umum berlaku sekali per klien. Kupon Unik memakai aturan induk dan kode anak sekali pakai. Pemakaian dicatat setelah pembayaran sukses.</p>
    <div className="coupons-summary">{[['Total kupon', coupons.length], ['Aktif', coupons.filter(item => status(item) === 'Aktif').length], ['Terjadwal', coupons.filter(item => status(item) === 'Terjadwal').length]].map(([label, value]) => <article key={label}><span>{label}</span><strong>{value}</strong></article>)}</div>
    <p className="coupons-notice" role="status">{notice}</p>
    <section><h3>Generate kode kupon unik</h3><label>Jumlah kode<input type="number" min={1} max={100} value={generateCount} onChange={e => setGenerateCount(Number(e.target.value))} /></label>{coupons.filter(c => c.kind === 'parent').map(parent => <Button key={parent.id} disabled={busy || !Number.isInteger(generateCount) || generateCount < 1 || generateCount > 100} onClick={async () => { setBusy(true); try { await extraRequest(`coupons/${parent.id}/generate`, { count: generateCount }); await load(); setNotice(`${generateCount} kode untuk ${parent.name} dibuat.`) } catch (e) { setNotice(e instanceof Error ? e.message : 'Generate gagal.') } finally { setBusy(false) } }}>Generate untuk {parent.name}</Button>)}</section>
    <section><h3>Riwayat pemakaian kupon</h3><div className="coupons-table-wrap"><table><thead><tr><th>Kode kupon</th><th>Pembayaran</th><th>Invoice</th><th>Klien</th><th>Status</th><th>Potongan</th><th>Waktu terpakai</th></tr></thead><tbody>{history.map(row => <tr key={row.id}><td>{row.rule_snapshot?.code ?? row.coupon?.code ?? '-'}</td><td>{row.payment_id}</td><td>{row.invoice_id}</td><td>{row.client_id}</td><td>{({ reserved: 'Menunggu pembayaran', redeemed: 'Terpakai', released: 'Dilepas' } as Record<string, string>)[row.status] ?? row.status}</td><td>{rupiah(row.discount_amount)}</td><td>{row.redeemed_at ?? 'Belum terpakai'}</td></tr>)}</tbody></table></div><Button disabled={historyPage <= 1} onClick={() => setHistoryPage(historyPage - 1)}>Sebelumnya</Button><span>{historyPage} / {historyLastPage}</span><Button disabled={historyPage >= historyLastPage} onClick={() => setHistoryPage(historyPage + 1)}>Berikutnya</Button></section>
    <div className="coupons-panel"><div className="coupons-toolbar"><Input aria-label="Cari kupon" leadingIcon="search" placeholder="Cari kode atau nama kupon..." value={query} onChange={event => setQuery(event.target.value)} /><span>{visible.length} kupon</span></div>
      <div className="coupons-filters"><SegmentedButton options={['Semua', 'Aktif', 'Terjadwal', 'Nonaktif', 'Kedaluwarsa', 'Sudah Digunakan']} value={filter} onChange={setFilter} /></div>
      {fetching ? <p role="status">Memuat kupon...</p> : loadError ? <div role="alert"><p>{loadError}</p><Button onClick={() => void load()}>Coba Lagi</Button></div> : visible.length ? <div className="coupons-table-wrap"><table><thead><tr><th>Kupon</th><th>Diskon</th><th>Masa berlaku</th><th>Ketentuan</th><th>Status</th><th>Aksi</th></tr></thead><tbody>{visible.map(coupon => <tr key={coupon.id}><td><strong className="coupon-code">{coupon.code}</strong><small>{coupon.kind === 'general' ? 'Umum' : coupon.kind === 'parent' ? 'Unik — Induk' : coupon.kind === 'child' ? 'Unik — Kode anak' : 'Kode lama'}</small><small>{coupon.name}{coupon.parent_name ? ` — Induk: ${coupon.parent_name}` : ''}</small></td><td><strong>{coupon.type === 'percent' ? `${coupon.value}%` : rupiah(coupon.value)}</strong><small>{coupon.type === 'percent' && coupon.maximum_enabled && coupon.maximum > 0 ? `Maks. ${rupiah(coupon.maximum)}` : 'Tanpa batas potongan tambahan'}</small></td><td>{coupon.validity_enabled ? `${coupon.start} s.d. ${coupon.end}` : 'Tanpa batas tanggal'}</td><td>{coupon.minimum_enabled ? `Min. ${rupiah(coupon.minimum)}` : 'Tanpa minimum'}<small>{coupon.used ?? 0} / {coupon.kind === 'child' || coupon.kind === 'legacy' ? '1' : coupon.kind === 'general' && coupon.quota_enabled ? coupon.limit : 'tanpa batas'} penggunaan</small></td><td><Badge tone={status(coupon) === 'Aktif' ? 'success' : status(coupon) === 'Terjadwal' ? 'warning' : 'neutral'}>{status(coupon)}</Badge></td><td><div className="coupon-actions"><Button variant="text" disabled={busy} onClick={event => { const target = coupon.kind === 'child' ? coupons.find(c => c.id === coupon.parent_id) : coupon; if (target) { open(target, event.currentTarget); if (coupon.kind === 'child') setNotice(`Mengedit aturan induk ${target.code}. Perubahan berlaku untuk semua kode anaknya.`) } }} aria-label={`Edit ${coupon.code}`}>Edit</Button><Button variant="outlined" disabled={busy} onClick={async () => { if (await persist({ ...coupon, enabled: !coupon.enabled })) setNotice(`Kupon ${coupon.code} ${coupon.enabled ? 'dinonaktifkan' : 'diaktifkan'}.`) }} aria-label={`${coupon.enabled ? 'Nonaktifkan' : 'Aktifkan'} ${coupon.code}`}>{coupon.enabled ? 'Nonaktifkan' : 'Aktifkan'}</Button></div></td></tr>)}</tbody></table></div> : <div className="coupons-empty"><h3>{coupons.length ? 'Kupon tidak ditemukan' : 'Mulai promo pertamamu'}</h3><p>{coupons.length ? 'Coba kata kunci atau filter status lainnya.' : 'Buat kode kupon dengan diskon persen atau nominal untuk klien studio.'}</p>{!coupons.length && <Button variant="tonal" leadingIcon="plus" disabled={fetching || !!loadError || busy} onClick={event => open(emptyCoupon(), event.currentTarget)}>Buat Kupon Pertama</Button>}</div>}
    </div>
    <dialog ref={dialog} className="coupon-dialog" onCancel={event => { event.preventDefault(); close() }} aria-labelledby="coupon-dialog-title">
      {draft && <form onSubmit={save}><header><div><h2 id="coupon-dialog-title">{coupons.some(item => item.id === draft.id) ? 'Edit Kupon' : 'Buat Kupon'}</h2><p>Tentukan potongan dan syarat penggunaan promo.</p></div><Button variant="text" onClick={close} aria-label="Tutup form kupon">Tutup</Button></header>
        <div className="coupon-form-grid">
          <label>Tipe kupon<select disabled={coupons.some(c => c.id === draft.id)} value={draft.kind ?? 'general'} onChange={e => setDraft({ ...draft, kind: e.target.value as Coupon['kind'] })}><option value="general">Umum</option><option value="parent">Unik — aturan induk</option>{draft.kind === 'legacy' && <option value="legacy">Kode lama</option>}{draft.kind === 'child' && <option value="child">Kode anak</option>}</select></label>
          <label><input type="checkbox" checked={draft.minimum_enabled ?? false} onChange={e => setDraft({ ...draft, minimum_enabled: e.target.checked })} />Aktifkan minimum transaksi</label>
          {draft.type === 'percent' && <label><input type="checkbox" checked={draft.maximum_enabled ?? false} onChange={e => setDraft({ ...draft, maximum_enabled: e.target.checked })} />Aktifkan maksimum potongan</label>}
          <label><input type="checkbox" checked={draft.validity_enabled ?? false} onChange={e => setDraft({ ...draft, validity_enabled: e.target.checked })} />Batasi masa berlaku</label>
          {(draft.kind ?? 'general') === 'general' && <label><input type="checkbox" checked={draft.quota_enabled ?? false} onChange={e => setDraft({ ...draft, quota_enabled: e.target.checked })} />Batasi kuota total{draft.quota_enabled && <input type="number" min={1} value={draft.limit} onChange={e => setDraft({ ...draft, limit: Number(e.target.value) })} />}</label>}
          <label>Batas paket<select value={draft.package_mode ?? 'all'} onChange={e => setDraft({ ...draft, package_mode: e.target.value as Coupon['package_mode'] })}><option value="all">Semua paket</option><option value="whitelist">Whitelist</option><option value="blacklist">Blacklist</option></select></label>
          {draft.package_mode && draft.package_mode !== 'all' && <fieldset><legend>{draft.package_mode === 'whitelist' ? 'Paket yang diperbolehkan' : 'Paket yang dikecualikan'}</legend>{packageOptions.map(p => <label key={p.id}><input type="checkbox" checked={(draft.package_ids ?? []).includes(p.id)} onChange={e => setDraft({ ...draft, package_ids: e.target.checked ? [...(draft.package_ids ?? []), p.id] : (draft.package_ids ?? []).filter(id => id !== p.id) })} />{p.name}</label>)}</fieldset>}
          <label>Nama promo<input autoFocus required maxLength={80} value={draft.name} placeholder="Promo rekaman akhir tahun" onChange={e => setDraft({ ...draft, name: e.target.value })} /></label>
          <label>Kode kupon<input disabled={(draft.used ?? 0) > 0} required minLength={3} maxLength={24} pattern="[A-Za-z0-9_-]+" value={draft.code} placeholder="REKAMAN10" onChange={e => setDraft({ ...draft, code: e.target.value.toUpperCase() })} /><small>3–24 huruf, angka, tanda hubung, atau garis bawah.</small></label>
          <label>Jenis diskon<select value={draft.type} onChange={e => setDraft({ ...draft, type: e.target.value as Coupon['type'], value: 10 })}><option value="percent">Persentase (%)</option><option value="fixed">Nominal (Rp)</option></select></label>
          <label>Nilai diskon {draft.type === 'percent' ? '(%)' : '(Rp)'}<input required type="number" min={1} max={draft.type === 'percent' ? 100 : undefined} step={1} value={draft.value} onChange={e => setDraft({ ...draft, value: Number(e.target.value) })} /></label>
          <label>Minimum transaksi (Rp)<input disabled={!draft.minimum_enabled} required type="number" min={0} step={1} value={draft.minimum} onChange={e => setDraft({ ...draft, minimum: Number(e.target.value) })} /><small>Isi 0 untuk tanpa minimum transaksi.</small></label>
          {draft.type === 'percent' && <label>Maksimum potongan (Rp)<input disabled={!draft.maximum_enabled} required type="number" min={0} step={1} value={draft.maximum} onChange={e => setDraft({ ...draft, maximum: Number(e.target.value) })} /><small>Isi 0 untuk tanpa batas potongan.</small></label>}
          <label>Tanggal mulai<input disabled={!draft.validity_enabled} required type="date" value={draft.start} onChange={e => setDraft({ ...draft, start: e.target.value })} /></label>
          <label>Tanggal berakhir<input disabled={!draft.validity_enabled} required type="date" min={draft.start} value={draft.end} onChange={e => setDraft({ ...draft, end: e.target.value })} /></label>
          <p>Kupon Umum dapat digunakan sekali oleh masing-masing klien. Untuk kupon Unik, simpan aturan induk lalu generate kode anak dari panel di atas.</p>
          <label className="coupon-enabled"><input type="checkbox" checked={draft.enabled} onChange={e => setDraft({ ...draft, enabled: e.target.checked })} />Aktifkan kupon</label>
        </div>{error && <p className="coupon-error" role="alert">{error}</p>}<footer><Button variant="outlined" onClick={close}>Batal</Button><Button type="submit" disabled={busy}>{busy ? 'Menyimpan...' : 'Simpan Kupon'}</Button></footer>
      </form>}
    </dialog>
  </section>
}

