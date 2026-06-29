import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import {
  Activity,
  AlertTriangle,
  ArrowDownRight,
  ArrowLeft,
  ArrowRight,
  ArrowUpRight,
  Banknote,
  Building2,
  FileText,
  Gauge,
  Home,
  Loader2,
  ReceiptText,
  RefreshCcw,
  TrendingUp,
  Users,
  Wrench,
  Zap,
  type LucideIcon,
} from 'lucide-react'
import { AdminSelect } from '../../shared/components/AdminSelect'
import { ApiError } from '../../../../shared/lib/api/api-client'
import { cn } from '../../../../shared/lib/utils/cn'
import { formatMoneyText } from '../../../../shared/lib/utils/format'
import {
  fetchDashboardOverview,
  type DashboardContractExpirationItem,
  type DashboardExpenseChart,
  type DashboardInvoiceStatusItem,
  type DashboardMetric,
  type DashboardOccupancyChart,
  type DashboardOccupancyItem,
  type DashboardOverview,
  type DashboardRevenuePoint,
  type UtilityReadingHistoryItem,
} from '../services/dashboard.service'

const SUPER_ADMIN_ROLE_ID = 2
const CURRENT_YEAR = new Date().getFullYear()
const CURRENT_MONTH = new Date().getMonth() + 1
const YEAR_OPTIONS = Array.from({ length: 7 }).map((_, index) => {
  const year = CURRENT_YEAR - index
  return { value: year, label: String(year) }
})
const MONTH_FILTER_OPTIONS = Array.from({ length: 12 }).map((_, index) => ({
  value: index + 1,
  label: `Tháng ${index + 1}`,
}))

const invoiceColors = ['#f3c56b', '#a65f16', '#0f766e', '#dc2626', '#6f6254']
function formatNumber(value: number | null | undefined) {
  return new Intl.NumberFormat('vi-VN').format(Math.round(Number(value || 0)))
}

function formatCurrencyVnd(value: number | null | undefined) {
  return `${formatMoneyText(Math.round(Number(value || 0)))} VNĐ`
}

function formatMetricValue(metric: DashboardMetric) {
  if (metric.unit === 'money') return formatCurrencyVnd(metric.value)
  if (metric.unit === 'percent') return `${Number(metric.value || 0).toFixed(1)}%`
  return formatNumber(metric.value)
}

function formatMetricChange(metric: DashboardMetric) {
  if (metric.change === null || metric.change === undefined) return null
  const sign = metric.change > 0 ? '+' : ''
  const value = metric.unit === 'money'
    ? formatCurrencyVnd(Math.abs(metric.change))
    : metric.unit === 'percent'
      ? `${Math.abs(metric.change).toFixed(1)}%`
      : formatNumber(Math.abs(metric.change))
  const percent = metric.change_percent === null || metric.change_percent === undefined
    ? ''
    : ` · ${sign}${metric.change_percent}%`

  return `${metric.change >= 0 ? '+' : '-'}${value}${percent}`
}

function hasPositiveValue(values: Array<number | null | undefined>) {
  return values.some((value) => Number(value || 0) > 0)
}

function EmptyState({ title = 'Chưa có dữ liệu', description = 'Dữ liệu sẽ hiển thị khi hệ thống phát sinh giao dịch.' }: { title?: string; description?: string }) {
  return (
    <div className="flex min-h-52 flex-col items-center justify-center rounded-[1.5rem] border border-dashed border-[#3d2a18]/12 bg-[#fff7e8]/45 px-6 py-10 text-center">
      <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#f3c56b]/20 text-[#a65f16]">
        <Activity className="h-5 w-5" />
      </div>
      <p className="text-sm font-black text-[#24170d]">{title}</p>
      <p className="mt-1 max-w-md text-xs font-semibold leading-5 text-[#6f6254]">{description}</p>
    </div>
  )
}

