import { createContext, useContext } from 'react'
import type { AdminLoginResult } from '../types/admin-auth.model'

export const SUPER_ADMIN_ROLE = 'quan_tri_tong'
export const SUPER_ADMIN_ROLE_ID = 2
export const BUILDING_MANAGER_ROLE = 'quan_ly_toa_nha'
export const BUILDING_MANAGER_ROLE_ID = 1

export type AdminSessionStatus = 'idle' | 'checking' | 'authenticated' | 'guest'

export interface AdminSessionContextValue {
  session: AdminLoginResult | null
  status: AdminSessionStatus
  isAuthenticated: boolean
  isChecking: boolean
  saveSession: (payload: AdminLoginResult) => void
  clearSession: () => void
  refreshSession: () => Promise<AdminLoginResult | null>
}

export const AdminSessionContext = createContext<AdminSessionContextValue | null>(null)

export function isSuperAdminRole(role?: string | number | null): boolean {
  return role === SUPER_ADMIN_ROLE || role === SUPER_ADMIN_ROLE_ID || role === String(SUPER_ADMIN_ROLE_ID)
}

export function isBuildingManagerRole(role?: string | number | null): boolean {
  return role === BUILDING_MANAGER_ROLE || role === BUILDING_MANAGER_ROLE_ID || role === String(BUILDING_MANAGER_ROLE_ID)
}

export function useAdminSession() {
  const context = useContext(AdminSessionContext)

  if (!context) {
    throw new Error('useAdminSession must be used within AdminSessionProvider')
  }

  return context
}
