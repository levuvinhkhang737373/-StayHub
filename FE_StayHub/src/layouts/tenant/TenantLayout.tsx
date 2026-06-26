import { Outlet } from 'react-router-dom'
import { TenantChatWidget } from '../../features/tenant/chat/components/TenantChatWidget'
import { TenantFooter } from './TenantFooter'
import { TenantHeader } from './TenantHeader'
import { TenantSocketProvider } from '../../shared/lib/socket/socket-context'

export function TenantLayout() {
  return (
    <TenantSocketProvider>
      <div className="min-h-screen bg-slate-50">
        <TenantHeader />
        <main className="p-4 lg:p-6">
          <Outlet />
        </main>
        <TenantFooter />
        <TenantChatWidget />
      </div>
    </TenantSocketProvider>
  )
}
