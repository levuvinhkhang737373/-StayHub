import { createContext, useContext } from 'react'
import type { AdminLoginResult, AdminProfile } from '../types/admin-auth.model'

export const SUPER_ADMIN_ROLE = 'quan_tri_tong'
export const SUPER_ADMIN_ROLE_ID = 2
const LEGACY_SUPER_ADMIN_ROLES = ['super_admin', 'superadmin', 'admin']
export const BUILDING_MANAGER_ROLE = 'quan_ly_toa_nha'
export const BUILDING_MANAGER_ROLE_ID = 1
const LEGACY_BUILDING_MANAGER_ROLES = ['building_manager', 'manager', 'quanly_toanha', 'quan_ly_toanha']

function normalizeRole(role?: string | number | null): string {
  return String(role ?? '')
    .trim()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/đ/g, 'd')
    .replace(/[\s-]+/g, '_')
}

function isObject(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null
}

export function getAdminSessionProfile(payload: unknown): AdminProfile | null {
  let current = payload

  for (let depth = 0; depth < 5; depth += 1) {
    if (!isObject(current)) return null

    if (isObject(current.admin)) {
      current = current.admin
      continue
    }

    if ('id' in current || 'username' in current || 'full_name' in current || 'role' in current) {
      return current as unknown as AdminProfile
    }

    return null
  }

  return null
}

export function normalizeAdminSession(payload: unknown): AdminLoginResult | null {
  const admin = getAdminSessionProfile(payload)
  return admin ? { admin } : null
}

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
  if (role === SUPER_ADMIN_ROLE_ID) return true

  const normalizedRole = normalizeRole(role)
  return normalizedRole === SUPER_ADMIN_ROLE || normalizedRole === String(SUPER_ADMIN_ROLE_ID) || LEGACY_SUPER_ADMIN_ROLES.includes(normalizedRole)
}

export function isBuildingManagerRole(role?: string | number | null): boolean {
  if (role === BUILDING_MANAGER_ROLE_ID) return true

  const normalizedRole = normalizeRole(role)
  return normalizedRole === BUILDING_MANAGER_ROLE || normalizedRole === String(BUILDING_MANAGER_ROLE_ID) || LEGACY_BUILDING_MANAGER_ROLES.includes(normalizedRole)
}

export function canManageContractsRole(role?: string | number | null): boolean {
  return isSuperAdminRole(role) || isBuildingManagerRole(role)
}

export function useAdminSession() {
  const context = useContext(AdminSessionContext)

  if (!context) {
    throw new Error('useAdminSession must be used within AdminSessionProvider')
  }

  return context
}
