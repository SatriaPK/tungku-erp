import { Icon } from '../icon'
import type { IconName } from '../icon'
import './ManagerPackageStatCard.css'

export interface ManagerPackageStatCardProps {
  label: string
  value: number
  icon: IconName
  tone: 'primary' | 'blue' | 'success' | 'warning' | 'mastering'
  onClick: () => void
}

const number = new Intl.NumberFormat('id-ID')

export function ManagerPackageStatCard({ label, value, icon, tone, onClick }: ManagerPackageStatCardProps) {
  return (
    <button className={`manager-package-stat manager-package-stat--${tone}`} onClick={onClick} type="button">
      <div><span>{label}</span><strong>{number.format(value)}</strong></div>
      <span aria-hidden="true" className="manager-package-stat__icon"><Icon name={icon} size={24} /></span>
    </button>
  )
}