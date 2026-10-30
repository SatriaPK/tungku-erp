import type { HTMLAttributes } from 'react'
import { Icon } from '../icon'

export interface NumberStepperProps extends Omit<HTMLAttributes<HTMLDivElement>, 'onChange'> {
  ariaLabel: string
  min?: number
  max?: number
  value: number
  disabled?: boolean
  onChange: (value: number) => void
}

export function NumberStepper({ ariaLabel, min = 0, max = Number.MAX_SAFE_INTEGER, value, onChange, disabled = false, className, ...props }: NumberStepperProps) {
  const decrement = () => onChange(Math.max(min, value - 1))
  const increment = () => onChange(Math.min(max, value + 1))

  return (
    <div aria-label={ariaLabel} className={['ts-number-stepper', className].filter(Boolean).join(' ')} {...props}>
      <button aria-label={`Kurangi ${ariaLabel}`} disabled={disabled || value <= min} onClick={decrement} type="button"><Icon name="minus" size={18} /></button>
      <output aria-live="polite">{new Intl.NumberFormat('id-ID').format(value)}</output>
      <button aria-label={`Tambah ${ariaLabel}`} disabled={disabled || value >= max} onClick={increment} type="button"><Icon name="plus" size={18} /></button>
    </div>
  )
}