import { useId } from 'react'
import type { InputHTMLAttributes, ReactNode } from 'react'
import { Icon } from '../icon'
import type { IconName } from '../icon'

export interface InputProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'size'> {
  label?: string
  leadingIcon?: IconName
  trailingAction?: ReactNode
  error?: string
  hint?: string
}

export function Input({
  label,
  leadingIcon,
  trailingAction,
  error,
  hint,
  className,
  id: providedId,
  ...props
}: InputProps) {
  const generatedId = useId()
  const id = providedId ?? generatedId
  const messageId = error ? `${id}-error` : hint ? `${id}-hint` : undefined

  return (
    <div className="ts-input-field">
      {label && <label className="ts-input-field__label" htmlFor={id}>{label}</label>}
      <div className={['ts-input', error ? 'is-invalid' : '', className].filter(Boolean).join(' ')}>
        {leadingIcon && <Icon aria-hidden="true" className="ts-input__icon" name={leadingIcon} size={20} />}
        <input aria-describedby={messageId} aria-invalid={Boolean(error)} id={id} {...props} />
        {trailingAction}
      </div>
      {(error || hint) && (
        <span className={error ? 'ts-input-field__message is-error' : 'ts-input-field__message'} id={messageId}>
          {error ?? hint}
        </span>
      )}
    </div>
  )
}