import { useEffect, useMemo, useState } from 'react'
import type { FormEvent } from 'react'
import { createPortal } from 'react-dom'
import { Button, Icon, Input, NumberStepper, Switch, ToggleablePhase } from '../index'
import type { IconName } from '../index'
import type { CreateManagerPackageInput, ManagerBasePrice, ManagerPackage } from '../../../lib/manager-packages-api'
import './PackageCreateModal.css'

const currency = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 })
const number = new Intl.NumberFormat('id-ID')

const phases = [
  { key: 'recording', label: 'Recording', icon: 'microphone', unit: 'jam' },
  { key: 'editing', label: 'Editing', icon: 'scissors', unit: 'track' },
  { key: 'mixing', label: 'Mixing', icon: 'sliders', unit: 'track' },
  { key: 'mastering', label: 'Mastering', icon: 'activity', unit: 'track' },
] as const

type PhaseKey = (typeof phases)[number]['key']
type PackageForm = Omit<CreateManagerPackageInput, 'description' | 'related_info' | 'recording_hours' | 'manual_price'> & { description: string; related_info: string; recording_hours: number; manual_price: string }

const emptyForm: PackageForm = {
  name: '', description: '', related_info: '', track_count: 1, recording_hours: 1,
  include_recording: false, include_editing: false, include_mixing: false, include_mastering: false,
  manual_price: '',
}

export interface PackageCreateModalProps {
  basePrices: ManagerBasePrice[]
  initialPackage?: ManagerPackage
  error?: string
  submitting?: boolean
  onClose: () => void
  onSubmit: (input: CreateManagerPackageInput) => Promise<ManagerPackage>
  onCalculatePrice: (input: Omit<CreateManagerPackageInput, 'name' | 'description' | 'related_info' | 'manual_price'>) => Promise<number>
}

