interface PackageStageFilterProps {
  active: boolean
  count: number
  label: string
  onClick: () => void
}

export function PackageStageFilter({ active, count, label, onClick }: PackageStageFilterProps) {
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