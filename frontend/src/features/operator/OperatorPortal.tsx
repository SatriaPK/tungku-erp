import { useEffect, useState, type FormEvent } from 'react'
import { Navigate, useNavigate } from 'react-router-dom'
import { Button, Icon, LoadingScreen } from '../../components/ui'
import { getCurrentUser, getApiErrorMessage, logout, type AuthUser } from '../../lib/auth-api'
import tungkuIcon from '../../assets/tungku-icon.svg'
import ProfileDialog from '../auth/ProfileDialog'
import './operator-portal.css'

type Track = { id: number; title: string; progress_id: number; status: string; review_notes: string | null }
type Stage = { id: number; project_id: number; project_name: string; client_name: string; stage: string; status: string; deadline_at: string | null; tracks: Track[] }
type Collection = { data: Stage[]; meta?: { last_page: number } }
type Session = { id: number; room_id: number; room_name: string; client_name: string; start_at: string; end_at: string; status_label: string }
type Room = { id: number; name: string }
type Submission = { id: number; version: number; result_url: string; submitted_at: string; review_decision: string | null; review_notes: string | null }
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
function dateTime(value: string) { return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta' }).format(new Date(value)) }
function HistoryModal({ track, onClose }: { track: Track; onClose: () => void }) {
  const [items, setItems] = useState<Submission[]>([])
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)
  useEffect(() => { let active = true; void operatorRequest<{ data: Submission[] }>(`track-progress/${track.progress_id}/submissions`).then((result) => { if (active) { setItems(result.data); setLoading(false) } }).catch((cause: unknown) => { if (active) { setError(getApiErrorMessage(cause)); setLoading(false) } }); return () => { active = false } }, [track.progress_id])
  return <div className="operator-modal-backdrop" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose() }}><section aria-modal="true" aria-labelledby="operator-history-title" className="operator-modal" role="dialog"><header><div><span>RIWAYAT HASIL</span><h2 id="operator-history-title">{track.title}</h2></div><button aria-label="Tutup" onClick={onClose}>&times;</button></header><div className="operator-history">{loading && <p>Memuat riwayat...</p>}{error && <p role="alert">{error}</p>}{!loading && !items.length && <p>Belum ada hasil yang dikirim.</p>}{items.map((item) => <article key={item.id}><div><strong>Versi {item.version}</strong><small>{dateTime(item.submitted_at)}</small></div><span>{item.review_decision === 'approve' ? 'Disetujui' : item.review_decision === 'request_revision' ? 'Perlu revisi' : 'Menunggu review'}</span><a href={item.result_url} target="_blank" rel="noopener noreferrer">Buka hasil</a>{item.review_notes && <p>Catatan Manager: {item.review_notes}</p>}</article>)}</div></section></div>
}
function OperatorSchedule({ stages, version }: { stages: Stage[]; version: number }) {
  const [sessions, setSessions] = useState<Session[]>([])
  const [rooms, setRooms] = useState<Room[]>([])
  const [error, setError] = useState('')
  const [extensionStage, setExtensionStage] = useState<Stage | null>(null)
  const [room, setRoom] = useState('')
  const [start, setStart] = useState('')
  const [end, setEnd] = useState('')
  const [busy, setBusy] = useState(false)
  const [notice, setNotice] = useState('')
  const [reload, setReload] = useState(0)
  useEffect(() => { let active = true; void Promise.all([operatorRequest<{ data: Session[] }>('sessions'), operatorRequest<{ data: Room[] }>('rooms')]).then(([schedule, locations]) => { if (active) { setSessions(schedule.data); setRooms(locations.data); setError('') } }).catch((cause: unknown) => { if (active) setError(getApiErrorMessage(cause)) }); return () => { active = false } }, [version, reload])
  async function requestExtension(event: FormEvent) {
    event.preventDefault(); if (!extensionStage) return
    setBusy(true); setError(''); setNotice('')
    try {
      await operatorRequest(`stages/${extensionStage.id}/recording-extensions`, { room_id: Number(room), start_at: `${start}:00+07:00`, end_at: `${end}:00+07:00` })
      setExtensionStage(null); setNotice('Sesi tambahan berhasil dibuat.'); setReload((current) => current + 1)
    } catch (cause) { setError(getApiErrorMessage(cause)) }
    finally { setBusy(false) }
  }
  return <section className="operator-schedule"><header><div><h2>Jadwal rekaman</h2><p>Sesi yang ditugaskan kepada Anda.</p></div>{stages.some((stage) => stage.stage === 'recording' && stage.status === 'in_progress') && <Button variant="outlined" onClick={() => setExtensionStage(stages.find((stage) => stage.stage === 'recording' && stage.status === 'in_progress') ?? null)}>Tambah Sesi</Button>}</header>{notice && <p className="operator-success" role="status">{notice}</p>}{error && !extensionStage && <p role="alert">{error}</p>}{sessions.length ? <div className="operator-session-list">{sessions.map((session) => <article key={session.id}><strong>{session.room_name}</strong><span>{session.client_name}</span><time>{dateTime(session.start_at)} – {dateTime(session.end_at)}</time><small>{session.status_label}</small></article>)}</div> : !error && <p className="operator-empty">Belum ada jadwal rekaman.</p>}{extensionStage && <div className="operator-modal-backdrop" onMouseDown={(event) => { if (event.target === event.currentTarget) setExtensionStage(null) }}><section aria-modal="true" aria-labelledby="operator-extension-title" className="operator-modal" role="dialog"><header><div><span>REKAMAN</span><h2 id="operator-extension-title">Tambah sesi</h2></div><button aria-label="Tutup" onClick={() => setExtensionStage(null)}>&times;</button></header><form onSubmit={(event) => void requestExtension(event)}><p>Jadwalkan sesi tambahan untuk {extensionStage.project_name}.</p><label>Ruang<select required value={room} onChange={(event) => setRoom(event.target.value)}><option value="">Pilih ruang</option>{rooms.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label><label>Mulai<input type="datetime-local" required value={start} onChange={(event) => setStart(event.target.value)} /></label><label>Selesai<input type="datetime-local" required value={end} onChange={(event) => setEnd(event.target.value)} /></label>{error && <p role="alert">{error}</p>}<footer><Button variant="text" onClick={() => setExtensionStage(null)}>Batal</Button><Button type="submit" disabled={busy}>{busy ? 'Menyimpan...' : 'Simpan Sesi'}</Button></footer></form></section></div>}</section>
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
  const [historyTrack, setHistoryTrack] = useState<Track | null>(null)
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
  return <div className="operator-portal"><header className="operator-header"><a href="/operator"><img src={tungkuIcon} alt="" /><strong>Tungku Studio</strong><span>Portal Operator</span></a><div><span>{user.name}</span><button onClick={() => setEditingProfile(true)}><Icon name="user" size={18} />Edit Profil</button><button onClick={() => void leave()}><Icon name="logout" size={18} />Logout</button></div></header><main><div className="operator-heading"><div><span>TUGAS SAYA</span><h1>Pekerjaan produksi</h1><p>Lihat tahap yang ditugaskan, tetapkan tenggat, dan kirim hasil per lagu.</p></div><strong>{stages.length} tahap</strong></div>{loading ? <p role="status">Memuat pekerjaan...</p> : error ? <div role="alert">{error}<Button variant="text" onClick={() => { setError(''); setLoading(true); setVersion((current) => current + 1) }}>Coba lagi</Button></div> : stages.length === 0 ? <div className="operator-empty">Belum ada tahap produksi yang ditugaskan kepada Anda.</div> : <div className="operator-stage-grid">{stages.map((stage) => <article key={stage.id} className="operator-stage-card"><div className="operator-stage-top"><span>{names[stage.stage] ?? stage.stage}</span><small>{stage.status.replaceAll('_', ' ')}</small></div><h2>{stage.project_name}</h2><p>{stage.client_name}</p><div className="operator-deadline"><Icon name="calendar" size={17} />{stage.deadline_at ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta' }).format(new Date(stage.deadline_at)) : 'Tenggat belum ditetapkan'}</div>{stage.stage !== 'recording' && stage.status === 'ready' && !stage.deadline_at && <Button variant="outlined" onClick={() => { setModal({ type: 'deadline', stage }); setActionError('') }}>Tetapkan Tenggat</Button>}<div className="operator-tracks"><h3>Lagu dalam tahap ini</h3>{stage.tracks.length ? stage.tracks.map((track) => <div key={track.progress_id}><span><strong>{track.title}</strong><small>{track.status.replaceAll('_', ' ')}</small>{track.review_notes && <em>Catatan: {track.review_notes}</em>}</span><Button variant="text" onClick={() => setHistoryTrack(track)}>Riwayat</Button><Button variant="text" disabled={stage.status === 'locked' || !['pending', 'revision'].includes(track.status)} onClick={() => { setModal({ type: 'result', stage, track }); setUrl(''); setActionError('') }}>Kirim Hasil</Button></div>) : <p>Belum ada lagu pada tahap ini.</p>}</div></article>)}</div>}{lastPage > 1 && <nav className="operator-pager" aria-label="Halaman tugas"><button disabled={page <= 1} onClick={() => setPage(page - 1)}>Sebelumnya</button><span>{page} / {lastPage}</span><button disabled={page >= lastPage} onClick={() => setPage(page + 1)}>Berikutnya</button></nav>}<OperatorSchedule stages={stages} version={version} /></main>{historyTrack && <HistoryModal track={historyTrack} onClose={() => setHistoryTrack(null)} />}{editingProfile && <ProfileDialog user={user} onClose={() => setEditingProfile(false)} onSaved={(saved) => { onUserChange(saved); setEditingProfile(false) }} />}{modal && <div className="operator-modal-backdrop" onMouseDown={(event) => { if (event.currentTarget === event.target) setModal(null) }}><section aria-modal="true" role="dialog" aria-labelledby="operator-modal-title" className="operator-modal"><header><div><span>{modal.stage.project_name} - {names[modal.stage.stage] ?? modal.stage.stage}</span><h2 id="operator-modal-title">{modal.type === 'deadline' ? 'Tetapkan tenggat' : `Kirim hasil ${modal.track?.title}`}</h2></div><button aria-label="Tutup" onClick={() => setModal(null)}>&times;</button></header><form onSubmit={(event) => void submit(event)}>{modal.type === 'deadline' ? <label>Tanggal dan waktu selesai<input type="datetime-local" required value={date} onChange={(event) => setDate(event.target.value)} /></label> : <label>Link hasil kerja<input type="url" required placeholder="https://..." value={url} onChange={(event) => setUrl(event.target.value)} /><small>Gunakan link yang bisa dibuka Manager untuk meninjau hasil.</small></label>}{actionError && <p role="alert">{actionError}</p>}<footer><Button variant="text" onClick={() => setModal(null)}>Batal</Button><Button type="submit" disabled={busy}>{busy ? 'Menyimpan...' : modal.type === 'deadline' ? 'Simpan Tenggat' : 'Kirim ke Manager'}</Button></footer></form></section></div>}</div>
}
export default function OperatorPortal() {
  const [user, setUser] = useState<AuthUser | null>(null)
  const [state, setState] = useState<'loading' | 'ready' | 'guest'>('loading')
  useEffect(() => { let active = true; void getCurrentUser().then((current) => { if (active) { setUser(current); setState('ready') } }).catch(() => { if (active) setState('guest') }); return () => { active = false } }, [])
  if (state === 'loading') return <LoadingScreen label="Memeriksa sesi Operator" />
  if (state === 'guest') return <Navigate to="/staff/login" replace />
  if (user?.role !== 'operator') return <main className="operator-loading">Halaman ini untuk akun Operator.</main>
  return <OperatorWorkspace user={user} onUserChange={setUser} />
}
