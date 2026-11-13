import { useRef, useState } from 'react'
import { Button, Input } from '../../components/ui'
import { getApiErrorMessage } from '../../lib/auth-api'
import { request, rupiah, type ClientPackage, type Specifications, type Stage } from './client-api'

type Match = { package: ClientPackage; score: number; within_budget: boolean; reasons: string[] }
const stageNames: Record<Stage, string> = { recording: 'Recording', editing: 'Editing', mixing: 'Mixing', mastering: 'Mastering' }

export default function PackageRecommendations({ spec, onDetails }: { spec: Specifications; onDetails: (item: ClientPackage) => void }) {
  const [budget, setBudget] = useState('')
  const [results, setResults] = useState<Match[] | null>(null)
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [lastInput, setLastInput] = useState('')
  const requestId = useRef(0)
  const selectedStages = (Object.keys(stageNames) as Stage[]).filter((stage) => spec[`include_${stage}`])
  const signature = JSON.stringify({ spec, budget })
  const stale = lastInput !== signature
  const validBudget = budget === '' || (Number.isSafeInteger(Number(budget)) && Number(budget) > 0 && Number(budget) <= 2147483647)

  async function recommend() {
    const id = ++requestId.current
    setBusy(true); setError(''); setResults(null); setLastInput(signature)
    const params = new URLSearchParams({ track_count: String(spec.track_count), recording_hours: String(spec.recording_hours) })
    selectedStages.forEach((stage) => params.append('stages[]', stage))
    if (budget) params.set('budget', budget)
    try {
      const response = await request<{ data: Match[]; meta: { message: string } }>(`package-recommendations?${params}`)
      if (id === requestId.current) { setResults(response.data); setMessage(response.meta.message) }
    } catch (failure) { if (id === requestId.current) setError(getApiErrorMessage(failure)) }
    finally { if (id === requestId.current) setBusy(false) }
  }

  return <section className="client-recommendations" aria-labelledby="recommendations-heading">
    <h2 id="recommendations-heading">Rekomendasi Paket</h2>
    <p>Rekomendasi menggunakan katalog studio sesuai tahap, jumlah lagu, durasi, dan anggaran Anda. Paket demo tidak disertakan. Skor menunjukkan kemiripan kebutuhan, bukan jaminan hasil produksi.</p>
    <p>Kebutuhan: {spec.track_count} lagu ? {spec.include_recording ? spec.recording_hours : 0} jam rekaman ? {selectedStages.map((stage) => stageNames[stage]).join(', ') || 'Belum ada tahap dipilih'}.</p>
    <Input label="Anggaran Maksimal (Rp, opsional)" type="number" min={1} max={2147483647} step={1} value={budget} onChange={(event) => setBudget(event.target.value)} />
    {!validBudget && <p role="alert">Anggaran harus berupa bilangan bulat positif maksimal Rp 2.147.483.647.</p>}
    <Button disabled={busy || !validBudget || !selectedStages.length} onClick={() => { void recommend() }}>{busy ? 'Mencari paket?' : 'Cari Rekomendasi'}</Button>
    {!stale && error && <p role="alert">{error}</p>}
    {!stale && results && <div aria-live="polite"><p>{message}</p><div className="client-recommendation-grid">{results.map((match) => <article key={match.package.id}>
      <h3>{match.package.name}</h3><strong>{rupiah(match.package.price)}</strong><p>Kemiripan {match.score}/100 ? {!budget ? 'Tanpa batas anggaran' : match.within_budget ? 'Sesuai anggaran' : 'Melebihi anggaran'}</p>
      <ul>{match.reasons.map((reason) => <li key={reason}>{reason}</li>)}</ul>
      <Button variant="outlined" onClick={() => onDetails(match.package)}>Detail {match.package.name}</Button>
    </article>)}</div></div>}
    {stale && results && <p role="status">Kebutuhan berubah. Cari ulang untuk memperbarui rekomendasi.</p>}
  </section>
}
