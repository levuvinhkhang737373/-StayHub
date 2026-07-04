export function formatMoneyText(value: string | number | null | undefined): string {
  const numValue = Math.round(Number(value || 0))
  return String(numValue).replace(/\B(?=(\d{3})+(?!\d))/g, '.')
}

export function formatMoneyInput(value: string): string {
  let cleanValue = value
  if (/^\d+\.\d{1,2}$/.test(value)) {
    cleanValue = String(Math.round(Number(value)))
  }
  cleanValue = cleanValue.replace(/\D/g, '')
  if (!cleanValue) return ''
  return cleanValue.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
}

export function parseMoneyInput(value: string): string {
  return value.replace(/\./g, '')
}

export function formatCurrency(value: string | number | null | undefined): string {
  return `${formatMoneyText(value)} VNĐ`
}

function parseUtcDate(value: string | Date): Date {
  if (value instanceof Date) return value

  if (typeof value === 'string') {
    const trimmed = value.trim()
    // Matches YYYY-MM-DD HH:mm:ss or YYYY-MM-DDTHH:mm:ss without timezone offset (e.g. from Laravel DB)
    if (/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(\.\d+)?$/.test(trimmed)) {
      return new Date(`${trimmed.replace(' ', 'T')}+07:00`)
    }
  }

  return new Date(value)
}

export function formatDate(value?: string | Date | null) {
  if (!value) return '—'
  const date = parseUtcDate(value)

  if (Number.isNaN(date.getTime())) return String(value)

  return new Intl.DateTimeFormat('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    timeZone: 'Asia/Ho_Chi_Minh',
  }).format(date)
}

export function formatDateTime(value?: string | Date | null) {
  if (!value) return '—'
  const date = parseUtcDate(value)

  if (Number.isNaN(date.getTime())) return String(value)

  return new Intl.DateTimeFormat('vi-VN', {
    hour: '2-digit',
    minute: '2-digit',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    timeZone: 'Asia/Ho_Chi_Minh',
  }).format(date)
}

export function formatTimeOnly(value?: string | Date | null): string {
  if (!value) return ''
  const date = parseUtcDate(value)

  if (Number.isNaN(date.getTime())) return String(value)

  return new Intl.DateTimeFormat('vi-VN', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZone: 'Asia/Ho_Chi_Minh',
  }).format(date)
}

export function getChatDividerLabel(value?: string | Date | null): string {
  if (!value) return ''
  const date = parseUtcDate(value)
  if (Number.isNaN(date.getTime())) return String(value)

  const today = new Date()
  const yesterday = new Date()
  yesterday.setDate(today.getDate() - 1)

  const dateStr = formatDate(date)
  const todayStr = formatDate(today)
  const yesterdayStr = formatDate(yesterday)

  if (dateStr === todayStr) {
    return 'Hôm nay'
  } else if (dateStr === yesterdayStr) {
    return 'Hôm qua'
  } else {
    return dateStr
  }
}
