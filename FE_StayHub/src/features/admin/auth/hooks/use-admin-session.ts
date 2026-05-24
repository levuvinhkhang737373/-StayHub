import { useMemo, useState } from 'react'
import type { AdminLoginResult } from '../types/admin-auth.model'

export const ADMIN_SESSION_KEY = 'stayhub_admin_session'
export const SUPER_ADMIN_ROLE = 'quan_tri_tong'
export const SUPER_ADMIN_ROLE_ID = 2
export const BUILDING_MANAGER_ROLE = 'quan_ly_toa_nha'
export const BUILDING_MANAGER_ROLE_ID = 1

export function isSuperAdminRole(role?: string | number | null): boolean {
  return role === SUPER_ADMIN_ROLE || role === SUPER_ADMIN_ROLE_ID || role === String(SUPER_ADMIN_ROLE_ID)
}

export function isBuildingManagerRole(role?: string | number | null): boolean {
  return role === BUILDING_MANAGER_ROLE || role === BUILDING_MANAGER_ROLE_ID || role === String(BUILDING_MANAGER_ROLE_ID)
}

function getStoredSession(): AdminLoginResult | null {
  const value = localStorage.getItem(ADMIN_SESSION_KEY)

  if (!value) return null

  try {
    return JSON.parse(value) as AdminLoginResult
  } catch {
    localStorage.removeItem(ADMIN_SESSION_KEY)
    return null
  }
}

export function useAdminSession() {
  const [session, setSession] = useState<AdminLoginResult | null>(() => getStoredSession())

  const isAuthenticated = useMemo(() => Boolean(session?.admin), [session])

  function saveSession(payload: AdminLoginResult) {
    localStorage.setItem(ADMIN_SESSION_KEY, JSON.stringify(payload))
    setSession(payload)
  }

  function clearSession() {
    localStorage.removeItem(ADMIN_SESSION_KEY)
    setSession(null)
  }

  return {
    session,
    isAuthenticated,
    saveSession,
    clearSession,
  }
}
