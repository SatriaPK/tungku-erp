import './ManagerPackageStageFilter.css'

export interface ManagerPackageStageFilterProps {
  active: boolean
  count: number
  label: string
  onClick: () => void
}

export function ManagerPackageStageFilter({ active, count, label, onClick }: ManagerPackageStageFilterProps) {
  return (
    <button
      aria-pressed={active}
      className={`manager-package-filter${active ? ' is-active' : ''}`}
      onClick={onClick}
      type="button"
    >
      <span>{label}</span>
      <span>{count.toLocaleString('id-ID')}</span>
    </button>
  )
}