function ChartCard({ title, description, action, children }: { title: string; description: string; action?: ReactNode; children: ReactNode }) {
  return (
    <section className="min-w-0 rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/88 p-5 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md sm:p-6">
      <div className="mb-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h2 className="text-lg font-black tracking-tight text-[#24170d] sm:text-xl">{title}</h2>
          <p className="mt-1 text-sm font-semibold leading-5 text-[#6f6254]">{description}</p>
        </div>
        {action}
      </div>
      {children}
    </section>
  )
}

function MetricCard({ metric, icon: Icon, tone, helper }: { metric: DashboardMetric; icon: LucideIcon; tone: string; helper?: string }) {
  const change = formatMetricChange(metric)
  const isPositive = (metric.change ?? 0) >= 0

  return (
    <article className="group relative overflow-hidden rounded-[1.75rem] border border-[#3d2a18]/10 bg-[#fffaf1]/90 p-5 shadow-lg shadow-[#6b3f1d]/8 transition duration-200 hover:-translate-y-0.5 hover:shadow-2xl hover:shadow-[#6b3f1d]/14">
      <div className={`absolute -right-10 -top-12 h-28 w-28 rounded-full blur-2xl ${tone}`} />
      <div className="relative flex items-start justify-between gap-3">
        <div className="flex h-12 w-12 items-center justify-center rounded-2xl border border-[#3d2a18]/10 bg-[#fff7e8] text-[#a65f16] shadow-inner shadow-[#6b3f1d]/8">
          <Icon className="h-5 w-5" />
        </div>
        {change && (
          <span className={`inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-black ${isPositive ? 'border-[#0f766e]/15 bg-[#0f766e]/10 text-[#0f5f59]' : 'border-rose-200 bg-rose-50 text-rose-700'}`}>
            {isPositive ? <ArrowUpRight className="h-3.5 w-3.5" /> : <ArrowDownRight className="h-3.5 w-3.5" />}
            {change}
          </span>
        )}
      </div>
      <div className="relative mt-5">
        <p className="text-sm font-bold text-[#6f6254]">{metric.label}</p>
        <strong className="mt-2 block break-words text-2xl font-black tracking-tight text-[#24170d] sm:text-[1.7rem]">{formatMetricValue(metric)}</strong>
        <p className="mt-3 text-xs font-semibold leading-5 text-[#8b5e34]">
          {helper || (metric.overdue_count !== undefined ? `${formatNumber(metric.count || 0)} hóa đơn cần thu · ${formatNumber(metric.overdue_count)} quá hạn` : '')}
        </p>
      </div>
    </article>
  )
}

function RevenueComboChart({ data }: { data: DashboardRevenuePoint[] }) {
  const [activeMetric, setActiveMetric] = useState<'revenue' | 'expenses' | 'profit'>('revenue')
  const metricOptions = {
    revenue: {
      label: 'Doanh thu',
      summaryLabel: 'Tổng doanh thu',
      color: '#f3c56b',
      darkColor: '#a65f16',
      topColor: '#fde7a7',
      key: 'revenue' as const,
    },
    expenses: {
      label: 'Chi phí',
      summaryLabel: 'Tổng chi phí',
      color: '#0f766e',
      darkColor: '#083f3b',
      topColor: '#5eead4',
      key: 'expenses' as const,
    },
    profit: {
      label: 'Lợi nhuận',
      summaryLabel: 'Tổng lợi nhuận',
      color: '#d97706',
      darkColor: '#7c2d12',
      topColor: '#fed7aa',
      negativeColor: '#dc2626',
      negativeDarkColor: '#991b1b',
      negativeTopColor: '#fecaca',
      key: 'profit' as const,
    },
  }
  const activeOption = metricOptions[activeMetric]

  if (!data.length || !hasPositiveValue(data.flatMap((item) => [item.revenue, item.expenses, Math.abs(item.profit)]))) {
    return <EmptyState title="Chưa có dữ liệu tài chính" description="Doanh thu, chi phí và lợi nhuận sẽ xuất hiện sau khi phát sinh thanh toán hoặc phiếu chi." />
  }

  const points = data.map((item, index) => ({ item, index, value: item[activeOption.key] }))
  const total = points.reduce((sum, point) => sum + point.value, 0)
  const average = data.length ? total / data.length : 0
  const activeMonths = points.filter((point) => point.value !== 0).length
  const highestPoint = points.reduce((best, point) => point.value > best.value ? point : best, points[0])
  const lowestPoint = points.reduce((best, point) => point.value < best.value ? point : best, points[0])
  const width = 820
  const height = 300
  const paddingLeft = 86
  const paddingRight = 34
  const paddingTop = 34
  const paddingBottom = 58
  const chartWidth = width - paddingLeft - paddingRight
  const chartHeight = height - paddingTop - paddingBottom
  const maxValue = Math.max(0, ...points.map((point) => point.value))
  const minValue = Math.min(0, ...points.map((point) => point.value))
  const range = maxValue - minValue || 1
  const zeroY = paddingTop + ((maxValue - 0) / range) * chartHeight
  const slotWidth = chartWidth / data.length
  const barWidth = Math.max(22, Math.min(46, slotWidth * 0.42))
  const depthX = 12
  const depthY = 9
  const yForValue = (value: number) => paddingTop + ((maxValue - value) / range) * chartHeight
  const xForCenter = (index: number) => paddingLeft + slotWidth * index + slotWidth / 2
  const gridLines = Array.from({ length: 4 }).map((_, index) => {
    const ratio = index / 3
    const value = maxValue - range * ratio
    return { y: paddingTop + chartHeight * ratio, value }
  })
  const gradientId = `finance-${activeMetric}-front`
  const negativeGradientId = `finance-${activeMetric}-negative`

  return (
    <div className="grid gap-5 lg:grid-cols-[1fr_280px]">
      <div className="min-w-0">
        <div className="mb-5 flex flex-wrap gap-2 text-xs font-black text-[#6f6254]">
          {(Object.keys(metricOptions) as Array<'revenue' | 'expenses' | 'profit'>).map((type) => {
            const option = metricOptions[type]
            const isActive = activeMetric === type

            return (
              <button
                key={type}
                type="button"
                onClick={() => setActiveMetric(type)}
                className={cn(
                  'inline-flex items-center gap-2 rounded-full border px-3.5 py-2 transition duration-200 focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20',
                  isActive ? 'border-[#3d2a18]/12 bg-[#24170d] text-[#fff4df] shadow-lg shadow-[#24170d]/10' : 'border-[#3d2a18]/8 bg-[#fffaf1] hover:border-[#f3c56b]/40 hover:bg-[#fff7e8] hover:text-[#24170d]',
                )}
                aria-pressed={isActive}
              >
                <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: option.color }} />
                {option.label}
              </button>
            )
          })}
        </div>
        <div className="overflow-x-auto">
          <svg viewBox={`0 0 ${width} ${height}`} className="min-w-[720px] rounded-[1.5rem] bg-[linear-gradient(180deg,#fff8ea_0%,#fff1d6_100%)]">
            <defs>
              <linearGradient id={gradientId} x1="0" x2="0" y1="0" y2="1">
                <stop offset="0%" stopColor={activeOption.topColor} />
                <stop offset="58%" stopColor={activeOption.color} />
                <stop offset="100%" stopColor={activeOption.darkColor} />
              </linearGradient>
              {'negativeColor' in activeOption && (
                <linearGradient id={negativeGradientId} x1="0" x2="0" y1="0" y2="1">
                  <stop offset="0%" stopColor={activeOption.negativeTopColor} />
                  <stop offset="58%" stopColor={activeOption.negativeColor} />
                  <stop offset="100%" stopColor={activeOption.negativeDarkColor} />
                </linearGradient>
              )}
              <filter id="financeBarShadow" x="-20%" y="-20%" width="150%" height="160%">
                <feDropShadow dx="0" dy="9" stdDeviation="7" floodColor="#3d2a18" floodOpacity="0.14" />
              </filter>
            </defs>
            {gridLines.map((line) => (
              <g key={line.y}>
                <line x1={paddingLeft} x2={width - paddingRight} y1={line.y} y2={line.y} stroke="#3d2a18" strokeOpacity="0.08" strokeDasharray="5 6" />
                <text x={paddingLeft - 12} y={line.y + 4} textAnchor="end" className="fill-[#8b5e34] text-[11px] font-bold">
                  {formatMoneyText(Math.round(line.value))}
                </text>
              </g>
            ))}
            <line x1={paddingLeft} x2={width - paddingRight + depthX} y1={zeroY} y2={zeroY} stroke="#24170d" strokeOpacity="0.18" />
            <polygon points={`${paddingLeft},${zeroY} ${width - paddingRight},${zeroY} ${width - paddingRight + depthX},${zeroY - depthY} ${paddingLeft + depthX},${zeroY - depthY}`} fill="#3d2a18" opacity="0.06" />
            {points.map((point) => {
              const centerX = xForCenter(point.index)
              const valueY = yForValue(point.value)
              const x = centerX - barWidth / 2
              const rawHeight = Math.abs(zeroY - valueY)
              const barHeight = point.value === 0 ? 5 : Math.max(9, rawHeight)
              const topY = point.value >= 0 ? zeroY - barHeight : zeroY
              const sideTopY = point.value >= 0 ? topY - depthY : topY + barHeight - depthY
              const frontFill = activeMetric === 'profit' && point.value < 0 ? `url(#${negativeGradientId})` : `url(#${gradientId})`
              const sideFill = activeMetric === 'profit' && point.value < 0 && 'negativeDarkColor' in activeOption ? activeOption.negativeDarkColor : activeOption.darkColor
              const topFill = activeMetric === 'profit' && point.value < 0 && 'negativeTopColor' in activeOption ? activeOption.negativeTopColor : activeOption.topColor
              const labelY = point.value >= 0 ? topY - 9 : topY + barHeight + 18

              return (
                <g key={point.item.month_key} filter="url(#financeBarShadow)">
                  <polygon points={`${x + barWidth},${topY} ${x + barWidth + depthX},${sideTopY} ${x + barWidth + depthX},${sideTopY + barHeight} ${x + barWidth},${topY + barHeight}`} fill={sideFill} opacity="0.72" />
                  <rect x={x} y={topY} width={barWidth} height={barHeight} rx="12" fill={frontFill}>
                    <title>{`${point.item.month} · ${activeOption.label}: ${formatCurrencyVnd(point.value)}`}</title>
                  </rect>
                  <polygon points={`${x},${topY} ${x + depthX},${sideTopY} ${x + barWidth + depthX},${sideTopY} ${x + barWidth},${topY}`} fill={topFill} opacity="0.9" />
                  <text x={centerX} y={labelY} textAnchor="middle" className="fill-[#24170d] text-[10px] font-black">
                    {formatMoneyText(Math.round(point.value))}
                  </text>
                  <text x={centerX} y={height - 24} textAnchor="middle" className="fill-[#8b5e34] text-[11px] font-black">
                    {point.item.month}
                  </text>
                </g>
              )
            })}
          </svg>
        </div>
      </div>
      <div className="space-y-3">
        <div className="rounded-2xl border border-[#3d2a18]/8 bg-[#fff7e8]/72 p-4">
          <p className="text-xs font-black uppercase tracking-[0.14em] text-[#a65f16]">{activeOption.summaryLabel}</p>
          <strong className="mt-2 block text-xl font-black text-[#24170d]">{formatCurrencyVnd(total)}</strong>
          <p className="mt-1 text-xs font-bold text-[#6f6254]">{formatNumber(activeMonths)} tháng phát sinh · TB {formatCurrencyVnd(average)}</p>
        </div>
        <div className="rounded-2xl border border-[#3d2a18]/8 bg-[#fffaf1] px-4 py-3">
          <p className="text-xs font-black uppercase tracking-[0.14em] text-[#a65f16]">Cao nhất</p>
          <div className="mt-2 flex items-start justify-between gap-3 text-sm font-black text-[#24170d]">
            <span>{highestPoint.item.month}</span>
            <span className="text-right">{formatCurrencyVnd(highestPoint.value)}</span>
          </div>
        </div>
        <div className="rounded-2xl border border-[#3d2a18]/8 bg-[#fffaf1] px-4 py-3">
          <p className="text-xs font-black uppercase tracking-[0.14em] text-[#a65f16]">Thấp nhất</p>
          <div className="mt-2 flex items-start justify-between gap-3 text-sm font-black text-[#24170d]">
            <span>{lowestPoint.item.month}</span>
            <span className="text-right">{formatCurrencyVnd(lowestPoint.value)}</span>
          </div>
        </div>
      </div>
    </div>
  )
}

