import type { ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import { isSuperAdminRole, useAdminSession } from '../../auth/hooks/use-admin-session'
import type { AdminRouteAccess } from '../config/admin-navigation'

interface AdminRouteGuardProps {
  access: AdminRouteAccess
  children: ReactNode
}

export function AdminRouteGuard({ access, children }: AdminRouteGuardProps) {
  const location = useLocation()
  const { isAuthenticated, session } = useAdminSession()

  if (!isAuthenticated) {
    return <Navigate to="/admin/login" replace state={{ from: location.pathname }} />
  }

  if (access === 'superadmin' && !isSuperAdminRole(session?.admin.role)) {
    return <Navigate to="/admin/dashboard" replace state={{ deniedFrom: location.pathname }} />
  }

  return children
}
