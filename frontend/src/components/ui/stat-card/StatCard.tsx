import type { IconName } from '../icon'
import { Icon } from '../icon'

export type StatCardTone = 'primary' | 'success' | 'warning' | 'error' | 'blue' | 'purple' | 'neutral'

export interface StatCardProps {
  label: string
  value: string | number
  icon: IconName
  tone?: StatCardTone
  trend?: { value: string; direction: 'up' | 'down' | 'neutral' }
  detail?: string
  className?: string
}

export function StatCard({ label, value, icon, tone = 'primary', trend, detail, className }: StatCardProps) {
  return (
    <article className={['ts-stat-card', `ts-stat-card--${tone}`, className].filter(Boolean).join(' ')}>
      {trend && (
        <span className={['ts-stat-card__trend', `is-${trend.direction}`].join(' ')}>
          <Icon name={trend.direction === 'up' ? 'trending-up' : trend.direction === 'down' ? 'trending-down' : 'activity'} size={14} />
          {trend.value}
        </span>
      )}
      <span aria-hidden="true" className="ts-stat-card__icon"><Icon name={icon} size={26} /></span>
      <p className="ts-stat-card__label">{label}</p>
      <p className="ts-stat-card__value">{value}</p>
      {detail && <p className="ts-stat-card__detail">{detail}</p>}
    </article>
  )
}