import { Icon } from '../icon'
import type { IconName } from '../icon'
import { Link } from 'react-router-dom'
import './styles.css'

export interface NavItemProps {
  label: string
  icon: IconName
  href?: string
  badge?: string | number
  badgeTone?: 'primary' | 'dark'
  active?: boolean
  onClick?: () => void
  className?: string
}

export function NavItem({ label, icon, href, badge, badgeTone = 'primary', active = false, onClick, className = '' }: NavItemProps) {
  const classes = ['nav-item', active ? 'is-active' : '', className].filter(Boolean).join(' ')
  const content = (
    <>
      <Icon className="nav-item__icon" name={icon} size={20} />
      <span className="nav-item__label">{label}</span>
      {badge !== undefined && badge !== null && badge !== '' && (
        <span aria-label={`${badge} notifikasi`} className={`nav-item__badge nav-item__badge--${badgeTone}`}>{badge}</span>
      )}
    </>
  )

  if (href) {
    return <Link aria-current={active ? 'page' : undefined} className={classes} onClick={onClick} to={href}>{content}</Link>
  }

  return <button aria-current={active ? 'page' : undefined} className={classes} onClick={onClick} type="button">{content}</button>
}