import { Outlet } from 'react-router-dom'
import { AdminSessionProvider } from '../features/admin/auth/hooks/admin-session-context'
import { AdminNotificationProvider } from '../features/admin/notifications'

function App() {
  return (
    <AdminSessionProvider>
      <AdminNotificationProvider>
        <Outlet />
      </AdminNotificationProvider>
    </AdminSessionProvider>
  )
}

export default App

