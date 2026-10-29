export const managerPages = {
  '/booking': 'Booking',
  '/quotation': 'Quotation',
  '/project': 'Project',
  '/inventaris': 'Inventaris',
  '/manajemen-paket': 'Manajemen Paket',
  '/crm': 'CRM',
  '/invoice': 'Invoice',
  '/laporan': 'Laporan',
  '/pengeluaran': 'Pengeluaran',
  '/operator': 'Operator',
  '/pengaturan': 'Pengaturan',
} as const

export type ManagerPagePath = keyof typeof managerPages