export interface ApiEnvelope<T> {
  status: boolean
  message: string
  errorCode: number | null
  result: T
}
