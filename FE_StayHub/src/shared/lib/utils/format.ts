export function formatMoneyText(value: string | number | null | undefined): string {
  const [integerPart, decimalPart] = String(value || '0').split('.')
  const formattedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.')

  if (!decimalPart || /^0+$/.test(decimalPart)) return formattedInteger

  return `${formattedInteger},${decimalPart}`
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
