import { useEffect, useState } from 'react'
import { ManagerSideNav, UpperNavBar } from '../../../components/layout'
import { getCurrentUser } from '../../../lib/auth-api'
import { managerPages, type ManagerPagePath } from './manager-pages'
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

  return (
    <div className="manager-shell">
      <ManagerSideNav managerName={managerName} />
      <main className="manager-main manager-placeholder">
        <UpperNavBar path={path} title={title} />
        <section aria-label={`${title} kosong`} className="manager-placeholder__content" />
      </main>
    </div>
  )
}