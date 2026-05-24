import type { RouteObject } from 'react-router-dom'
import { Navigate } from 'react-router-dom'
import { TenantDashboardScreen } from '../features/tenant/dashboard/components/tenant-dashboard-screen'
import { TenantLayout } from '../layouts/tenant/TenantLayout'

export const tenantRoutes: RouteObject[] = [
  {
    path: 'tenant',
    element: <TenantLayout />,
    children: [
      {
        index: true,
        element: <Navigate to="/tenant/dashboard" replace />,
      },
      {
        path: 'dashboard',
        element: <TenantDashboardScreen />,
      },
    ],
  },
]
