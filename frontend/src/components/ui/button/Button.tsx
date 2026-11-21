import type { ButtonHTMLAttributes, ReactNode } from 'react'
import { Icon } from '../icon'
import type { IconName } from '../icon'

export type ButtonVariant = 'filled' | 'outlined' | 'tonal' | 'text' | 'dark'

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant
  leadingIcon?: IconName
  trailingIcon?: IconName
  iconOnly?: boolean
  children: ReactNode
}

export function Button({
  variant = 'filled',
  leadingIcon,
  trailingIcon,
  iconOnly = false,
  className,
  children,
  type = 'button',
  ...props
}: ButtonProps) {
  const classes = ['ts-button', `ts-button--${variant}`, iconOnly ? 'ts-button--icon-only' : '', className]
    .filter(Boolean)
    .join(' ')

  return (
    <button className={classes} type={type} {...props}>
      {leadingIcon && <Icon aria-hidden="true" name={leadingIcon} size={19} />}
      {!iconOnly && <span>{children}</span>}
      {trailingIcon && <Icon aria-hidden="true" name={trailingIcon} size={19} />}
    </button>
  )
}

export interface SegmentedButtonProps {
  options: readonly string[]
  value: string
  onChange: (value: string) => void
  className?: string
}

export function SegmentedButton({ options, value, onChange, className }: SegmentedButtonProps) {
  return (
    <div aria-label="Filter pilihan" className={['ts-segmented', className].filter(Boolean).join(' ')} role="group">
      {options.map((option) => (
        <button
          aria-pressed={value === option}
          className={value === option ? 'ts-segmented__option is-selected' : 'ts-segmented__option'}
          key={option}
          onClick={() => onChange(option)}
          type="button"
        >
          {option}
        </button>
      ))}
    </div>
  )
}