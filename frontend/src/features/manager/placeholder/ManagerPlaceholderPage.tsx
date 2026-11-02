import { useEffect, useState } from 'react'
import { ManagerSideNav, UpperNavBar } from '../../../components/layout'
import { getCurrentUser } from '../../../lib/auth-api'
import { managerPages, type ManagerPagePath } from './manager-pages'
import ManagerWorkspace, { type Section } from '../workspace/ManagerWorkspace'
import '../dashboard/ManagerDashboard.css'
import './manager-placeholder-page.css'

export default function ManagerPlaceholderPage({ path }: { path: ManagerPagePath }) {
  const [managerName, setManagerName] = useState('Manager')

  useEffect(() => {
    let mounted = true
    void getCurrentUser().then((user) => {
      if (mounted && user.role === 'manager') setManagerName(user.name)
    }).catch(() => undefined)
    return () => { mounted = false }
  }, [])

  const title = managerPages[path]
  const sections: Record<ManagerPagePath, Section> = {
    '/booking': 'booking', '/quotation': 'quotation', '/project': 'project', '/inventaris': 'inventory',
    '/manajemen-paket': 'packages', '/crm': 'crm', '/invoice': 'invoice', '/laporan': 'reports',
    '/pengeluaran': 'expenses', '/operator': 'operator', '/pengaturan': 'settings',
  }

  return (
    <div className="manager-shell">
      <ManagerSideNav managerName={managerName} />
      <main className="manager-main manager-placeholder">
        <UpperNavBar path={path} title={title} />
        <div className="manager-placeholder__content"><ManagerWorkspace section={sections[path]} /></div>
      </main>
    </div>
  )
}
