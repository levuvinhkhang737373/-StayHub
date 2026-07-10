import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { listenAdminSessionInvalidated } from '../../../../shared/lib/api/admin-session-events'
import { fetchAdminMe } from '../services/admin-auth.service'
import type { AdminLoginResult } from '../types/admin-auth.model'
import { AdminSessionContext, normalizeAdminSession, type AdminSessionStatus } from './admin-session-store'

const LEGACY_ADMIN_SESSION_KEY = 'stayhub_admin_session'

export function AdminSessionProvider({ children }: { children: ReactNode }) {
  const [session, setSession] = useState<AdminLoginResult | null>(null)
  const [status, setStatus] = useState<AdminSessionStatus>('idle')
  const refreshPromiseRef = useRef<Promise<AdminLoginResult | null> | null>(null)
  const sessionVersionRef = useRef(0)

  const saveSession = useCallback((payload: AdminLoginResult) => {
    const nextSession = normalizeAdminSession(payload)

    sessionVersionRef.current += 1
    setSession(nextSession)
    setStatus(nextSession ? 'authenticated' : 'guest')
  }, [])

  const clearSession = useCallback(() => {
    sessionVersionRef.current += 1
    setSession(null)
    setStatus('guest')
  }, [])

  const refreshSession = useCallback(() => {
    if (refreshPromiseRef.current) {
      return refreshPromiseRef.current
    }

    const startedVersion = sessionVersionRef.current
    setStatus('checking')

    const refreshPromise = fetchAdminMe()
      .then((response): AdminLoginResult | null => {
        const nextSession = normalizeAdminSession(response.result)

        if (sessionVersionRef.current !== startedVersion) {
          return null
        }

        setSession(nextSession)
        setStatus(nextSession ? 'authenticated' : 'guest')

        return nextSession
      })
      .catch((): null => {
        if (sessionVersionRef.current === startedVersion) {
          setSession(null)
          setStatus('guest')
        }

        return null
      })
      .finally(() => {
        if (refreshPromiseRef.current === refreshPromise) {
          refreshPromiseRef.current = null
        }
      })

    refreshPromiseRef.current = refreshPromise
    return refreshPromise
  }, [])

  useEffect(() => {
    localStorage.removeItem(LEGACY_ADMIN_SESSION_KEY)
    return listenAdminSessionInvalidated(clearSession)
  }, [clearSession])

  const normalizedSession = useMemo(() => normalizeAdminSession(session), [session])
  const isAuthenticated = useMemo(() => Boolean(normalizedSession?.admin), [normalizedSession])
  const isChecking = status === 'checking'

  const value = useMemo(() => ({
    session: normalizedSession,
    status,
    isAuthenticated,
    isChecking,
    saveSession,
    clearSession,
    refreshSession,
  }), [clearSession, isAuthenticated, isChecking, normalizedSession, refreshSession, saveSession, status])

  return <AdminSessionContext.Provider value={value}>{children}</AdminSessionContext.Provider>
}