function ExpenseChart({ chart }: { chart: DashboardExpenseChart }) {
  const data = chart.by_month
  if (!data.length || !hasPositiveValue(data.map((item) => item.amount))) {
    return <EmptyState title="Chưa có dữ liệu chi tiền" description="Biểu đồ chi tiền sẽ hiển thị khi có phiếu chi đã ghi nhận trong kỳ lọc." />
  }

  const width = 820
  const height = 280
  const paddingLeft = 78
  const paddingRight = 28
  const paddingTop = 30
  const paddingBottom = 52
  const chartWidth = width - paddingLeft - paddingRight
  const chartHeight = height - paddingTop - paddingBottom
  const maxAmount = Math.max(...data.map((item) => item.amount), 1)
  const slotWidth = chartWidth / data.length
  const barWidth = Math.max(14, Math.min(34, slotWidth * 0.38))

  return (
    <div className="grid gap-5 lg:grid-cols-[1fr_280px]">
      <div className="min-w-0 overflow-x-auto">
        <svg viewBox={`0 0 ${width} ${height}`} className="min-w-[720px] rounded-[1.5rem] bg-[#fff7e8]/55">
          <defs>
            <linearGradient id="expenseSingleGradient" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor="#dc2626" />
              <stop offset="100%" stopColor="#a65f16" />
            </linearGradient>
          </defs>
          {[0, 1, 2, 3].map((index) => {
            const y = paddingTop + (chartHeight * index) / 3
            const value = maxAmount - (maxAmount * index) / 3
            return (
              <g key={index}>
                <line x1={paddingLeft} x2={width - paddingRight} y1={y} y2={y} stroke="#3d2a18" strokeOpacity="0.08" strokeDasharray="5 6" />
                <text x={paddingLeft - 12} y={y + 4} textAnchor="end" className="fill-[#8b5e34] text-[11px] font-bold">
                  {formatMoneyText(Math.round(value))}
                </text>
              </g>
            )
          })}
          {data.map((item, index) => {
            const visualHeight = Math.max(8, (item.amount / maxAmount) * chartHeight)
            const x = paddingLeft + slotWidth * index + (slotWidth - barWidth) / 2
            const y = paddingTop + chartHeight - visualHeight
            return (
              <g key={item.month_key}>
                <rect x={x} y={y} width={barWidth} height={visualHeight} rx="14" fill="url(#expenseSingleGradient)">
                  <title>{`${item.month}: ${formatCurrencyVnd(item.amount)} · ${formatNumber(item.count)} phiếu chi`}</title>
                </rect>
                <text x={x + barWidth / 2} y={height - 22} textAnchor="middle" className="fill-[#8b5e34] text-[11px] font-black">
                  {item.month}
                </text>
              </g>
            )
          })}
        </svg>
      </div>
      <div className="space-y-3">
        <div className="rounded-2xl border border-[#3d2a18]/8 bg-[#fff7e8]/72 p-4">
          <p className="text-xs font-black uppercase tracking-[0.14em] text-[#a65f16]">Tổng chi</p>
          <strong className="mt-2 block text-xl font-black text-[#24170d]">{formatCurrencyVnd(chart.summary.total_amount)}</strong>
          <p className="mt-1 text-xs font-bold text-[#6f6254]">{formatNumber(chart.summary.count)} phiếu · TB {formatCurrencyVnd(chart.summary.average_amount)}</p>
        </div>
        {chart.by_category.map((item) => (
          <div key={item.label} className="rounded-2xl border border-[#3d2a18]/8 bg-[#fffaf1] px-4 py-3">
            <div className="flex items-start justify-between gap-3 text-sm font-black text-[#24170d]">
              <span className="whitespace-normal break-words leading-5">{item.label}</span>
              <span className="shrink-0 text-right">{formatCurrencyVnd(item.amount)}</span>
            </div>
            <p className="mt-1 text-xs font-bold text-[#8b5e34]">{formatNumber(item.count)} phiếu chi</p>
          </div>
        ))}
      </div>
    </div>
  )
}

