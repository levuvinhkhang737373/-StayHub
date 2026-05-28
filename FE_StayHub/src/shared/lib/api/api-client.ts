import axios, { AxiosError, type AxiosRequestConfig } from 'axios'
import { appConfig } from '../../config/app-config'
import type { ApiEnvelope } from '../../types/api/envelope'
import { dispatchAdminSessionInvalidated } from './admin-session-events'

export type ApiValidationErrors = Record<string, string[]>

export class ApiError extends Error {
  public readonly statusCode: number
  public readonly errorCode: number | null
  public readonly validationErrors: ApiValidationErrors | null

  constructor(message: string, statusCode: number, errorCode: number | null = null, validationErrors: ApiValidationErrors | null = null) {
    super(message)
    this.name = 'ApiError'
    this.statusCode = statusCode
    this.errorCode = errorCode
    this.validationErrors = validationErrors
  }
}

const XSRF_COOKIE_NAME = 'XSRF-TOKEN'

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

function getValidationErrors(payload: Partial<ApiEnvelope<unknown>> | undefined, statusCode: number): ApiValidationErrors | null {
  if (statusCode !== 422) {
    return null
  }

  const result = payload?.result

  if (!result || typeof result !== 'object' || Array.isArray(result)) {
    return null
  }

  const errors: ApiValidationErrors = {}

  Object.entries(result as Record<string, unknown>).forEach(([field, messages]) => {
    if (Array.isArray(messages)) {
      const fieldMessages = messages.filter((message): message is string => typeof message === 'string')

      if (fieldMessages.length > 0) {
        errors[field] = fieldMessages
      }
    }

    if (typeof messages === 'string') {
      errors[field] = [messages]
    }
  })

  return Object.keys(errors).length > 0 ? errors : null
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

      const isLockedAdminSession = statusCode === 403 && payload?.message === 'Tài khoản của bạn đã bị khóa'

      if ((statusCode === 401 || isLockedAdminSession) && config.url?.startsWith('admin/')) {
        dispatchAdminSessionInvalidated()
        if (!window.location.pathname.startsWith('/admin/login')) {
          window.location.replace('/admin/login')
        }
      }

      throw new ApiError(
        getSafeErrorMessage(payload, statusCode),
        statusCode,
        payload?.errorCode ?? null,
        getValidationErrors(payload, statusCode),
      )
    }

    throw error
  }
}
