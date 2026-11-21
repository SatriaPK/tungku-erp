import type { HTMLAttributes, ReactNode } from 'react'

export interface CardProps extends HTMLAttributes<HTMLElement> {
  children: ReactNode
  interactive?: boolean
}

export function Card({ children, interactive = false, className, ...props }: CardProps) {
  return (
    <article className={['ts-card', interactive ? 'ts-card--interactive' : '', className].filter(Boolean).join(' ')} {...props}>
      {children}
    </article>
  )
}

export interface CardHeaderProps extends HTMLAttributes<HTMLDivElement> {
  title: string
  action?: ReactNode
  children?: ReactNode
}

export function CardHeader({ title, action, children, className, ...props }: CardHeaderProps) {
  return (
    <div className={['ts-card__header', className].filter(Boolean).join(' ')} {...props}>
      <div className="ts-card__heading">
        <h3>{title}</h3>
        {children}
      </div>
      {action}
    </div>
  )
}