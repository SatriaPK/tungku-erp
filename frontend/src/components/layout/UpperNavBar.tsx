import type { ReactNode } from 'react'
import { getManagerNavigationLabels, type ManagerNavigationPath } from './manager-navigation'

export interface UpperNavBarProps {
  path?: ManagerNavigationPath
  title?: string
  tools?: ReactNode
}

export function UpperNavBar({ path, title, tools }: UpperNavBarProps) {
  const currentPath = typeof window === 'undefined' ? '/dashboard' : window.location.pathname
  const labels = getManagerNavigationLabels(path ?? (currentPath as ManagerNavigationPath))
  const pageTitle = title ?? labels.page

  return (
    <header className="manager-topbar">
      <div className="manager-topbar__heading">
        <span className="manager-breadcrumb">{labels.section} / {pageTitle}</span>
        <h1>{pageTitle}</h1>
      </div>
      {tools && <div className="manager-topbar__tools">{tools}</div>}
    </header>
  )
}