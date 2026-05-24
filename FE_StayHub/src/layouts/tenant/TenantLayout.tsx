import { Outlet } from 'react-router-dom'
import { TenantFooter } from './TenantFooter'
import { TenantHeader } from './TenantHeader'

export function TenantLayout() {
  return (
    <div className="min-h-screen bg-slate-50">
      <TenantHeader />
      <main className="p-4 lg:p-6">
        <Outlet />
      </main>
      <TenantFooter />
    </div>
  )
}
