import { useId, useState, type ReactNode } from 'react'
import './styles.css'

export interface NavAccordionProps {
  label: string
  children: ReactNode
  defaultExpanded?: boolean
  expanded?: boolean
  onExpandedChange?: (expanded: boolean) => void
  className?: string
}

export function NavAccordion({
  label,
  children,
  defaultExpanded = false,
  expanded,
  onExpandedChange,
  className = '',
}: NavAccordionProps) {
  const [internalExpanded, setInternalExpanded] = useState(defaultExpanded)
  const controlled = expanded !== undefined
  const isExpanded = controlled ? expanded : internalExpanded
  const panelId = useId()

  function toggle() {
    const nextExpanded = !isExpanded
    if (!controlled) setInternalExpanded(nextExpanded)
    onExpandedChange?.(nextExpanded)
  }

  return (
    <section className={['nav-accordion', className].filter(Boolean).join(' ')}>
      <button
        aria-controls={panelId}
        aria-expanded={isExpanded}
        className="nav-accordion__trigger"
        onClick={toggle}
        type="button"
      >
        <span className="nav-accordion__label">{label}</span>
        <span aria-hidden="true" className="nav-accordion__chevron" />
      </button>
      <div className="nav-accordion__content" hidden={!isExpanded} id={panelId}>
        {children}
      </div>
    </section>
  )
}