import { appConfig } from '../../config/app-config'

const LOCAL_HOSTS = new Set(['localhost', '127.0.0.1', '0.0.0.0', '10.0.2.2'])

export function resolveAssetUrl(path: string | null | undefined): string {
  if (!path) {
    return ''
  }

  if (/^https?:\/\//i.test(path)) {
    try {
      const url = new URL(path)
      const currentOrigin = new URL(appConfig.apiOrigin)

      if (LOCAL_HOSTS.has(url.hostname) && !LOCAL_HOSTS.has(currentOrigin.hostname)) {
        url.protocol = currentOrigin.protocol
        url.host = currentOrigin.host
      }

      return url.toString()
    } catch {
      return path
    }
  }

  if (path.startsWith('/')) {
    return `${appConfig.apiOrigin}${path}`
  }

  return `${appConfig.apiOrigin}/${path}`
}
