import { createBrowserRouter, Navigate } from 'react-router-dom'
import App from '../App'
import { adminRoutes } from '../../routes/admin.routes'
import { tenantRoutes } from '../../routes/tenant.routes'

export const router = createBrowserRouter([
  {
    path: '/',
    element: <App />,
    children: [
      {
        index: true,
        element: <Navigate to="/admin/login" replace />,
      },
      ...adminRoutes,
      ...tenantRoutes,
    ],
  },
])
