import type { ButtonHTMLAttributes } from 'react'
import { Icon } from '../icon'
import type { IconName } from '../icon'

export interface ToggleablePhaseProps extends Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'onChange'> {
  icon: IconName
  label: string
  priceLabel: string
  selected: boolean
  onChange: (selected: boolean) => void
}

export function ToggleablePhase({ icon, label, priceLabel, selected, onChange, className, ...props }: ToggleablePhaseProps) {
  return (
    <button aria-pressed={selected} className={['ts-toggleable-phase', selected ? 'is-selected' : '', className].filter(Boolean).join(' ')} onClick={() => onChange(!selected)} type="button" {...props}>
      <Icon aria-hidden="true" className="ts-toggleable-phase__icon" name={icon} size={48} />
      <span className="ts-toggleable-phase__copy"><strong>{label}</strong><small>{priceLabel}</small></span>
    </button>
  )
}