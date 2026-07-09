export type DashboardMonthOption = {
  value: number
  label: string
}

export function getDashboardMonthFilterOptions(selectedYear: number, currentDate = new Date()): DashboardMonthOption[] {
  const currentYear = currentDate.getFullYear()
  const currentMonth = currentDate.getMonth() + 1
  const latestVisibleMonth = selectedYear >= currentYear ? currentMonth : 12

  return Array.from({ length: latestVisibleMonth }).map((_, index) => ({
    value: index + 1,
    label: `Tháng ${index + 1}`,
  }))
}
