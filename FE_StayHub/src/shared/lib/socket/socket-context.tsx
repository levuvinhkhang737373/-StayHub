import { createContext, useContext, useEffect, useState, type ReactNode } from 'react'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import axios from 'axios'
import { useAdminSession } from '../../../features/admin/auth/hooks/use-admin-session'
import { appConfig } from '../../config/app-config'

if (typeof window !== 'undefined') {
  ; (window as any).Pusher = Pusher
}

interface SocketContextValue {
  echo: Echo<any> | null
}

const AdminSocketContext = createContext<SocketContextValue | null>(null)
const TenantSocketContext = createContext<SocketContextValue | null>(null)

function createEchoInstance() {
  const isTLS = appConfig.reverbScheme === 'https'

  return new Echo({
    broadcaster: 'reverb',
    key: appConfig.reverbKey,
    wsHost: appConfig.reverbHost,
    wsPort: appConfig.reverbPort,
    wssPort: appConfig.reverbPort,
    forceTLS: isTLS,
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel: any) => {
      return {
        authorize: (socketId: string, callback: any) => {
          const xsrfToken = document.cookie
            .split('; ')
            .find((item) => item.startsWith('XSRF-TOKEN='))
            ?.split('=')[1]

          axios.post(
            `${appConfig.apiOrigin}/broadcasting/auth`,
            {
              socket_id: socketId,
              channel_name: channel.name,
            },
            {
              withCredentials: true,
              headers: {
                Accept: 'application/json',
                ...(xsrfToken ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfToken) } : {}),
              },
            }
          )
            .then((response) => {
              callback(false, response.data)
            })
            .catch((error) => {
              console.error('WS Auth failed:', error)
              callback(true, error)
            })
        },
      }
    },
  })
}

export function AdminSocketProvider({ children }: { children: ReactNode }) {
  const { session } = useAdminSession()
  const [echo, setEcho] = useState<Echo<any> | null>(null)
  const adminId = session?.admin?.id

  useEffect(() => {
    if (!adminId) {
      if (echo) {
        echo.disconnect()
        setEcho(null)
      }
      return
    }

    const instance = createEchoInstance()

    setEcho(instance)

    return () => {
      instance.disconnect()
      setEcho(null)
    }
  }, [adminId])

  return (
    <AdminSocketContext.Provider value={{ echo }}>
      {children}
    </AdminSocketContext.Provider>
  )
}

export function TenantSocketProvider({ children }: { children: ReactNode }) {
  const [echo, setEcho] = useState<Echo<any> | null>(null)

  useEffect(() => {
    const instance = createEchoInstance()
    setEcho(instance)

    return () => {
      instance.disconnect()
      setEcho(null)
    }
  }, [])

  return (
    <TenantSocketContext.Provider value={{ echo }}>
      {children}
    </TenantSocketContext.Provider>
  )
}

export function useAdminSocket() {
  const context = useContext(AdminSocketContext)
  if (!context) {
    throw new Error('useAdminSocket must be used within AdminSocketProvider')
  }
  return context
}

export function useTenantSocket() {
  const context = useContext(TenantSocketContext)
  if (!context) {
    throw new Error('useTenantSocket must be used within TenantSocketProvider')
  }
  return context
}
