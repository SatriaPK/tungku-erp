import { Badge } from '../badge'
import type { BadgeTone, PhaseChipProps } from '../badge'
import { Button } from '../button'
import { Icon } from '../icon'

export interface ProjectCardProps {
  name: string
  client: string
  phase: PhaseChipProps['phase'] | 'Selesai' | 'Revisi'
  progress: number
  progressLabel?: string
  date?: string
  operators?: readonly { name: string; avatarUrl?: string }[]
  operatorsLabel?: string
  clientAvatarUrl?: string
  onDetails?: () => void
  className?: string
}

const phaseTone: Record<ProjectCardProps['phase'], BadgeTone> = {
  Recording: 'recording', Editing: 'editing', Mixing: 'mixing', Mastering: 'mastering', Selesai: 'success', Revisi: 'neutral',
}

const phaseIcon: Record<ProjectCardProps['phase'], 'microphone' | 'scissors' | 'sliders' | 'activity' | 'check' | 'edit'> = {
  Recording: 'microphone', Editing: 'scissors', Mixing: 'sliders', Mastering: 'activity', Selesai: 'check', Revisi: 'edit',
}

export function ProjectCard({
  name,
  client,
  phase,
  progress,
  progressLabel,
  date,
  operators = [],
  operatorsLabel = 'Operator:',
  clientAvatarUrl,
  onDetails,
  className,
}: ProjectCardProps) {
  const clampedProgress = Math.min(100, Math.max(0, progress))

  return (
    <article aria-label={`Proyek ${name}`} className={['ts-project-card', className].filter(Boolean).join(' ')}>
      <div className="ts-project-card__top">
        <div className="ts-project-card__avatar" aria-hidden="true">{clientAvatarUrl ? <img alt="" src={clientAvatarUrl} /> : <Icon name="user" size={24} />}</div>
        <div className="ts-project-card__title">
          <h3>{name}</h3>
          <p>{client}</p>
        </div>
        <div className="ts-project-card__meta">
          <Badge icon={phaseIcon[phase]} tone={phaseTone[phase]}>{phase}</Badge>
          {date && <time>{date}</time>}
        </div>
      </div>
      <div className="ts-project-card__progress-info">
        <span>{clampedProgress}%</span>
        <span>{progressLabel ?? phase}</span>
      </div>
      <div aria-label={`Progress ${clampedProgress}%`} className="ts-progress" role="progressbar" aria-valuenow={clampedProgress} aria-valuemin={0} aria-valuemax={100}>
        <span style={{ width: `${clampedProgress}%` }} />
      </div>
      <div className="ts-project-card__footer">
        <div className="ts-project-card__operators">
          <span className="ts-project-card__operators-label">{operatorsLabel}</span>
          <div aria-label="Operator proyek" className="ts-avatar-stack">
            {operators.slice(0, 4).map((operator) => (
              <span className="ts-avatar" key={operator.name} title={operator.name}>
                {operator.avatarUrl ? <img alt="" src={operator.avatarUrl} /> : operator.name.slice(0, 1).toUpperCase()}
              </span>
            ))}
            {operators.length === 0 && <span className="ts-project-card__empty">Belum ditugaskan</span>}
          </div>
        </div>
        <Button aria-label={`Lihat detail ${name}`} className="ts-button--link" onClick={onDetails} trailingIcon="arrow-right" variant="text">
          Lihat Detail
        </Button>
      </div>
    </article>
  )
}