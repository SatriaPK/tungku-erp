import { Badge } from '../badge'
import { Icon } from '../icon'
import type { ManagerPackage } from '../../../lib/manager-packages-api'
import './ManagerPackageCard.css'

const currency = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 })
const number = new Intl.NumberFormat('id-ID')

const stages = [
  { key: 'recording', label: 'Recording', icon: 'microphone', tone: 'recording' },
  { key: 'editing', label: 'Editing', icon: 'scissors', tone: 'editing' },
  { key: 'mixing', label: 'Mixing', icon: 'sliders', tone: 'mixing' },
  { key: 'mastering', label: 'Mastering', icon: 'activity', tone: 'mastering' },
] as const

export interface ManagerPackageCardProps {
  item: ManagerPackage
  onMenu: (anchor: DOMRect) => void
  onSelect: () => void
}

export function ManagerPackageCard({ item, onMenu, onSelect }: ManagerPackageCardProps) {
  const includedStages = stages.filter((stage) => (item.stages ?? []).includes(stage.key))

  return (
    <article aria-label={`Lihat detail paket ${item.name}`} className={`manager-package-card${item.is_active ? '' : ' is-inactive'}`} onClick={onSelect} onKeyDown={(event) => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); onSelect() } }} role="button" tabIndex={0}>
      <div className="manager-package-card__heading">
        <h3>{item.name}{!item.is_active && <span className="manager-package-card__inactive-label">Nonaktif</span>}</h3>
      <button aria-label={`Opsi paket ${item.name}`} className="manager-package-card__menu" onClick={(event) => { event.stopPropagation(); onMenu(event.currentTarget.getBoundingClientRect()) }} type="button">
          <Icon name="ellipsis-vertical" size={20} />
        </button>
      </div>
      <p className="manager-package-card__description" title={item.description ?? undefined}>
        {item.description || 'Deskripsi paket belum tersedia.'}
      </p>
      <div aria-label="Tahap paket" className="manager-package-card__stages">
        {includedStages.map((stage) => <Badge icon={stage.icon} key={stage.key} tone={stage.tone}>{stage.label}</Badge>)}
      </div>
      <div className="manager-package-card__separator" />
      <div className="manager-package-card__footer">
        <strong>{currency.format(item.price).replace(/\u00a0/g, ' ')}</strong>
        <span><span>{number.format(item.recording_hours)} Jam rekaman</span><span>{number.format(item.track_count)} lagu</span></span>
      </div>
    </article>
  )
}