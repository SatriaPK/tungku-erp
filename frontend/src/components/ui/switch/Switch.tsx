import type { ButtonHTMLAttributes } from 'react'

export interface SwitchProps extends Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'onChange' | 'role'> {
  checked: boolean
  onChange: (checked: boolean) => void
  label?: string
}

export function Switch({ checked, onChange, label, className, ...props }: SwitchProps) {
  return (
    <button
      aria-checked={checked}
      aria-label={props['aria-label'] ?? label}
      className={['ts-switch', checked ? 'is-checked' : '', className].filter(Boolean).join(' ')}
      onClick={() => onChange(!checked)}
      role="switch"
      type="button"
      {...props}
    >
      <span className="ts-switch__thumb" />
      {label && <span className="ts-switch__label">{label}</span>}
    </button>
  )
}