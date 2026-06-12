import { Outlet } from 'react-router-dom'
import { AdminSessionProvider } from '../features/admin/auth/hooks/admin-session-context'
import { AdminSocketProvider } from '../shared/lib/socket/socket-context'
import { AdminNotificationProvider } from '../features/admin/notifications'

function App() {
  return (
    <AdminSessionProvider>
      <AdminSocketProvider>
        <AdminNotificationProvider>
          <Outlet />
        </AdminNotificationProvider>
      </AdminSocketProvider>
    </AdminSessionProvider>
  )
}

export default App

