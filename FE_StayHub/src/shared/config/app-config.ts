const LOCAL_API_URL = 'http://localhost:8080/api'
const PUBLIC_API_URL = 'https://api.stayhub.id.vn/api'

function formatApiBaseUrl(url: string): string {
  return url.replace(/\/+$/, '')
}

function resolveApiUrl(): string {
  if (window.location.hostname === 'stayhub.id.vn') {
    return PUBLIC_API_URL
  }

  return import.meta.env.VITE_API_URL ?? LOCAL_API_URL
}

const apiUrl = formatApiBaseUrl(resolveApiUrl())

export const appConfig = {
  apiUrl,
  apiOrigin: new URL(apiUrl).origin,
}
