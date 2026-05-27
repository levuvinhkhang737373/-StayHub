const ADMIN_SESSION_INVALIDATED_EVENT = 'stayhub:admin-session-invalidated'

export function dispatchAdminSessionInvalidated() {
  window.dispatchEvent(new Event(ADMIN_SESSION_INVALIDATED_EVENT))
}

export function listenAdminSessionInvalidated(listener: () => void) {
  window.addEventListener(ADMIN_SESSION_INVALIDATED_EVENT, listener)

  return () => window.removeEventListener(ADMIN_SESSION_INVALIDATED_EVENT, listener)
}
