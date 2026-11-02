import { useEffect, useState, type FormEvent } from 'react'
import { Navigate, useNavigate } from 'react-router-dom'
import { Button, Icon } from '../../components/ui'
import { getCurrentUser, getApiErrorMessage, logout, type AuthUser } from '../../lib/auth-api'
import tungkuIcon from '../../assets/tungku-icon.svg'
import ProfileDialog from '../auth/ProfileDialog'
import './operator-portal.css'

type Track = { id: number; title: string; progress_id: number; status: string; review_notes: string | null }
type Stage = { id: number; project_id: number; project_name: string; client_name: string; stage: string; status: string; deadline_at: string | null; tracks: Track[] }
type Collection = { data: Stage[]; meta?: { last_page: number } }
const origin = import.meta.env.VITE_API_ORIGIN ?? 'http://localhost:8000'
const names: Record<string, string> = { recording: 'Recording', editing: 'Editing', mixing: 'Mixing', mastering: 'Mastering' }
async function operatorRequest<T>(path: string, body?: unknown): Promise<T> {
  if (body !== undefined) {
    const csrf = await fetch(`${origin}/sanctum/csrf-cookie`, { credentials: 'include', headers: { Accept: 'application/json' } })
    if (!csrf.ok) throw new Error('Sesi berakhir. Silakan masuk kembali.')
  }
  const token = document.cookie.split('; ').find((entry) => entry.startsWith('XSRF-TOKEN='))?.slice(11)
  const response = await fetch(`${origin}/api/v1/operator/${path}`, { method: body === undefined ? 'GET' : 'POST', credentials: 'include', headers: { Accept: 'application/json', ...(body === undefined ? {} : { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': token ? decodeURIComponent(token) : '' }) }, ...(body === undefined ? {} : { body: JSON.stringify(body) }) })
  const data = await response.json().catch(() => null)
  if (!response.ok) throw new Error(data?.message ?? 'Permintaan tidak berhasil.')
  return data as T
}
function OperatorWorkspace({ user, onUserChange }: { user: AuthUser; onUserChange: (user: AuthUser) => void }) {
  const navigate = useNavigate()
  const [stages, setStages] = useState<Stage[]>([])
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [version, setVersion] = useState(0)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [modal, setModal] = useState<{ type: 'deadline' | 'result'; stage: Stage; track?: Track } | null>(null)
  const [date, setDate] = useState('')
  const [url, setUrl] = useState('')
  const [busy, setBusy] = useState(false)
  const [actionError, setActionError] = useState('')
  const [editingProfile, setEditingProfile] = useState(false)
  useEffect(() => { let active = true; void operatorRequest<Collection>(`stages?page=${page}`).then((response) => { if (active) { setStages(response.data); setLastPage(response.meta?.last_page ?? 1); setLoading(false) } }).catch((cause: unknown) => { if (active) { setError(getApiErrorMessage(cause)); setLoading(false) } }); return () => { active = false } }, [page, version])
  async function submit(event: FormEvent) {
    event.preventDefault(); if (!modal) return
    setBusy(true); setActionError('')
    try {
      if (modal.type === 'deadline') await operatorRequest(`stages/${modal.stage.id}/confirm-deadline`, { deadline_at: `${date}:00+07:00` })
      else if (modal.track) await operatorRequest(`track-progress/${modal.track.progress_id}/tracks/${modal.track.id}/submissions`, { result_url: url })
      setModal(null); setVersion((current) => current + 1)
    } catch (cause) { setActionError(getApiErrorMessage(cause)) }
    finally { setBusy(false) }
  }
  async function leave() { try { await logout(); navigate('/staff/login', { replace: true }) } catch (cause) { setError(getApiErrorMessage(cause)) } }
  return <div className="operator-portal"><header className="operator-header"><a href="/operator"><img src={tungkuIcon} alt="" /><strong>Tungku Studio</strong><span>Portal Operator</span></a><div><span>{user.name}</span><button onClick={() => setEditingProfile(true)}><Icon name="user" size={18} />Edit Profil</button><button onClick={() => void leave()}><Icon name="logout" size={18} />Logout</button></div></header><main><div className="operator-heading"><div><span>TUGAS SAYA</span><h1>Pekerjaan produksi</h1><p>Lihat tahap yang ditugaskan, tetapkan tenggat, dan kirim hasil per lagu.</p></div><strong>{stages.length} tahap</strong></div>{loading ? <p role="status">Memuat pekerjaan...</p> : error ? <div role="alert">{error}<Button variant="text" onClick={() => { setError(''); setLoading(true); setVersion((current) => current + 1) }}>Coba lagi</Button></div> : stages.length === 0 ? <div className="operator-empty">Belum ada tahap produksi yang ditugaskan kepada Anda.</div> : <div className="operator-stage-grid">{stages.map((stage) => <article key={stage.id} className="operator-stage-card"><div className="operator-stage-top"><span>{names[stage.stage] ?? stage.stage}</span><small>{stage.status.replaceAll('_', ' ')}</small></div><h2>{stage.project_name}</h2><p>{stage.client_name}</p><div className="operator-deadline"><Icon name="calendar" size={17} />{stage.deadline_at ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta' }).format(new Date(stage.deadline_at)) : 'Tenggat belum ditetapkan'}</div>{stage.stage !== 'recording' && stage.status === 'ready' && !stage.deadline_at && <Button variant="outlined" onClick={() => { setModal({ type: 'deadline', stage }); setActionError('') }}>Tetapkan Tenggat</Button>}<div className="operator-tracks"><h3>Lagu dalam tahap ini</h3>{stage.tracks.length ? stage.tracks.map((track) => <div key={track.progress_id}><span><strong>{track.title}</strong><small>{track.status.replaceAll('_', ' ')}</small>{track.review_notes && <em>Catatan: {track.review_notes}</em>}</span><Button variant="text" disabled={stage.status === 'locked' || !['pending', 'revision'].includes(track.status)} onClick={() => { setModal({ type: 'result', stage, track }); setUrl(''); setActionError('') }}>Kirim Hasil</Button></div>) : <p>Belum ada lagu pada tahap ini.</p>}</div></article>)}</div>}{lastPage > 1 && <nav className="operator-pager" aria-label="Halaman tugas"><button disabled={page <= 1} onClick={() => setPage(page - 1)}>Sebelumnya</button><span>{page} / {lastPage}</span><button disabled={page >= lastPage} onClick={() => setPage(page + 1)}>Berikutnya</button></nav>}</main>{editingProfile && <ProfileDialog user={user} onClose={() => setEditingProfile(false)} onSaved={(saved) => { onUserChange(saved); setEditingProfile(false) }} />}{modal && <div className="operator-modal-backdrop" onMouseDown={(event) => { if (event.currentTarget === event.target) setModal(null) }}><section aria-modal="true" role="dialog" aria-labelledby="operator-modal-title" className="operator-modal"><header><div><span>{modal.stage.project_name} ? {names[modal.stage.stage] ?? modal.stage.stage}</span><h2 id="operator-modal-title">{modal.type === 'deadline' ? 'Tetapkan tenggat' : `Kirim hasil ${modal.track?.title}`}</h2></div><button aria-label="Tutup" onClick={() => setModal(null)}>&times;</button></header><form onSubmit={(event) => void submit(event)}>{modal.type === 'deadline' ? <label>Tanggal dan waktu selesai<input type="datetime-local" required value={date} onChange={(event) => setDate(event.target.value)} /></label> : <label>Link hasil kerja<input type="url" required placeholder="https://..." value={url} onChange={(event) => setUrl(event.target.value)} /><small>Gunakan link yang bisa dibuka Manager untuk meninjau hasil.</small></label>}{actionError && <p role="alert">{actionError}</p>}<footer><Button variant="text" onClick={() => setModal(null)}>Batal</Button><Button type="submit" disabled={busy}>{busy ? 'Menyimpan...' : modal.type === 'deadline' ? 'Simpan Tenggat' : 'Kirim ke Manager'}</Button></footer></form></section></div>}</div>
}
export default function OperatorPortal() {
  const [user, setUser] = useState<AuthUser | null>(null)
  const [state, setState] = useState<'loading' | 'ready' | 'guest'>('loading')
  useEffect(() => { let active = true; void getCurrentUser().then((current) => { if (active) { setUser(current); setState('ready') } }).catch(() => { if (active) setState('guest') }); return () => { active = false } }, [])
  if (state === 'loading') return <main role="status" className="operator-loading">Memeriksa sesi Operator...</main>
  if (state === 'guest') return <Navigate to="/staff/login" replace />
  if (user?.role !== 'operator') return <main className="operator-loading">Halaman ini untuk akun Operator.</main>
  return <OperatorWorkspace user={user} onUserChange={setUser} />
}
