import type { AnchorHTMLAttributes } from 'react'
import type { IconName } from '../icon'
import { Icon } from '../icon'
import { Badge } from '../badge'
import type { BadgeTone } from '../badge'

export type StatCardTone = 'primary' | 'success' | 'warning' | 'error' | 'blue' | 'purple' | 'neutral' | 'mastering'

export interface StatCardProps extends Omit<AnchorHTMLAttributes<HTMLAnchorElement>, 'children' | 'href'> {
  label: string
  value: string | number
  icon: IconName
  tone?: StatCardTone
  trend?: { value: string; direction: 'up' | 'down' | 'neutral' }
  detail?: string
  className?: string
  badge?: { label: string; icon?: IconName; tone?: BadgeTone }
  href?: string
}

export function StatCard({ label, value, icon, tone = 'primary', trend, detail, className, badge, href, ...linkProps }: StatCardProps) {
  const content = (
    <>
      {(badge || trend) && (
        <span className="ts-stat-card__top">
          {badge ? <Badge className="ts-stat-card__badge" icon={badge.icon} tone={badge.tone}>{badge.label}</Badge> : trend && (
            <span className={['ts-stat-card__trend', `is-${trend.direction}`].join(' ')}>
              <Icon name={trend.direction === 'up' ? 'trending-up' : trend.direction === 'down' ? 'trending-down' : 'activity'} size={14} />
              {trend.value}
            </span>
          )}
          {href && <Icon aria-hidden="true" className="ts-stat-card__arrow" name="arrow-right" size={20} />}
        </span>
      )}
      <span className="ts-stat-card__bottom">
        <span className="ts-stat-card__copy">
          <span className="ts-stat-card__label">{label}</span>
          <strong className="ts-stat-card__value">{value}</strong>
          {detail && <span className="ts-stat-card__detail">{detail}</span>}
        </span>
        <span aria-hidden="true" className="ts-stat-card__icon"><Icon name={icon} size={26} /></span>
      </span>
    </>
  )

  return (
    href
      ? <a aria-label={linkProps['aria-label'] ?? `${label}: ${value}. ${badge?.label ?? trend?.value ?? ''}. Buka ${label}`} className={['ts-stat-card', `ts-stat-card--${tone}`, 'ts-stat-card--interactive', className].filter(Boolean).join(' ')} href={href} {...linkProps}>{content}</a>
      : <article className={['ts-stat-card', `ts-stat-card--${tone}`, className].filter(Boolean).join(' ')}>{content}</article>
  )
}