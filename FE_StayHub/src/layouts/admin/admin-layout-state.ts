export type AdminLayoutState = 'checking' | 'authenticated' | 'guest'

export function resolveAdminLayoutState(authStatus: AdminLayoutState, hasSessionAdmin: boolean): AdminLayoutState {
  if (authStatus === 'checking') return 'checking'
  if (!hasSessionAdmin) return 'guest'
  return 'authenticated'
}
