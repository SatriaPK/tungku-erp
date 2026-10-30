import { Icon } from '../../../components/ui'
import type { IconName } from '../../../components/ui'

export interface PackageStatCardProps {
  label: string
  value: number
  icon: IconName
  tone: 'primary' | 'blue' | 'success' | 'warning' | 'mastering'
  onClick: () => void
}

const number = new Intl.NumberFormat('id-ID')

export function PackageStatCard({ label, value, icon, tone, onClick }: PackageStatCardProps) {
  return (
    <button className={`manager-package-stat manager-package-stat--${tone}`} onClick={onClick} type="button">
      <div><span>{label}</span><strong>{number.format(value)}</strong></div>
      <span aria-hidden="true" className="manager-package-stat__icon"><Icon name={icon} size={24} /></span>
    </button>
  )
}