export function PackageCreateModal({ basePrices, initialPackage, error = '', submitting = false, onClose, onSubmit, onCalculatePrice }: PackageCreateModalProps) {
  const [form, setForm] = useState<PackageForm>(() => initialPackage ? { name: initialPackage.name, description: initialPackage.description ?? '', related_info: initialPackage.related_info ?? '', track_count: initialPackage.track_count, recording_hours: Math.max(1, initialPackage.recording_hours || 1), include_recording: initialPackage.include_recording, include_editing: initialPackage.include_editing, include_mixing: initialPackage.include_mixing, include_mastering: initialPackage.include_mastering, manual_price: initialPackage.manual_price === null ? '' : String(initialPackage.manual_price) } : emptyForm)
  const [autoPrice, setAutoPrice] = useState<number | null>(null)
  const [priceError, setPriceError] = useState('')
  const [validationError, setValidationError] = useState('')
  const [manualPriceEnabled, setManualPriceEnabled] = useState(false)
  const [recordingEnabled, setRecordingEnabled] = useState(false)
  const selectedStages = useMemo(() => ({
    include_recording: form.include_recording,
    include_editing: form.include_editing,
    include_mixing: form.include_mixing,
    include_mastering: form.include_mastering,
  }), [form.include_editing, form.include_mastering, form.include_mixing, form.include_recording])

  useEffect(() => {
    const originalOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    const onKeyDown = (event: KeyboardEvent) => { if (event.key === 'Escape' && !submitting) onClose() }
    document.addEventListener('keydown', onKeyDown)
    return () => {
      document.body.style.overflow = originalOverflow
      document.removeEventListener('keydown', onKeyDown)
    }
  }, [onClose, submitting])

  useEffect(() => {
    let active = true
    const calculate = async () => {
      if (!Object.values(selectedStages).some(Boolean)) {
        setAutoPrice(null)
        setPriceError('')
        return
      }
      try {
        const result = await onCalculatePrice({
          track_count: form.track_count,
          recording_hours: form.include_recording ? form.recording_hours : null,
          ...selectedStages,
        })
        if (active) { setAutoPrice(result); setPriceError('') }
      } catch (cause) {
        if (active) { setAutoPrice(null); setPriceError(cause instanceof Error ? cause.message : 'Harga otomatis tidak dapat dihitung.') }
      }
    }
    const timeout = window.setTimeout(() => { void calculate() }, 220)
    return () => { active = false; window.clearTimeout(timeout) }
  }, [form.include_recording, form.recording_hours, form.track_count, onCalculatePrice, selectedStages])

  const stagePrice = (key: PhaseKey) => basePrices.find((price) => price.stage === key)?.price ?? null
  const stagePriceLabel = (key: PhaseKey) => {
    const price = stagePrice(key)
    const phase = phases.find((entry) => entry.key === key)!
    return price === null ? 'Harga belum diatur' : `${currency.format(price)} / ${phase.unit}`
  }
  const toggleStage = (key: PhaseKey, selected: boolean) => {
    const includeKey = `include_${key}` as const
    if (key === 'recording') setRecordingEnabled(selected)
    setForm((current) => ({ ...current, [includeKey]: selected }))
  }

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const anyStageSelected = Object.values(selectedStages).some(Boolean)
    if (!anyStageSelected) { setValidationError('Pilih minimal satu tahap produksi.'); return }
    if (form.include_recording && form.recording_hours < 1) { setValidationError('Durasi Recording minimal 1 jam.'); return }
    setValidationError('')
    await onSubmit({
      name: form.name.trim(), description: form.description.trim() || null, related_info: form.related_info.trim() || null,
      track_count: form.track_count, recording_hours: form.include_recording ? form.recording_hours : null,
      ...selectedStages, manual_price: form.manual_price.trim() ? Number(form.manual_price) : null,
    })
  }

  const dialog = (
    <div className="ts-package-create-backdrop" onMouseDown={(event) => { if (event.target === event.currentTarget && !submitting) onClose() }}>
      <section aria-labelledby="ts-package-create-title" aria-modal="true" className="ts-package-create-modal" role="dialog">
        <form className="ts-package-create-modal__form" onSubmit={(event) => { void submit(event) }}>
          <header className="ts-package-create-modal__header"><h2 id="ts-package-create-title">Buat Paket</h2><button aria-label="Tutup modal" className="ts-package-create-modal__dismiss" disabled={submitting} onClick={onClose} type="button"><Icon name="close" size={20} /></button></header>
          <div className="ts-package-create-modal__scroll">
            <section className="ts-package-create-modal__identity">
              <label className="ts-package-create-field"><span>Nama Paket<span className="is-required">*</span></span><Input autoFocus aria-label="Nama Paket" maxLength={100} onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))} placeholder="Contoh: Paket Produksi Lengkap" required value={form.name} /></label>
              <label className="ts-package-create-field"><span>Deskripsi Paket <span className="is-optional">(Opsional)</span></span><textarea aria-label="Deskripsi Paket (Opsional)" maxLength={355} onChange={(event) => setForm((current) => ({ ...current, description: event.target.value }))} placeholder="Jelaskan fasilitas, batas revisi, format hasil, estimasi penyelesaian, serta biaya tambahan bila ada" rows={6} value={form.description} /></label>
              <label className="ts-package-create-field"><span>Informasi terkait <span className="is-optional">(Opsional)</span></span><textarea aria-label="Informasi terkait (Opsional)" maxLength={1000} onChange={(event) => setForm((current) => ({ ...current, related_info: event.target.value }))} placeholder="Contoh: Peralatan yang disediakan studio sudah termasuk dalam harga booking." rows={4} value={form.related_info} /></label>
            </section>

            <section className="ts-package-create-modal__production">
              <div className="ts-package-create-section-heading">Tahap Produksi<span className="is-required">*</span></div>
              <div className="ts-package-create-phase-grid">
                {phases.map((phase) => <ToggleablePhase icon={phase.icon as IconName} key={phase.key} label={phase.label} onChange={(selected) => toggleStage(phase.key, selected)} priceLabel={stagePriceLabel(phase.key)} selected={form[`include_${phase.key}`]} />)}
              </div>
              <div className="ts-package-create-counts">
                {recordingEnabled && <label className="ts-package-create-field"><span>Durasi Recording<span className="is-required">*</span></span><NumberStepper ariaLabel="durasi Recording" min={1} max={100} onChange={(value) => setForm((current) => ({ ...current, recording_hours: value }))} value={form.recording_hours} /><small>Jam</small></label>}
                <label className="ts-package-create-field"><span>Jumlah Lagu<span className="is-required">*</span></span><NumberStepper ariaLabel="jumlah lagu" min={1} max={50} onChange={(value) => setForm((current) => ({ ...current, track_count: value }))} value={form.track_count} /><small>Lagu</small></label>
              </div>
              {validationError && <p className="ts-package-create-error" role="alert">{validationError}</p>}
            </section>

            <section className="ts-package-create-modal__pricing">
              <div className="ts-package-create-section-heading">Rincian Harga</div>
              <div className="ts-package-create-price-container">
              <div className="ts-package-create-price-list">
                {phases.map((phase) => {
                  const selected = form[`include_${phase.key}`]
                  const quantity = phase.key === 'recording' ? form.recording_hours : form.track_count
                  const basePrice = stagePrice(phase.key)
                  const lineTotal = selected && basePrice !== null ? quantity * basePrice : 0
                  return <div className={`ts-package-create-price-line${selected ? '' : ' is-excluded'}`} key={phase.key}><div><strong>{phase.label}</strong><small>{selected && basePrice !== null ? `${number.format(quantity)} ${phase.unit} × ${currency.format(basePrice)}` : `${phase.key === 'recording' ? 'Harga per jam' : 'Harga per track'} ${basePrice === null ? 'belum diatur' : '× belum dipilih'}`}</small></div><span>{selected && basePrice !== null ? currency.format(lineTotal) : '—'}</span></div>
                })}
              </div>
              {priceError && <p className="ts-package-create-error" role="alert">{priceError}</p>}
              <div className="ts-package-create-auto-total"><span>Harga Paket</span><strong>{currency.format(manualPriceEnabled && form.manual_price ? Number(form.manual_price) : autoPrice ?? 0)}</strong></div>
              </div>
              <div className="ts-package-create-manual-toggle"><span>Atur Harga Manual</span><Switch aria-label="Atur Harga Manual" checked={manualPriceEnabled} onChange={(checked) => { setManualPriceEnabled(checked); if (!checked) setForm((current) => ({ ...current, manual_price: '' })) }} /></div>
              {manualPriceEnabled && <label className="ts-package-create-field"><span>Harga Manual</span><Input aria-label="Harga Manual" min="0" onChange={(event) => setForm((current) => ({ ...current, manual_price: event.target.value.replace(/\D/g, '') }))} placeholder="Masukkan harga manual" type="number" value={form.manual_price} />{!form.manual_price && <small>Kosongkan untuk menggunakan harga otomatis.</small>}</label>}
            </section>
          </div>
          {error && <p className="ts-package-create-error ts-package-create-error--submit" role="alert">{error}</p>}
          <footer className="ts-package-create-modal__footer"><Button disabled={submitting} onClick={onClose} variant="text">Batal</Button><Button disabled={submitting || Boolean(priceError)} type="submit">{submitting ? 'Membuat…' : 'Buat Paket'}</Button></footer>
        </form>
      </section>
    </div>
  )
  return createPortal(dialog, document.body)
}
