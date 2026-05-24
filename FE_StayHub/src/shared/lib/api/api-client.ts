import axios, { AxiosError, type AxiosRequestConfig } from 'axios'
import { appConfig } from '../../config/app-config'
import type { ApiEnvelope } from '../../types/api/envelope'

export class ApiError extends Error {
  public readonly statusCode: number
  public readonly errorCode: number | null

  constructor(message: string, statusCode: number, errorCode: number | null = null) {
    super(message)
    this.name = 'ApiError'
    this.statusCode = statusCode
    this.errorCode = errorCode
  }
}

const XSRF_COOKIE_NAME = 'XSRF-TOKEN'
const ADMIN_SESSION_KEY = 'stayhub_admin_session'

function getCookieValue(name: string): string | null {
  const cookie = document.cookie
    .split('; ')
    .find((item) => item.startsWith(`${name}=`))

  return cookie ? decodeURIComponent(cookie.split('=').slice(1).join('=')) : null
}

const apiClient = axios.create({
  baseURL: appConfig.apiUrl,
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: XSRF_COOKIE_NAME,
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: {
    Accept: 'application/json',
  },
})

export async function getCsrfCookie() {
  await axios.get(`${appConfig.apiOrigin}/sanctum/csrf-cookie`, {
    withCredentials: true,
    withXSRFToken: true,
    xsrfCookieName: XSRF_COOKIE_NAME,
    xsrfHeaderName: 'X-XSRF-TOKEN',
    headers: {
      Accept: 'application/json',
    },
  })
}

apiClient.interceptors.request.use((config) => {
  const xsrfToken = getCookieValue(XSRF_COOKIE_NAME)

  if (xsrfToken) {
    config.headers.set('X-XSRF-TOKEN', xsrfToken)
  }

  return config
})

function getSafeErrorMessage(payload: Partial<ApiEnvelope<unknown>> | undefined, statusCode: number) {
  if (!statusCode || statusCode >= 500) {
    return 'Hệ thống đang gặp sự cố. Vui lòng thử lại sau.'
  }

  return payload?.message ?? 'Không thể kết nối tới hệ thống StayHub.'
}

export async function apiRequest<T>(config: AxiosRequestConfig): Promise<ApiEnvelope<T>> {
  try {
    const response = await apiClient.request<ApiEnvelope<T>>(config)
    return response.data
  } catch (error) {
    if (error instanceof AxiosError && error.response?.status === 419) {
      await getCsrfCookie()
      const response = await apiClient.request<ApiEnvelope<T>>(config)
      return response.data
    }

    if (error instanceof AxiosError) {
      const payload = error.response?.data as Partial<ApiEnvelope<unknown>> | undefined
      const statusCode = error.response?.status ?? 0

      if (statusCode === 401 && config.url?.startsWith('admin/')) {
        localStorage.removeItem(ADMIN_SESSION_KEY)
        if (!window.location.pathname.startsWith('/admin/login')) {
          window.location.replace('/admin/login')
        }
      }

      throw new ApiError(
        getSafeErrorMessage(payload, statusCode),
        statusCode,
        payload?.errorCode ?? null,
      )
    }

    throw error
  }
}
