import { Icon, NavAccordion, NavItem } from '../ui'
import type { IconName } from '../ui'
import tungkuIcon from '../../assets/tungku-icon.svg'
import { managerNavigation } from './manager-navigation'
import './manager-side-nav.css'

export interface ManagerSideNavItem {
  label: string
  icon: IconName
  href?: string
  badge?: string | number
  active?: boolean
  onClick?: () => void
}

export interface ManagerSideNavSection {
  label: string
  items: ManagerSideNavItem[]
  defaultExpanded?: boolean
}

export interface ManagerSideNavProps {
  sections?: ManagerSideNavSection[]
  managerName: string
  logoSrc?: string
  logoAlt?: string
  profileImageSrc?: string
  onLogout?: () => void
  logoutHref?: string
  className?: string
}

export function ManagerSideNav({
  sections = managerNavigation,
  managerName,
  logoSrc = tungkuIcon,
  logoAlt = 'Tungku Studio',
  profileImageSrc,
  onLogout,
  logoutHref,
  className = '',
}: ManagerSideNavProps) {
  const currentPath = typeof window === 'undefined' ? '' : window.location.pathname
  const sidebarClasses = ['manager-side-nav', className].filter(Boolean).join(' ')

  return (
    <aside aria-label="Navigasi Manager" className={sidebarClasses}>
      <div className="manager-side-nav__top">
        <a aria-label="Tungku Studio" className="manager-side-nav__brand" href="/dashboard">
          <img alt={logoAlt} className="manager-side-nav__logo" src={logoSrc} />
          <span className="manager-side-nav__brand-copy">
            <strong>Tungku Studio</strong>
            <small>Enterprise Resource Planning</small>
          </span>
        </a>
        <nav aria-label="Navigasi utama" className="manager-side-nav__sections">
          {sections.map((section) => (
            <NavAccordion defaultExpanded={section.defaultExpanded} key={section.label} label={section.label}>
              {section.items.map((item) => (
                <NavItem
                  active={item.href ? currentPath === item.href : item.active}
                  badge={item.badge}
                  href={item.href}
                  icon={item.icon}
                  key={item.label}
                  label={item.label}
                  onClick={item.onClick}
                />
              ))}
            </NavAccordion>
          ))}
        </nav>
      </div>

      <div className="manager-side-nav__profile">
        {profileImageSrc
          ? <img alt="" className="manager-side-nav__avatar" src={profileImageSrc} />
          : <span aria-hidden="true" className="manager-side-nav__avatar manager-side-nav__avatar--placeholder"><Icon name="user" size={24} /></span>}
        <span className="manager-side-nav__profile-copy">
          <strong>{managerName}</strong>
          <small>Manager</small>
        </span>
        {logoutHref
          ? <a aria-label="Keluar" className="manager-side-nav__logout" href={logoutHref}><Icon name="logout" size={24} /></a>
          : <button aria-label="Keluar" className="manager-side-nav__logout" onClick={onLogout} type="button"><Icon name="logout" size={24} /></button>}
      </div>
    </aside>
  )
}