function OccupancyBars({ chart }: { chart: DashboardOccupancyChart }) {
  const pageSize = 3
  const [page, setPage] = useState(0)
  const sortedItems = useMemo(() => [...chart.items].sort((first, second) => {
    if (second.current_occupants !== first.current_occupants) return second.current_occupants - first.current_occupants
    if (second.occupancy_rate !== first.occupancy_rate) return second.occupancy_rate - first.occupancy_rate
    return second.total_rooms - first.total_rooms
  }), [chart.items])
  const totalPages = Math.max(1, Math.ceil(sortedItems.length / pageSize))
  const safePage = Math.min(page, totalPages - 1)
  const visibleItems = sortedItems.slice(safePage * pageSize, safePage * pageSize + pageSize)

  if (!chart.items.length) {
    return <EmptyState title="Chưa có dữ liệu phòng" description="Tỷ lệ lấp đầy cần phòng đang hoạt động và sức chứa hợp lệ." />
  }

  return (
    <div className="space-y-4">
      <div className="rounded-[1.5rem] border border-[#3d2a18]/8 bg-[#24170d] p-5 text-[#fff4df]">
        <p className="text-xs font-black uppercase tracking-[0.18em] text-[#f3c56b]">Tỷ lệ lấp đầy</p>
        <div className="mt-3 flex flex-wrap items-end gap-5">
          <strong className="text-4xl font-black">{chart.occupancy_rate.toFixed(1)}%</strong>
          <span className="pb-1 text-sm font-semibold text-[#f8e8c8]/80">
            {formatNumber(chart.summary.current_occupants)}/{formatNumber(chart.summary.total_capacity)} chỗ đang sử dụng
          </span>
        </div>
      </div>
      <div className="space-y-3">
        {visibleItems.map((item) => (
          <OccupancyRow key={item.label} item={item} />
        ))}
      </div>
      {sortedItems.length > pageSize && (
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-[#3d2a18]/8 bg-[#fff7e8]/55 px-3 py-2">
          <span className="text-xs font-black text-[#8b5e34]">
            {safePage * pageSize + 1}-{Math.min((safePage + 1) * pageSize, sortedItems.length)} / {sortedItems.length} {chart.mode === 'building' ? 'tòa' : 'tầng'} nhiều người ở nhất
          </span>
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => setPage((current) => Math.max(0, current - 1))}
              disabled={safePage === 0}
              className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:border-[#f3c56b]/40 hover:text-[#24170d] disabled:cursor-not-allowed disabled:opacity-40"
              aria-label="Xem nhóm trước"
            >
              <ArrowLeft className="h-4 w-4" />
            </button>
            <div className="flex items-center gap-1.5">
              {Array.from({ length: totalPages }).map((_, index) => (
                <button
                  key={index}
                  type="button"
                  onClick={() => setPage(index)}
                  className={cn('h-2 rounded-full transition-all', safePage === index ? 'w-6 bg-[#24170d]' : 'w-2 bg-[#3d2a18]/18 hover:bg-[#a65f16]/50')}
                  aria-label={`Xem trang ${index + 1}`}
                  aria-current={safePage === index ? 'page' : undefined}
                />
              ))}
            </div>
            <button
              type="button"
              onClick={() => setPage((current) => Math.min(totalPages - 1, current + 1))}
              disabled={safePage >= totalPages - 1}
              className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:border-[#f3c56b]/40 hover:text-[#24170d] disabled:cursor-not-allowed disabled:opacity-40"
              aria-label="Xem nhóm tiếp theo"
            >
              <ArrowRight className="h-4 w-4" />
            </button>
          </div>
        </div>
      )}
    </div>
  )
}

function OccupancyRow({ item }: { item: DashboardOccupancyItem }) {
  return (
    <div className="rounded-2xl border border-[#3d2a18]/8 bg-[#fff7e8]/72 p-4">
      <div className="mb-2 flex items-center justify-between gap-3 text-sm">
        <span className="font-black text-[#24170d]">{item.label}</span>
        <span className="font-black tabular-nums text-[#0f766e]">{item.occupancy_rate.toFixed(1)}%</span>
      </div>
      <div className="h-3 overflow-hidden rounded-full bg-[#3d2a18]/8">
        <div className="h-full rounded-full bg-gradient-to-r from-[#0f766e] via-[#f3c56b] to-[#a65f16]" style={{ width: `${Math.min(100, item.occupancy_rate)}%` }} />
      </div>
      <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[11px] font-bold text-[#8b5e34]">
        <span>{formatNumber(item.current_occupants)}/{formatNumber(item.total_capacity)} chỗ</span>
        <span>{formatNumber(item.total_rooms)} phòng</span>
        <span>{formatNumber(item.available_slots)} chỗ trống</span>
      </div>
    </div>
  )
}

function InvoiceDonutChart({ data }: { data: DashboardInvoiceStatusItem[] }) {
  const total = data.reduce((sum, item) => sum + item.count, 0)
  if (total === 0) {
    return <EmptyState title="Chưa có hóa đơn" description="Trạng thái công nợ sẽ hiển thị khi hệ thống phát sinh hóa đơn." />
  }

  const radius = 74
  const circumference = 2 * Math.PI * radius
  const donutSegments = data.reduce<{
    offset: number
    segments: Array<{ item: DashboardInvoiceStatusItem; index: number; length: number; offset: number }>
  }>((state, item, index) => {
    const length = (item.count / total) * circumference
    return {
      offset: state.offset + length,
      segments: [...state.segments, { item, index, length, offset: state.offset }],
    }
  }, { offset: 0, segments: [] }).segments

  return (
    <div className="grid gap-5 lg:grid-cols-[220px_minmax(0,1fr)] lg:items-center">
      <svg viewBox="0 0 220 220" className="mx-auto h-56 w-56">
        <circle cx="110" cy="110" r={radius} fill="none" stroke="#3d2a18" strokeOpacity="0.08" strokeWidth="28" />
        {donutSegments.map(({ item, index, length, offset }) => {
          return (
            <circle
              key={item.status}
              cx="110"
              cy="110"
              r={radius}
              fill="none"
              stroke={invoiceColors[index % invoiceColors.length]}
              strokeWidth="28"
              strokeLinecap="round"
              strokeDasharray={`${length} ${circumference - length}`}
              strokeDashoffset={-offset}
              transform="rotate(-90 110 110)"
            >
              <title>{`${item.label}: ${formatNumber(item.count)} hóa đơn · còn thu ${formatCurrencyVnd(item.remaining_amount)}`}</title>
            </circle>
          )
        })}
        <text x="110" y="104" textAnchor="middle" className="fill-[#24170d] text-3xl font-black">{formatNumber(total)}</text>
        <text x="110" y="128" textAnchor="middle" className="fill-[#8b5e34] text-xs font-bold">hóa đơn</text>
      </svg>
      <div className="min-w-0 space-y-3">
        {data.map((item, index) => (
          <div key={item.status} className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-4 rounded-2xl border border-[#3d2a18]/8 bg-[#fff7e8]/72 px-4 py-3">
            <span className="flex min-w-0 items-center gap-2 text-sm font-black leading-5 text-[#24170d]">
              <span className="h-3 w-3 shrink-0 rounded-full" style={{ backgroundColor: invoiceColors[index % invoiceColors.length] }} />
              <span className="whitespace-normal break-words">{item.label}</span>
            </span>
            <span className="min-w-[5.75rem] text-right text-xs font-bold leading-4 text-[#6f6254]">
              <strong className="block text-sm text-[#24170d]">{formatNumber(item.count)}</strong>
              {formatCurrencyVnd(item.remaining_amount)}
            </span>
          </div>
        ))}
      </div>
    </div>
  )
}

function ContractExpirationChart({ data }: { data: DashboardContractExpirationItem[] }) {
  if (!hasPositiveValue(data.map((item) => item.count))) {
    return <EmptyState title="Không có hợp đồng sắp hết hạn" description="Các mốc nhắc gia hạn sẽ xuất hiện khi hợp đồng còn dưới 30 ngày." />
  }

  const maxValue = Math.max(...data.map((item) => item.count), 1)

  return (
    <div className="space-y-4">
      {data.map((item, index) => (
        <div key={item.days} className="rounded-2xl border border-[#3d2a18]/8 bg-[#fff7e8]/72 p-4">
          <div className="mb-2 flex items-center justify-between text-sm font-black text-[#24170d]">
            <span>{item.label}</span>
            <span>{formatNumber(item.count)} hợp đồng</span>
          </div>
          <div className="h-4 overflow-hidden rounded-full bg-[#3d2a18]/8">
            <div className="h-full rounded-full" style={{ width: `${Math.max(8, (item.count / maxValue) * 100)}%`, backgroundColor: ['#dc2626', '#f3c56b', '#0f766e'][index] }} />
          </div>
        </div>
      ))}
    </div>
  )
}

function UtilityUsageBarChart({ data }: { data: UtilityReadingHistoryItem[] }) {
  const [activeUtility, setActiveUtility] = useState<'electric' | 'water'>('electric')
  const utilityOptions = {
    electric: {
      label: 'Lượng dùng điện',
      shortLabel: 'Điện',
      unit: 'kWh',
      color: '#f3c56b',
      darkColor: '#a65f16',
      topColor: '#fde7a7',
      key: 'electric_consumption' as const,
      countKey: 'electric_reading_count' as const,
    },
    water: {
      label: 'Lượng dùng nước',
      shortLabel: 'Nước',
      unit: 'm³',
      color: '#0f766e',
      darkColor: '#0b4f4a',
      topColor: '#5eead4',
      key: 'water_consumption' as const,
      countKey: 'water_reading_count' as const,
    },
  }
  const activeOption = utilityOptions[activeUtility]
  const monthPoints = data.map((item, index) => {
    const rawVal = item[activeOption.key]
    const val = typeof rawVal === 'number' ? rawVal : 0
    return {
      item,
      index,
      value: val,
      hasValue: typeof rawVal === 'number',
    }
  })
  const validValues = monthPoints.filter((point) => point.hasValue).map((point) => point.value)

  if (!data.length) {
    return <EmptyState title="Chưa có lượng sử dụng điện nước" description="Biểu đồ sẽ hiển thị sau khi chốt chỉ số đồng hồ theo tháng." />
  }

  const width = 880
  const height = 360
  const paddingLeft = 86
  const paddingRight = 44
  const paddingTop = 42
  const paddingBottom = 72
  const chartWidth = width - paddingLeft - paddingRight
  const chartHeight = height - paddingTop - paddingBottom
  const baseline = paddingTop + chartHeight
  const maxValue = Math.max(...validValues, 1)
  const monthWidth = chartWidth / Math.max(1, data.length)
  const barWidth = Math.min(62, monthWidth * 0.48)
  const depthX = 15
  const depthY = 11
  const xForMonthCenter = (index: number) => paddingLeft + monthWidth * index + monthWidth / 2
  const yForValue = (value: number) => baseline - (value / maxValue) * (chartHeight - 8)
  const gridLines = Array.from({ length: 4 }).map((_, index) => {
    const ratio = index / 3
    const value = maxValue - maxValue * ratio
    return { y: paddingTop + chartHeight * ratio, value }
  })
  const gradientId = `utility-${activeUtility}-front`

  return (
    <div>
      <div className="mb-5 flex flex-wrap gap-2 text-xs font-black text-[#6f6254]">
        {(Object.keys(utilityOptions) as Array<'electric' | 'water'>).map((type) => {
          const option = utilityOptions[type]
          const isActive = activeUtility === type

          return (
            <button
              key={type}
              type="button"
              onClick={() => setActiveUtility(type)}
              className={cn(
                'inline-flex items-center gap-2 rounded-full border px-3.5 py-2 transition duration-200 focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20',
                isActive ? 'border-[#3d2a18]/12 bg-[#24170d] text-[#fff4df] shadow-lg shadow-[#24170d]/10' : 'border-[#3d2a18]/8 bg-[#fffaf1] hover:border-[#f3c56b]/40 hover:bg-[#fff7e8] hover:text-[#24170d]',
              )}
              aria-pressed={isActive}
            >
              <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: option.color }} />
              {option.label}
            </button>
          )
        })}
      </div>
      {validValues.length === 0 ? (
        <EmptyState title={`Chưa có ${activeOption.label.toLowerCase()}`} description={`Chọn ${activeOption.label.toLowerCase()} sẽ hiển thị sau khi chốt đồng hồ theo tháng.`} />
      ) : (
        <div className="overflow-x-auto">
          <svg viewBox={`0 0 ${width} ${height}`} className="min-w-[760px] rounded-[1.65rem] bg-[linear-gradient(180deg,#fff8ea_0%,#fff1d6_100%)]">
            <defs>
              <linearGradient id={gradientId} x1="0" x2="0" y1="0" y2="1">
                <stop offset="0%" stopColor={activeOption.topColor} />
                <stop offset="58%" stopColor={activeOption.color} />
                <stop offset="100%" stopColor={activeOption.darkColor} />
              </linearGradient>
              <filter id="utilityBarShadow" x="-20%" y="-20%" width="150%" height="160%">
                <feDropShadow dx="0" dy="10" stdDeviation="8" floodColor="#3d2a18" floodOpacity="0.16" />
              </filter>
            </defs>
            <text x={paddingLeft} y="26" className="fill-[#24170d] text-[13px] font-black uppercase tracking-[0.16em]">
              {activeOption.label} theo tháng
            </text>
            {gridLines.map((line) => (
              <g key={line.y}>
                <line x1={paddingLeft} x2={width - paddingRight} y1={line.y} y2={line.y} stroke="#3d2a18" strokeOpacity="0.1" strokeDasharray="7 9" />
                <text x={paddingLeft - 14} y={line.y + 4} textAnchor="end" className="fill-[#8b5e34] text-[11px] font-black">
                  {formatNumber(line.value)}
                </text>
              </g>
            ))}
            <line x1={paddingLeft} x2={width - paddingRight + depthX} y1={baseline} y2={baseline} stroke="#3d2a18" strokeOpacity="0.24" strokeWidth="1.5" />
            <polygon points={`${paddingLeft},${baseline} ${width - paddingRight},${baseline} ${width - paddingRight + depthX},${baseline - depthY} ${paddingLeft + depthX},${baseline - depthY}`} fill="#3d2a18" opacity="0.08" />
            {monthPoints.map((point) => {
              const centerX = xForMonthCenter(point.index)
              const x = centerX - barWidth / 2
              const y = yForValue(point.value)
              const barHeight = Math.max(3, baseline - y)
              const topY = baseline - barHeight
              const labelInside = barHeight > 42

              return (
                <g key={`${point.item.month_key || point.item.month}-${activeUtility}`} opacity={point.hasValue ? 1 : 0.4} filter="url(#utilityBarShadow)">
                  <polygon points={`${x + barWidth},${topY} ${x + barWidth + depthX},${topY - depthY} ${x + barWidth + depthX},${baseline - depthY} ${x + barWidth},${baseline}`} fill={activeOption.darkColor} opacity="0.72" />
                  <rect x={x} y={topY} width={barWidth} height={barHeight} rx="8" fill={`url(#${gradientId})`}>
                    <title>{`${point.item.month} · ${activeOption.shortLabel}: ${formatNumber(point.value)} ${activeOption.unit} · ${formatNumber(point.item[activeOption.countKey])} đồng hồ`}</title>
                  </rect>
                  <polygon points={`${x},${topY} ${x + depthX},${topY - depthY} ${x + barWidth + depthX},${topY - depthY} ${x + barWidth},${topY}`} fill={activeOption.topColor} opacity="0.92" />
                  <text x={x + barWidth / 2} y={labelInside ? topY + 25 : topY - 9} textAnchor="middle" className={cn('text-[11px] font-black', labelInside ? 'fill-white' : 'fill-[#24170d]')}>
                    {formatNumber(point.value)}
                  </text>
                  <text x={centerX} y={height - 28} textAnchor="middle" className="fill-[#8b5e34] text-[11px] font-black">
                    {point.item.month}
                  </text>
                </g>
              )
            })}
            <text x="26" y={paddingTop + chartHeight / 2} textAnchor="middle" transform={`rotate(-90 26 ${paddingTop + chartHeight / 2})`} className="fill-[#6f6254] text-[11px] font-black uppercase tracking-[0.16em]">
              {activeOption.unit}
            </text>
          </svg>
        </div>
      )}
    </div>
  )
}

function DashboardSkeleton() {
  return (
    <section className="space-y-6">
      <div className="h-44 animate-pulse rounded-[2rem] bg-[#3d2a18]/10" />
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {Array.from({ length: 6 }).map((_, index) => (
          <div key={index} className="h-44 animate-pulse rounded-[1.75rem] bg-[#3d2a18]/10" />
        ))}
      </div>
      <div className="grid gap-6 lg:grid-cols-2">
        <div className="h-96 animate-pulse rounded-[2rem] bg-[#3d2a18]/10" />
        <div className="h-96 animate-pulse rounded-[2rem] bg-[#3d2a18]/10" />
      </div>
    </section>
  )
}

export function AdminDashboardScreen() {
  const [overview, setOverview] = useState<DashboardOverview | null>(null)
  const [selectedBuildingId, setSelectedBuildingId] = useState('')
  const [selectedYear, setSelectedYear] = useState(CURRENT_YEAR)
  const [selectedMonthFrom, setSelectedMonthFrom] = useState(1)
  const [selectedMonthTo, setSelectedMonthTo] = useState(CURRENT_MONTH)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const loadDashboard = useCallback(async () => {
    try {
      const response = await fetchDashboardOverview({
        building_id: selectedBuildingId ? Number(selectedBuildingId) : null,
        year: selectedYear,
        month_from: selectedMonthFrom,
        month_to: selectedMonthTo,
      })
      setOverview(response.result || null)
    } catch (loadError) {
      const message = loadError instanceof ApiError || loadError instanceof Error
        ? loadError.message
        : 'Không thể tải dashboard. Vui lòng thử lại.'
      setError(message)
    } finally {
      setIsLoading(false)
    }
  }, [selectedBuildingId, selectedYear, selectedMonthFrom, selectedMonthTo])

  useEffect(() => {
    let isCancelled = false

    fetchDashboardOverview({
      building_id: selectedBuildingId ? Number(selectedBuildingId) : null,
      year: selectedYear,
      month_from: selectedMonthFrom,
      month_to: selectedMonthTo,
    })
      .then((response) => {
        if (!isCancelled) {
          setOverview(response.result || null)
        }
      })
      .catch((loadError) => {
        if (!isCancelled) {
          const message = loadError instanceof ApiError || loadError instanceof Error
            ? loadError.message
            : 'Không thể tải dashboard. Vui lòng thử lại.'
          setError(message)
        }
      })
      .finally(() => {
        if (!isCancelled) {
          setIsLoading(false)
        }
      })

    return () => {
      isCancelled = true
    }
  }, [selectedBuildingId, selectedYear, selectedMonthFrom, selectedMonthTo])

  const isSuperAdmin = overview?.meta.role === SUPER_ADMIN_ROLE_ID
  const effectiveBuildingValue = selectedBuildingId || (overview?.meta.selected_building_id ? String(overview.meta.selected_building_id) : '')
  const buildingOptions = useMemo(() => {
    const options = overview?.filters.buildings.map((building) => ({ value: String(building.id), label: building.name })) || []
    return isSuperAdmin ? [{ value: '', label: 'Tất cả tòa nhà' }, ...options] : options
  }, [isSuperAdmin, overview?.filters.buildings])

  if (isLoading && !overview) {
    return <DashboardSkeleton />
  }

  if (error && !overview) {
    return (
      <section className="rounded-[2rem] border border-rose-200 bg-rose-50 p-6 text-rose-800 shadow-lg shadow-rose-900/5">
        <div className="flex items-start gap-3">
          <AlertTriangle className="mt-1 h-5 w-5 shrink-0" />
          <div>
            <h1 className="text-lg font-black">Không thể tải dashboard</h1>
            <p className="mt-1 text-sm font-semibold">{error}</p>
            <button type="button" onClick={() => { setIsLoading(true); setError(null); void loadDashboard() }} className="mt-4 inline-flex items-center gap-2 rounded-2xl bg-rose-700 px-4 py-2 text-sm font-black text-white transition hover:bg-rose-800 focus:outline-none focus:ring-4 focus:ring-rose-200">
              <RefreshCcw className="h-4 w-4" />
              Thử lại
            </button>
          </div>
        </div>
      </section>
    )
  }

  if (!overview) return null

  const kpiCards = [
    { key: 'monthly_revenue', metric: overview.kpis.monthly_revenue, icon: Banknote, tone: 'bg-[#f3c56b]/35', helper: 'Tiền đã xác nhận trong tháng' },
    { key: 'monthly_profit', metric: overview.kpis.monthly_profit, icon: TrendingUp, tone: 'bg-[#0f766e]/25', helper: 'Doanh thu trừ chi phí ghi nhận' },
    { key: 'occupancy_rate', metric: overview.kpis.occupancy_rate, icon: Home, tone: 'bg-[#a65f16]/25', helper: `${formatNumber(overview.occupancy_chart.summary.available_slots)} chỗ còn trống` },
    { key: 'renting_tenants', metric: overview.kpis.renting_tenants, icon: Users, tone: 'bg-[#0f766e]/20', helper: '' },
    { key: 'outstanding_debt', metric: overview.kpis.outstanding_debt, icon: ReceiptText, tone: 'bg-rose-500/18' },
    { key: 'open_maintenance', metric: overview.kpis.open_maintenance, icon: Wrench, tone: 'bg-[#f3c56b]/25', helper: 'Mới tạo, tiếp nhận hoặc đang xử lý' },
  ]

  return (
    <section className="space-y-6 text-[#24170d]">
      <div className="relative overflow-hidden rounded-[2.25rem] border border-[#3d2a18]/10 bg-[#24170d] p-5 text-[#fff4df] shadow-2xl shadow-[#6b3f1d]/18 sm:p-7">
        <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(90deg,#4a2f17_0%,#372719_50%,#123f32_100%)]" />
        <div className="relative flex flex-col gap-5">
          <div className="max-w-3xl">
            <p className="inline-flex items-center gap-2 rounded-full border border-[#f3c56b]/25 bg-[#f3c56b]/12 px-3 py-1.5 text-xs font-black uppercase tracking-[0.22em] text-[#f3c56b]">
              <Gauge className="h-3.5 w-3.5" /> bảng điều khiển
            </p>
          </div>
          <div className="w-full rounded-[1.75rem] border border-[#fff4df]/12 bg-[#fff4df]/10 p-4 backdrop-blur-md">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(240px,1.65fr)_minmax(100px,0.65fr)_minmax(120px,0.8fr)_minmax(120px,0.8fr)]">
              <div>
                <label className="mb-2 block text-xs font-black uppercase tracking-[0.14em] text-[#f3c56b]">Phạm vi tòa nhà</label>
                <AdminSelect
                  value={effectiveBuildingValue}
                  options={buildingOptions}
                  onChange={(value) => { setIsLoading(true); setError(null); setSelectedBuildingId(String(value)) }}
                  disabled={!isSuperAdmin && buildingOptions.length <= 1}
                  placeholder="Chọn tòa nhà"
                  menuMinWidth={460}
                  wrapLabel
                />
              </div>
              <div>
                <label className="mb-2 block text-xs font-black uppercase tracking-[0.14em] text-[#f3c56b]">Năm</label>
                <AdminSelect value={selectedYear} options={YEAR_OPTIONS} menuMinWidth={170} wrapLabel onChange={(value) => { setIsLoading(true); setError(null); setSelectedYear(Number(value)) }} />
              </div>
              <div>
                <label className="mb-2 block text-xs font-black uppercase tracking-[0.14em] text-[#f3c56b]">Từ tháng</label>
                <AdminSelect
                  value={selectedMonthFrom}
                  options={MONTH_FILTER_OPTIONS}
                  menuMinWidth={190}
                  wrapLabel
                  onChange={(value) => {
                    const month = Number(value)
                    setIsLoading(true)
                    setError(null)
                    setSelectedMonthFrom(month)
                    if (month > selectedMonthTo) setSelectedMonthTo(month)
                  }}
                />
              </div>
              <div>
                <label className="mb-2 block text-xs font-black uppercase tracking-[0.14em] text-[#f3c56b]">Đến tháng</label>
                <AdminSelect
                  value={selectedMonthTo}
                  options={MONTH_FILTER_OPTIONS}
                  menuMinWidth={190}
                  wrapLabel
                  onChange={(value) => {
                    const month = Number(value)
                    setIsLoading(true)
                    setError(null)
                    setSelectedMonthTo(month)
                    if (month < selectedMonthFrom) setSelectedMonthFrom(month)
                  }}
                />
              </div>
            </div>
          </div>
        </div>
        {isLoading && overview && (
          <div className="absolute right-5 top-5 inline-flex items-center gap-2 rounded-full border border-[#fff4df]/15 bg-[#24170d]/70 px-3 py-1.5 text-xs font-black text-[#f3c56b] backdrop-blur-md">
            <Loader2 className="h-3.5 w-3.5 animate-spin" /> Đang cập nhật
          </div>
        )}
      </div>

      {error && overview && (
        <div className="flex items-start justify-between gap-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
          <span>{error}</span>
          <button type="button" onClick={() => { setIsLoading(true); setError(null); void loadDashboard() }} className="font-black underline underline-offset-4">Thử lại</button>
        </div>
      )}

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {kpiCards.map((card) => (
          <MetricCard key={card.key} metric={card.metric} icon={card.icon} tone={card.tone} helper={card.helper} />
        ))}
      </div>

      <div className="grid gap-6 lg:grid-cols-[1.35fr_0.65fr]">
        <ChartCard title="Tài chính theo kỳ lọc" description="Bấm từng chỉ số để xem riêng doanh thu, chi phí hoặc lợi nhuận.">
          <RevenueComboChart data={overview.revenue_chart} />
        </ChartCard>
        <ChartCard title="Tỷ lệ lấp đầy" description={overview.occupancy_chart.mode === 'building' ? 'So sánh sức chứa đang sử dụng theo từng tòa.' : 'So sánh sức chứa đang sử dụng theo từng tầng.'}>
          <OccupancyBars chart={overview.occupancy_chart} />
        </ChartCard>
      </div>

      <ChartCard title="Chi tiền theo kỳ lọc" description="Theo dõi tổng tiền chi theo tháng và nhóm danh mục chi phí.">
        <ExpenseChart chart={overview.expense_chart} />
      </ChartCard>

      <div className="grid gap-6 lg:grid-cols-2">
        <ChartCard title="Trạng thái hóa đơn" description="Tình hình thu tiền và công nợ.">
          <InvoiceDonutChart data={overview.invoice_status_chart} />
        </ChartCard>
        <ChartCard title="Hợp đồng sắp hết hạn" description="Theo dõi các mốc cần nhắc gia hạn trong 30 ngày tới.">
          <ContractExpirationChart data={overview.contract_expiration_chart} />
        </ChartCard>
      </div>

      <div className="grid gap-6">
        <ChartCard title="Lượng sử dụng điện & nước" description="Biểu đồ cột 3D theo tổng lượng sử dụng từng tháng." action={<span className="inline-flex items-center gap-2 rounded-full border border-[#f3c56b]/20 bg-[#f3c56b]/16 px-3 py-1.5 text-xs font-black text-[#8a4f18]"><Zap className="h-3.5 w-3.5" /> Tiêu thụ</span>}>
          <UtilityUsageBarChart data={overview.utility_price_chart} />
        </ChartCard>
      </div>

      <div className="grid gap-4 rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/80 p-5 text-sm font-semibold text-[#6f6254] shadow-lg shadow-[#6b3f1d]/6 sm:grid-cols-2">
        <div className="flex items-center gap-3">
          <Building2 className="h-5 w-5 text-[#a65f16]" />
          <span>Phạm vi: <strong className="text-[#24170d]">{overview.meta.scope_label}</strong></span>
        </div>
        <div className="flex items-center gap-3">
          <FileText className="h-5 w-5 text-[#a65f16]" />
          <span>Kỳ: <strong className="text-[#24170d]">Tháng {overview.meta.month_from}-{overview.meta.month_to}/{overview.meta.year}</strong></span>
        </div>
      </div>
    </section>
  )
}
