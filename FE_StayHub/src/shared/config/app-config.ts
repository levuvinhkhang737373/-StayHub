const LOCAL_API_URL = 'http://localhost:8080/api/v1'
const PUBLIC_API_URL = `${window.location.origin}/api/v1`

function formatApiBaseUrl(url: string): string {
  return url.replace(/\/+$/, '')
}

function resolveApiUrl(): string {
  if (window.location.hostname === 'stayhub.id.vn' || window.location.hostname === 'www.stayhub.id.vn') {
    return PUBLIC_API_URL
  }

  return import.meta.env.VITE_API_URL ?? LOCAL_API_URL
}

const apiUrl = formatApiBaseUrl(resolveApiUrl())

function resolveReverbHost(): string {
  if (import.meta.env.VITE_REVERB_HOST) {
    return import.meta.env.VITE_REVERB_HOST
  }
  if (window.location.hostname === 'stayhub.id.vn' || window.location.hostname === 'www.stayhub.id.vn') {
    return 'socket.stayhub.id.vn'
  }
  return window.location.hostname
}

function resolveReverbPort(): number {
  if (import.meta.env.VITE_REVERB_PORT) {
    return Number(import.meta.env.VITE_REVERB_PORT)
  }
  if (window.location.hostname === 'stayhub.id.vn' || window.location.hostname === 'www.stayhub.id.vn') {
    return 443
  }
  if (window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
    return window.location.port ? Number(window.location.port) : (window.location.protocol === 'https:' ? 443 : 80)
  }

  return 8009
}

export const appConfig = {
  apiUrl,
  apiOrigin: new URL(apiUrl).origin,
  reverbKey: import.meta.env.VITE_REVERB_APP_KEY ?? 'rhtxfafogu4wbww3eufp',
  reverbHost: resolveReverbHost(),
  reverbPort: resolveReverbPort(),
  reverbScheme: window.location.protocol === 'https:' ? 'https' : 'http',
}
