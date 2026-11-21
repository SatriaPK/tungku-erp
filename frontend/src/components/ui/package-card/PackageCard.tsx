import type { HTMLAttributes, ReactNode } from 'react'
import { Badge } from '../badge'
import type { PhaseChipProps } from '../badge'
import { Icon } from '../icon'

export interface PackageCardProps extends HTMLAttributes<HTMLElement> {
  name: string
  description: string
  price: string
  phases: readonly PhaseChipProps['phase'][]
  recordingHours: number
  songCount: number
  onSelect?: () => void
  onMenuClick?: () => void
  phaseIcons?: Partial<Record<PhaseChipProps['phase'], ReactNode>>
  menuIcon?: ReactNode
  separator?: ReactNode
}

const phaseIcon = {
  Recording: 'microphone', Editing: 'scissors', Mixing: 'sliders', Mastering: 'activity',
} as const

const phaseTone = {
  Recording: 'recording', Editing: 'editing', Mixing: 'mixing', Mastering: 'mastering',
} as const

export function PackageCard({
  name,
  description,
  price,
  phases,
  recordingHours,
  songCount,
  onSelect,
  onMenuClick,
  phaseIcons,
  menuIcon,
  separator,
  className,
  ...props
}: PackageCardProps) {
  return (
    <article className={['ts-package-card', onSelect ? 'ts-package-card--selectable' : '', className].filter(Boolean).join(' ')} {...props}>
      {onSelect && <button type="button" className="ts-package-card__select" aria-label={`Lihat detail ${name}`} onClick={onSelect} />}
      <div className="ts-package-card__heading">
        <h3>{name}</h3>
        {onMenuClick && (
          <button aria-label={`Aksi untuk ${name}`} className="ts-icon-button" onClick={onMenuClick} type="button">
            {menuIcon ?? <Icon name="more" size={20} />}
          </button>
        )}
      </div>
      <p className="ts-package-card__description">{description}</p>
      <div aria-label="Tahap dalam paket" className="ts-package-card__phases">
        {phases.map((phase) => (
          <Badge icon={phaseIcons?.[phase] ? undefined : phaseIcon[phase]} key={phase} tone={phaseTone[phase]}>
            {phaseIcons?.[phase]}
            {phase}
          </Badge>
        ))}
      </div>
      {separator && <div className="ts-package-card__separator">{separator}</div>}
      <div className="ts-package-card__footer">
        <p className="ts-package-card__price">{price}</p>
        <p className="ts-package-card__details"><span>{recordingHours} Jam Rekaman</span><span>{songCount} Lagu</span></p>
      </div>
    </article>
  )
}
