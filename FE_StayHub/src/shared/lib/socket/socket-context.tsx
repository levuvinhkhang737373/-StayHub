import { createContext, useContext, useEffect, useState, type ReactNode } from 'react'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import axios from 'axios'
import { useAdminSession } from '../../../features/admin/auth/hooks/use-admin-session'
import { appConfig } from '../../config/app-config'

if (typeof window !== 'undefined') {
  ;(window as any).Pusher = Pusher
}

interface AdminSocketContextValue {
  echo: Echo<any> | null
}

const AdminSocketContext = createContext<AdminSocketContextValue | null>(null)

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

    const REVERB_KEY = 'rhtxfafogu4wbww3eufp'
    const REVERB_HOST = window.location.hostname
    
    let REVERB_PORT = 8009
    if (window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
      REVERB_PORT = window.location.port ? parseInt(window.location.port) : (window.location.protocol === 'https:' ? 443 : 80)
    }

    const REVERB_SCHEME = window.location.protocol === 'https:' ? 'https' : 'http'
    const isTLS = REVERB_SCHEME === 'https'

    const instance = new Echo({
      broadcaster: 'reverb',
      key: REVERB_KEY,
      wsHost: REVERB_HOST,
      wsPort: REVERB_PORT,
      wssPort: REVERB_PORT,
      forceTLS: isTLS,
      enabledTransports: isTLS ? ['wss'] : ['ws'],
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

export function useAdminSocket() {
  const context = useContext(AdminSocketContext)
  if (!context) {
    throw new Error('useAdminSocket must be used within AdminSocketProvider')
  }
  return context
}
