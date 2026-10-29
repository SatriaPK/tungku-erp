import type { IconName } from '../ui'
import { managerPages } from '../../features/manager/placeholder/manager-pages'

export interface ManagerNavItem {
  label: string
  icon: IconName
  href: string
  badge?: string | number
}

export interface ManagerNavSection {
  label: string
  items: ManagerNavItem[]
  defaultExpanded?: boolean
}

export type ManagerNavigationPath = '/dashboard' | keyof typeof managerPages

export const managerNavigation: ManagerNavSection[] = [
  { label: 'UTAMA', defaultExpanded: true, items: [{ label: 'Dashboard', icon: 'grid', href: '/dashboard' }] },
  { label: 'OPERASIONAL', defaultExpanded: true, items: [
    { label: 'Booking', icon: 'calendar', href: '/booking' },
    { label: 'Quotation', icon: 'handshake', href: '/quotation', badge: 2 },
    { label: 'Project', icon: 'folder', href: '/project', badge: 2 },
    { label: 'Inventaris', icon: 'inventory', href: '/inventaris' },
  ] },
  { label: 'PENJUALAN', defaultExpanded: true, items: [
    { label: 'Manajemen Paket', icon: 'package', href: '/manajemen-paket' },
    { label: 'CRM', icon: 'users', href: '/crm' },
  ] },
  { label: 'KEUANGAN', defaultExpanded: true, items: [
    { label: 'Invoice', icon: 'file', href: '/invoice' },
    { label: 'Laporan', icon: 'chart', href: '/laporan' },
    { label: 'Pengeluaran', icon: 'dollar', href: '/pengeluaran' },
  ] },
  { label: 'ADMINISTRASI', defaultExpanded: true, items: [
    { label: 'Operator', icon: 'user', href: '/operator' },
    { label: 'Pengaturan', icon: 'settings', href: '/pengaturan' },
  ] },
]

export function getManagerNavigationLabels(path: ManagerNavigationPath) {
  for (const section of managerNavigation) {
    const item = section.items.find((candidate) => candidate.href === path)
    if (item) {
      const sectionLabel = section.label.toLocaleLowerCase('id-ID')
      const titleCaseSection = sectionLabel.charAt(0).toLocaleUpperCase('id-ID') + sectionLabel.slice(1)
      return { section: titleCaseSection, page: path === '/dashboard' ? item.label : managerPages[path] }
    }
  }

  return { section: 'Utama', page: 'Dashboard' }
}