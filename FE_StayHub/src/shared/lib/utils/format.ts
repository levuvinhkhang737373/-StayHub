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

export function formatDate(value?: string | Date | null) {
  if (!value) return '—'
  const date = new Date(value)

  if (Number.isNaN(date.getTime())) return String(value)

  return new Intl.DateTimeFormat('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(date)
}

export function formatDateTime(value?: string | Date | null) {
  if (!value) return '—'
  const date = new Date(value)

  if (Number.isNaN(date.getTime())) return String(value)

  return new Intl.DateTimeFormat('vi-VN', {
    hour: '2-digit',
    minute: '2-digit',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(date)
}
