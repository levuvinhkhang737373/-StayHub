import { Outlet } from 'react-router-dom'
import { AdminSessionProvider } from '../features/admin/auth/hooks/admin-session-context'

function App() {
  return (
    <AdminSessionProvider>
      <Outlet />
    </AdminSessionProvider>
  )
}

export default App
