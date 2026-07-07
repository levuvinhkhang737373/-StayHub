import { ApiError } from '../../../../shared/lib/api/api-client'

export function getVisibleErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError && (!error.statusCode || error.statusCode >= 500)) return fallback
  if (error instanceof ApiError) return error.message || fallback
  if (error instanceof Error) return error.message
  return fallback
}

export function getVisibleFilterErrorMessage(error: unknown, fallback: string, hasActiveFilters: boolean): string | null {
  if (hasActiveFilters && error instanceof ApiError && (!error.statusCode || error.statusCode >= 500)) {
    return null
  }

  return getVisibleErrorMessage(error, fallback)
}
