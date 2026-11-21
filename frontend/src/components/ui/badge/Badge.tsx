import type { HTMLAttributes, ReactNode } from 'react'
import { Icon } from '../icon'
import type { IconName } from '../icon'

export type BadgeTone = 'neutral' | 'primary' | 'success' | 'warning' | 'error' | 'recording' | 'editing' | 'mixing' | 'mastering'

export interface BadgeProps extends HTMLAttributes<HTMLSpanElement> {
  tone?: BadgeTone
  icon?: IconName
  children: ReactNode
}

export function Badge({ tone = 'neutral', icon, className, children, ...props }: BadgeProps) {
  return (
    <span className={['ts-badge', `ts-badge--${tone}`, className].filter(Boolean).join(' ')} {...props}>
      {icon && <Icon aria-hidden="true" name={icon} size={14} />}
      {children}
    </span>
  )
}

export interface PhaseChipProps extends HTMLAttributes<HTMLSpanElement> {
  phase: 'Recording' | 'Editing' | 'Mixing' | 'Mastering'
}

const phaseAppearance = {
  Recording: { tone: 'recording', icon: 'microphone' },
  Editing: { tone: 'editing', icon: 'scissors' },
  Mixing: { tone: 'mixing', icon: 'sliders' },
  Mastering: { tone: 'mastering', icon: 'activity' },
} as const satisfies Record<PhaseChipProps['phase'], { tone: BadgeTone; icon: IconName }>

export function PhaseChip({ phase, ...props }: PhaseChipProps) {
  const appearance = phaseAppearance[phase]
  return <Badge icon={appearance.icon} tone={appearance.tone} {...props}>{phase}</Badge>
}