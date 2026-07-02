import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  ArrowLeft,
  Banknote,
  Building2,
  Loader2,
  ReceiptText,
  DollarSign,
  PieChart,
  BarChart3,
  Percent,
  FileDown,
} from 'lucide-react'
import { ApiError } from '../../../../shared/lib/api/api-client'
import { cn } from '../../../../shared/lib/utils/cn'
import { formatCurrency } from '../../../../shared/lib/utils/format'
import { AdminSelect } from '../../shared/components/AdminSelect'
import { fetchBuilding } from '../../rooms/services/rooms.service'
import type { BuildingResource } from '../../rooms/types/rooms.model'
import { fetchFinancialReport } from '../services/financials.service'
import type { FinancialReportData, FinancialBreakdownItem, FinancialReportChartPoint } from '../types/financials.model'

const CURRENT_YEAR = new Date().getFullYear()
const CURRENT_MONTH = new Date().getMonth() + 1

const YEAR_OPTIONS = Array.from({ length: 7 }).map((_, index) => {
  const year = CURRENT_YEAR - index
  return { value: year, label: `Năm ${year}` }
})

const MONTH_OPTIONS = Array.from({ length: 12 }).map((_, index) => ({
  value: index + 1,
  label: `Tháng ${index + 1}`,
}))

export function FinancialsScreen() {
  const [year, setYear] = useState<number>(CURRENT_YEAR)
  const [monthFrom, setMonthFrom] = useState<number>(1)
  const [monthTo, setMonthTo] = useState<number>(CURRENT_MONTH)
  const [buildingId, setBuildingId] = useState<string | number>('')

  const [buildings, setBuildings] = useState<BuildingResource[]>([])
  const [isBuildingsLoading, setIsBuildingsLoading] = useState(true)

  const [reportData, setReportData] = useState<FinancialReportData | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  const [hoveredChartIndex, setHoveredChartIndex] = useState<number | null>(null)

  // 1. Tải danh sách tòa nhà
  useEffect(() => {
    async function loadBuildings() {
      setIsBuildingsLoading(true)
      try {
        const response = await fetchBuilding()
        setBuildings(response.result || [])
      } catch (error) {
        console.error('Không thể tải danh sách tòa nhà', error)
      } finally {
        setIsBuildingsLoading(false)
      }
    }
    void loadBuildings()
  }, [])

  // 2. Tải dữ liệu báo cáo
  const loadReport = useCallback(async () => {
    setIsLoading(true)
    setErrorMessage(null)
    try {
      const response = await fetchFinancialReport({
        year,
        month_from: monthFrom,
        month_to: monthTo,
        building_id: buildingId ? Number(buildingId) : null,
      })
      setReportData(response.result)
    } catch (error) {
      if (error instanceof ApiError) {
        setErrorMessage(error.message || 'Không thể tải báo cáo lợi nhuận.')
      } else {
        setErrorMessage('Đã xảy ra lỗi kết nối.')
      }
    } finally {
      setIsLoading(false)
    }
  }, [year, monthFrom, monthTo, buildingId])

  useEffect(() => {
    void loadReport()
  }, [loadReport])

  const buildingOptions = useMemo(() => [
    { value: '', label: 'Tất cả tòa nhà', tone: 'default' as const },
    ...buildings.map((b) => ({ value: b.id, label: b.name, tone: 'default' as const })),
  ], [buildings])

  const handleExportExcel = () => {
    if (!reportData) return

    const selectedBuildingName = buildingId
      ? (buildings.find((b) => Number(b.id) === Number(buildingId))?.name || 'Tòa nhà ' + buildingId)
      : 'Tất cả tòa nhà'

    // Tạo file Excel dưới dạng tài liệu HTML có chứa các thuộc tính XML dành riêng cho Excel.
    // Cách này cho phép chúng ta tùy biến kiểu dáng (font chữ, viền bảng, màu nền, căn lề)
    // giúp báo cáo hiển thị trực quan, sạch đẹp và chuyên nghiệp hơn rất nhiều.
    let html = `
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
  <meta charset="utf-8">
  <!--[if gte mso 9]>
  <xml>
    <x:ExcelWorkbook>
      <x:ExcelWorksheets>
        <x:ExcelWorksheet>
          <x:Name>Báo cáo tài chính</x:Name>
          <x:WorksheetOptions>
            <x:DisplayGridlines/>
          </x:WorksheetOptions>
        </x:ExcelWorksheet>
      </x:ExcelWorksheets>
    </x:ExcelWorkbook>
  </xml>
  <![endif]-->
  <style>
    body, table, tr, td, th { font-family: 'Times New Roman', Times, serif; }
    table { border-collapse: collapse; }
    td, th { border: 1px solid #dcd1c4; padding: 6px 10px; font-size: 13pt; color: #24170d; font-family: 'Times New Roman', Times, serif; }
    .title { font-size: 20pt; font-weight: bold; color: #a65f16; text-align: center; }
    .subtitle { font-size: 12pt; color: #6f6254; text-align: center; }
    .section-title { font-size: 15pt; font-weight: bold; background-color: #fff7e8; color: #8b5e34; }
    .header { font-weight: bold; background-color: #f3c56b; color: #24170d; text-align: center; font-size: 13pt; }
    .number { text-align: right; }
    .text-cell { mso-number-format:"\\@"; text-align: center; }
    .total-row { font-weight: bold; background-color: #fff1d6; }
    .profit-positive { color: #047857; font-weight: bold; text-align: right; }
    .profit-negative { color: #be123c; font-weight: bold; text-align: right; }
  </style>
</head>
<body>
  <table>
    <!-- Tiêu đề lớn -->
    <tr><td colspan="4" class="title" style="border:none;">BÁO CÁO TÀI CHÍNH STAYHUB</td></tr>
    <tr><td colspan="4" class="subtitle" style="border:none;">Thời gian lọc: Tháng ${monthFrom}/${year} - Tháng ${monthTo}/${year}</td></tr>
    <tr><td colspan="4" class="subtitle" style="border:none;padding-bottom:15px;">Tòa nhà: ${selectedBuildingName}</td></tr>
    <tr><td colspan="4" style="border:none;height:10px;"></td></tr>

    <tr><td colspan="4" class="section-title" style="border:none;">I. TỔNG QUAN CHỈ TIÊU</td></tr>
    <tr>
      <td colspan="2" class="header">Chỉ số</td>
      <td colspan="2" class="header">Giá trị</td>
    </tr>
    <tr>
      <td colspan="2">Tổng doanh thu</td>
      <td colspan="2" class="number" style="font-weight:bold;color:#a65f16;">${formatCurrency(reportData.summary.revenue)}</td>
    </tr>
    <tr>
      <td colspan="2">Tổng chi phí</td>
      <td colspan="2" class="number" style="font-weight:bold;color:#0f766e;">${formatCurrency(reportData.summary.expenses)}</td>
    </tr>
    <tr>
      <td colspan="2" class="total-row">Lợi nhuận ròng</td>
      <td colspan="2" class="total-row ${reportData.summary.profit >= 0 ? 'profit-positive' : 'profit-negative'}">${formatCurrency(reportData.summary.profit)}</td>
    </tr>
    <tr>
      <td colspan="2">Biên lợi nhuận ròng</td>
      <td colspan="2" class="number" style="font-weight:bold;">${reportData.summary.profit_margin}%</td>
    </tr>
    <tr><td colspan="4" style="border:none;height:15px;"></td></tr>

    <tr><td colspan="4" class="section-title" style="border:none;">II. XU HƯỚNG THEO THÁNG</td></tr>
    <tr>
      <td class="header">Tháng</td>
      <td class="header">Doanh thu</td>
      <td class="header">Chi phí</td>
      <td class="header">Lợi nhuận ròng</td>
    </tr>
`;

    reportData.chart.forEach((pt) => {
      const isPositive = pt.profit >= 0;
      html += `
    <tr>
      <td class="text-cell">${pt.month}</td>
      <td class="number">${formatCurrency(pt.revenue)}</td>
      <td class="number">${formatCurrency(pt.expenses)}</td>
      <td class="${isPositive ? 'profit-positive' : 'profit-negative'}">${formatCurrency(pt.profit)}</td>
    </tr>`;
    });

    html += `
    <tr><td colspan="4" style="border:none;height:15px;"></td></tr>

    <tr><td colspan="4" class="section-title" style="border:none;">III. CƠ CẤU DOANH THU THEO DỊCH VỤ</td></tr>
    <tr>
      <td colspan="2" class="header">Dịch vụ</td>
      <td class="header">Doanh thu (VNĐ)</td>
      <td class="header">Tỷ lệ (%)</td>
    </tr>
`;

    reportData.revenue_breakdown.forEach((item) => {
      html += `
    <tr>
      <td colspan="2">${item.label}</td>
      <td class="number">${formatCurrency(item.amount)}</td>
      <td style="text-align:center;">${item.percentage}%</td>
    </tr>`;
    });

    html += `
    <tr>
      <td colspan="2" class="total-row">TỔNG CỘNG DOANH THU</td>
      <td class="total-row number" style="color:#a65f16;">${formatCurrency(reportData.summary.revenue)}</td>
      <td class="total-row" style="text-align:center;">100%</td>
    </tr>
    <tr><td colspan="4" style="border:none;height:15px;"></td></tr>

    <tr><td colspan="4" class="section-title" style="border:none;">IV. CƠ CẤU CHI PHÍ</td></tr>
    <tr>
      <td colspan="2" class="header">Danh mục</td>
      <td class="header">Chi phí (VNĐ)</td>
      <td class="header">Tỷ lệ (%)</td>
    </tr>
`;

    reportData.expense_breakdown.forEach((item) => {
      html += `
    <tr>
      <td colspan="2">${item.label}</td>
      <td class="number">${formatCurrency(item.amount)}</td>
      <td style="text-align:center;">${item.percentage}%</td>
    </tr>`;
    });

    html += `
    <tr>
      <td colspan="2" class="total-row">TỔNG CỘNG CHI PHÍ</td>
      <td class="total-row number" style="color:#0f766e;">${formatCurrency(reportData.summary.expenses)}</td>
      <td class="total-row" style="text-align:center;">100%</td>
    </tr>
`;

    if (buildingId === '' && reportData.top_buildings && reportData.top_buildings.length > 0) {
      html += `
    <tr><td colspan="4" style="border:none;height:15px;"></td></tr>
    <tr><td colspan="4" class="section-title" style="border:none;">V. HIỆU SUẤT DOANH THU TÒA NHÀ</td></tr>
    <tr>
      <td colspan="2" class="header">Tòa nhà</td>
      <td class="header">Doanh thu (VNĐ)</td>
      <td class="header">Tỷ lệ (%)</td>
    </tr>
`;
      reportData.top_buildings.forEach((item) => {
        html += `
    <tr>
      <td colspan="2">${item.name}</td>
      <td class="number">${formatCurrency(item.revenue)}</td>
      <td style="text-align:center;">${item.percentage}%</td>
    </tr>`;
      });
      html += `
    <tr>
      <td colspan="2" class="total-row">TỔNG CỘNG DOANH THU</td>
      <td class="total-row number" style="color:#a65f16;">${formatCurrency(reportData.summary.revenue)}</td>
      <td class="total-row" style="text-align:center;">100%</td>
    </tr>
`;
    }

    html += `
  </table>
</body>
</html>
`;

    // Sử dụng kiểu mime của Excel và mã hóa UTF-8
    const blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8;' })

    const link = document.createElement('a')
    const url = URL.createObjectURL(blob)
    link.setAttribute('href', url)

    const filename = `bao_cao_tai_chinh_T${monthFrom}_T${monthTo}_${year}.xls`
    link.setAttribute('download', filename)
    link.style.visibility = 'hidden'
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  }

  return (
    <section className="space-y-6 text-[#24170d]">
      {/* 3. Header */}
      <section className="overflow-hidden rounded-[2.15rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
        <div className="relative p-5 text-[#fff4df] sm:p-6 lg:p-7">
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_16%_16%,rgba(243,197,107,0.28),transparent_30%),radial-gradient(circle_at_86%_4%,rgba(15,118,110,0.32),transparent_30%),linear-gradient(135deg,#24170d_0%,#3d2a18_52%,#0f3f3b_100%)]" />
          <div className="pointer-events-none absolute inset-x-6 bottom-0 h-px bg-gradient-to-r from-transparent via-[#f3c56b]/45 to-transparent" />
          <div className="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <span className="block text-xs font-black uppercase tracking-[0.18em] text-[#f3c56b]/80">TÀI CHÍNH & BÁO CÁO</span>
              <h1 className="mt-3 text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl lg:text-[2.65rem] flex items-center gap-3">
                <BarChart3 className="h-8 w-8 text-[#f3c56b] shrink-0" />
                Báo cáo lợi nhuận
              </h1>
            </div>

            <div className="flex flex-wrap gap-3">
              <button
                onClick={handleExportExcel}
                disabled={!reportData || isLoading}
                className="inline-flex h-11 items-center justify-center gap-2 rounded-2xl border border-[#f3c56b]/35 bg-[#f3c56b]/15 px-4 text-xs font-black uppercase tracking-[0.14em] text-[#f3c56b] transition hover:bg-[#f3c56b]/25 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <FileDown className="h-4 w-4" /> Xuất báo cáo Excel
              </button>

              <button
                onClick={() => void loadReport()}
                className="inline-flex h-11 items-center justify-center gap-2 rounded-2xl border border-[#fff4df]/15 bg-[#fff4df]/10 px-4 text-xs font-black uppercase tracking-[0.14em] text-[#fff4df] transition hover:bg-[#fff4df]/20"
              >
                Tải lại dữ liệu
              </button>
            </div>
          </div>
        </div>
      </section>

      {/* 4. Bộ lọc (Filters) */}
      <section className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 p-4 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur lg:p-5">
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <div className="space-y-1">
            <span className="text-[10px] font-black uppercase tracking-[0.15em] text-[#8b5e34]/70 pl-1">Tòa nhà</span>
            <AdminSelect
              value={buildingId}
              options={buildingOptions}
              onChange={setBuildingId}
              disabled={isBuildingsLoading}
            />
          </div>
          <div className="space-y-1">
            <span className="text-[10px] font-black uppercase tracking-[0.15em] text-[#8b5e34]/70 pl-1">Chọn năm</span>
            <AdminSelect
              value={year}
              options={YEAR_OPTIONS}
              onChange={(v) => setYear(Number(v))}
            />
          </div>
          <div className="space-y-1">
            <span className="text-[10px] font-black uppercase tracking-[0.15em] text-[#8b5e34]/70 pl-1">Từ tháng</span>
            <AdminSelect
              value={monthFrom}
              options={MONTH_OPTIONS}
              onChange={(v) => setMonthFrom(Number(v))}
            />
          </div>
          <div className="space-y-1">
            <span className="text-[10px] font-black uppercase tracking-[0.15em] text-[#8b5e34]/70 pl-1">Đến tháng</span>
            <AdminSelect
              value={monthTo}
              options={MONTH_OPTIONS.map(opt => ({
                ...opt,
                disabled: opt.value < monthFrom
              }))}
              onChange={(v) => setMonthTo(Number(v))}
            />
          </div>
        </div>
      </section>

      {errorMessage && (
        <div className="rounded-3xl border border-rose-200 bg-rose-50 p-4 text-sm font-black text-rose-700">
          {errorMessage}
        </div>
      )}

      {isLoading ? (
        <div className="flex h-96 flex-col items-center justify-center gap-3 rounded-[2rem] border border-[#3d2a18]/10 bg-white/60 backdrop-blur">
          <Loader2 className="h-8 w-8 animate-spin text-[#a65f16]" />
          <p className="text-sm font-black text-[#8b5e34]">Đang phân tích dữ liệu tài chính...</p>
        </div>
      ) : (
        reportData && (
          <div className="space-y-6">
            {/* 5. KPI Cards */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              <KpiCard
                title="Tổng Doanh Thu"
                value={reportData.summary.revenue}
                icon={Banknote}
                tone="revenue"
                description="Tổng thu nhập đã thu trong kỳ"
              />
              <KpiCard
                title="Tổng Chi Phí"
                value={reportData.summary.expenses}
                icon={ReceiptText}
                tone="expense"
                description="Tổng chi phí hoạt động đã phát sinh"
              />
              <KpiCard
                title="Lợi Nhuận Ròng"
                value={reportData.summary.profit}
                icon={DollarSign}
                tone={reportData.summary.profit >= 0 ? "profit" : "loss"}
                description="Doanh thu sau khi trừ chi phí"
              />
              <KpiCard
                title="Tỷ Suất Lợi Nhuận"
                value={`${reportData.summary.profit_margin}%`}
                icon={Percent}
                tone="margin"
                description="Tỷ suất lợi nhuận ròng trên doanh thu"
                isPercent
              />
            </div>

            {/* 6. Trend Chart */}
            <section className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/88 p-5 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md sm:p-6">
              <div className="mb-5 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                  <h2 className="text-lg font-black tracking-tight text-[#24170d] sm:text-xl">Biểu đồ Lợi nhuận</h2>
                </div>
              </div>
              <FinancialTrendChart
                data={reportData.chart}
                hoveredIndex={hoveredChartIndex}
                setHoveredIndex={setHoveredChartIndex}
              />
            </section>

            {/* 7. Breakdowns */}
            <div className={cn(
              "grid gap-6",
              buildingId === '' ? "lg:grid-cols-3" : "lg:grid-cols-2"
            )}>
              {/* Doanh thu breakdown */}
              <BreakdownCard
                title="Cơ Cấu Doanh Thu"
                subtitle="Phân bổ doanh thu theo dịch vụ"
                items={reportData.revenue_breakdown}
                total={reportData.summary.revenue}
                barColor="bg-[#f3c56b]"
              />

              {/* Chi phí breakdown */}
              <BreakdownCard
                title="Cơ Cấu Chi Phí"
                subtitle="Phân bổ chi phí theo danh mục"
                items={reportData.expense_breakdown}
                total={reportData.summary.expenses}
                barColor="bg-[#0f766e]"
              />

              {/* Xếp hạng doanh thu tòa nhà */}
              {buildingId === '' && (
                <TopBuildingsCard
                  title="Hiệu Suất Tòa Nhà"
                  subtitle="Xếp hạng doanh thu theo tòa nhà"
                  items={reportData.top_buildings}
                  total={reportData.summary.revenue}
                />
              )}
            </div>
          </div>
        )
      )}
    </section>
  )
}

// ======================= Sub Components =======================

interface KpiCardProps {
  title: string
  value: number | string
  icon: React.ComponentType<{ className?: string }>
  tone: 'revenue' | 'expense' | 'profit' | 'loss' | 'margin'
  description: string
  isPercent?: boolean
}

function KpiCard({ title, value, icon: Icon, tone, description, isPercent = false }: KpiCardProps) {
  const bgGradients = {
    revenue: 'bg-[radial-gradient(circle_at_86%_4%,rgba(243,197,107,0.15),transparent_60%)]',
    expense: 'bg-[radial-gradient(circle_at_86%_4%,rgba(15,118,110,0.15),transparent_60%)]',
    profit: 'bg-[radial-gradient(circle_at_86%_4%,rgba(16,185,129,0.15),transparent_60%)]',
    loss: 'bg-[radial-gradient(circle_at_86%_4%,rgba(244,63,94,0.15),transparent_60%)]',
    margin: 'bg-[radial-gradient(circle_at_86%_4%,rgba(99,102,241,0.15),transparent_60%)]',
  }

  const iconColors = {
    revenue: 'text-[#a65f16] bg-[#f3c56b]/15 border-[#f3c56b]/30',
    expense: 'text-[#0f766e] bg-[#0f766e]/15 border-[#0f766e]/30',
    profit: 'text-emerald-700 bg-emerald-500/15 border-emerald-500/30',
    loss: 'text-rose-700 bg-rose-500/15 border-rose-500/30',
    margin: 'text-indigo-700 bg-indigo-500/15 border-indigo-500/30',
  }

  const textColors = {
    revenue: 'text-[#24170d]',
    expense: 'text-[#24170d]',
    profit: 'text-emerald-800',
    loss: 'text-rose-800',
    margin: 'text-indigo-950',
  }

  return (
    <article className="relative overflow-hidden rounded-[1.75rem] border border-[#3d2a18]/10 bg-white/70 p-5 shadow-lg shadow-[#6b3f1d]/4 transition duration-200 hover:-translate-y-0.5 hover:shadow-2xl">
      <div className={cn("absolute inset-0 pointer-events-none", bgGradients[tone])} />
      <div className="relative flex items-center justify-between">
        <span className="text-xs font-black uppercase tracking-[0.12em] text-[#6f6254]">{title}</span>
        <div className={cn("flex h-10 w-10 items-center justify-center rounded-2xl border text-sm shadow-sm", iconColors[tone])}>
          <Icon className="h-4.5 w-4.5" />
        </div>
      </div>
      <div className="relative mt-4">
        <strong className={cn("block text-xl font-black tracking-tight sm:text-2xl", textColors[tone])}>
          {isPercent ? value : formatCurrency(Number(value))}
        </strong>
        <p className="mt-2 text-[10px] font-bold leading-5 text-[#8b5e34] opacity-75">{description}</p>
      </div>
    </article>
  )
}

// --- Custom Trend Chart Drawing ---
interface TrendChartProps {
  data: FinancialReportChartPoint[]
  hoveredIndex: number | null
  setHoveredIndex: (idx: number | null) => void
}

function FinancialTrendChart({ data, hoveredIndex, setHoveredIndex }: TrendChartProps) {
  if (!data.length) {
    return (
      <div className="flex min-h-64 flex-col items-center justify-center rounded-[1.5rem] border border-dashed border-[#3d2a18]/12 bg-[#fff7e8]/45 px-6 py-10 text-center">
        <p className="text-sm font-black text-[#24170d]">Chưa có dữ liệu giao dịch</p>
        <p className="mt-1 max-w-sm text-xs font-semibold leading-5 text-[#6f6254]">Báo cáo sẽ hiển thị trực quan sau khi phát sinh hóa đơn đã đóng hoặc các khoản chi.</p>
      </div>
    )
  }

  const width = 820
  const height = 320
  const paddingLeft = 86
  const paddingRight = 34
  const paddingTop = 34
  const paddingBottom = 58
  const chartWidth = width - paddingLeft - paddingRight
  const chartHeight = height - paddingTop - paddingBottom

  // Tìm mức giới hạn lớn nhất để căn tỉ lệ cột vẽ
  const maxVal = Math.max(
    1000000,
    ...data.flatMap((d) => [d.revenue, d.expenses, Math.abs(d.profit)])
  )
  const minVal = Math.min(0, ...data.map((d) => d.profit))
  const range = maxVal - minVal || 1

  const yForValue = (val: number) => paddingTop + ((maxVal - val) / range) * chartHeight
  const zeroY = yForValue(0)

  const slotWidth = chartWidth / data.length
  // Độ rộng từng thanh (Doanh thu, Chi phí & Lợi nhuận)
  const barWidth = Math.max(8, Math.min(18, slotWidth * 0.18))

  // Trục Y hiển thị 4 mức lưới
  const gridLines = Array.from({ length: 4 }).map((_, index) => {
    const ratio = index / 3
    const value = maxVal - range * ratio
    return { y: paddingTop + chartHeight * ratio, value }
  })

  return (
    <div className="grid gap-5 xl:grid-cols-[1fr_260px]">
      <div className="overflow-x-auto">
        <svg viewBox={`0 0 ${width} ${height}`} className="min-w-[720px] rounded-[2rem] bg-gradient-to-b from-[#fff8ea] to-[#fff1d6] shadow-inner">
          <defs>
            {/* Gradient Doanh thu */}
            <linearGradient id="revGrad" x1="0" x2="0" y1="0" y2="1">
              <stop offset="0%" stopColor="#fde7a7" />
              <stop offset="50%" stopColor="#f3c56b" />
              <stop offset="100%" stopColor="#a65f16" />
            </linearGradient>
            {/* Gradient Chi phí */}
            <linearGradient id="expGrad" x1="0" x2="0" y1="0" y2="1">
              <stop offset="0%" stopColor="#5eead4" />
              <stop offset="50%" stopColor="#0f766e" />
              <stop offset="100%" stopColor="#083f3b" />
            </linearGradient>
            {/* Gradient Lợi nhuận ròng */}
            <linearGradient id="profGrad" x1="0" x2="0" y1="0" y2="1">
              <stop offset="0%" stopColor="#e6ccb2" />
              <stop offset="50%" stopColor="#b08968" />
              <stop offset="100%" stopColor="#7f5539" />
            </linearGradient>
          </defs>

          {/* Lưới ngang */}
          {gridLines.map((line, idx) => (
            <g key={idx}>
              <line
                x1={paddingLeft}
                y1={line.y}
                x2={width - paddingRight}
                y2={line.y}
                stroke="#eadcc8"
                strokeWidth="1"
                strokeDasharray="4 4"
              />
              <text
                x={paddingLeft - 10}
                y={line.y + 4}
                textAnchor="end"
                className="text-[9px] font-black fill-[#8b5e34]"
              >
                {new Intl.NumberFormat('vi-VN').format(Math.round(line.value))}
              </text>
            </g>
          ))}

          {/* Cột dữ liệu */}
          {data.map((d, index) => {
            const slotCenterX = paddingLeft + slotWidth * index + slotWidth / 2

            // Cột Doanh Thu (Vàng - nằm bên trái)
            const revBarHeight = (d.revenue / range) * chartHeight
            const revX = slotCenterX - 1.5 * barWidth - 2
            const revY = d.revenue >= 0 ? zeroY - revBarHeight : zeroY
            const revH = Math.max(2, Math.abs(revBarHeight))

            // Cột Chi Phí (Teal - nằm ở giữa)
            const expBarHeight = (d.expenses / range) * chartHeight
            const expX = slotCenterX - 0.5 * barWidth
            const expY = d.expenses >= 0 ? zeroY - expBarHeight : zeroY
            const expH = Math.max(2, Math.abs(expBarHeight))

            // Cột Lợi nhuận ròng (Nâu - nằm bên phải)
            const profBarHeight = (d.profit / range) * chartHeight
            const profX = slotCenterX + 0.5 * barWidth + 2
            const profY = d.profit >= 0 ? zeroY - profBarHeight : zeroY
            const profH = Math.max(2, Math.abs(profBarHeight))

            const isHovered = hoveredIndex === index

            return (
              <g key={index}>
                {/* Cột Doanh thu */}
                <rect
                  x={revX}
                  y={revY}
                  width={barWidth}
                  height={revH}
                  fill="url(#revGrad)"
                  rx="3"
                  opacity={hoveredIndex === null || isHovered ? 1 : 0.45}
                  className="transition-all duration-200"
                />

                {/* Cột Chi phí */}
                <rect
                  x={expX}
                  y={expY}
                  width={barWidth}
                  height={expH}
                  fill="url(#expGrad)"
                  rx="3"
                  opacity={hoveredIndex === null || isHovered ? 1 : 0.45}
                  className="transition-all duration-200"
                />

                {/* Cột Lợi nhuận ròng */}
                <rect
                  x={profX}
                  y={profY}
                  width={barWidth}
                  height={profH}
                  fill="url(#profGrad)"
                  rx="3"
                  opacity={hoveredIndex === null || isHovered ? 1 : 0.45}
                  className="transition-all duration-200"
                />

                {/* Trục X nhãn tháng */}
                <text
                  x={slotCenterX}
                  y={height - paddingBottom + 20}
                  textAnchor="middle"
                  className={cn(
                    "text-[10px] font-black transition-all",
                    isHovered ? "fill-[#24170d] text-xs font-blackScale" : "fill-[#8b5e34]/70"
                  )}
                >
                  {d.month}
                </text>

                {/* Vùng bắt sự kiện hover */}
                <rect
                  x={paddingLeft + slotWidth * index}
                  y={paddingTop}
                  width={slotWidth}
                  height={chartHeight}
                  fill="transparent"
                  onMouseEnter={() => setHoveredIndex(index)}
                  onMouseLeave={() => setHoveredIndex(null)}
                  className="cursor-pointer"
                />
              </g>
            )
          })}
        </svg>
      </div>

      {/* Thông tin hiển thị khi hover */}
      <div className="rounded-[1.75rem] border border-[#3d2a18]/10 bg-[#fffaf1] p-5 flex flex-col justify-between">
        <div>
          <h3 className="text-xs font-black uppercase tracking-[0.14em] text-[#8b5e34]">
            {hoveredIndex !== null ? `Chi tiết: ${data[hoveredIndex].month}` : 'Thông số chung'}
          </h3>

          <div className="mt-4 space-y-3.5">
            <div className="flex items-center justify-between border-b border-[#3d2a18]/5 pb-1.5">
              <span className="text-xs font-bold text-[#6f6254] flex items-center gap-1.5">
                <span className="h-2.5 w-2.5 rounded-full bg-[#f3c56b]" /> Doanh thu
              </span>
              <span className="text-xs font-black text-[#24170d]">
                {formatCurrency(hoveredIndex !== null ? data[hoveredIndex].revenue : data.reduce((sum, item) => sum + item.revenue, 0))}
              </span>
            </div>

            <div className="flex items-center justify-between border-b border-[#3d2a18]/5 pb-1.5">
              <span className="text-xs font-bold text-[#6f6254] flex items-center gap-1.5">
                <span className="h-2.5 w-2.5 rounded-full bg-[#0f766e]" /> Chi phí
              </span>
              <span className="text-xs font-black text-[#24170d]">
                {formatCurrency(hoveredIndex !== null ? data[hoveredIndex].expenses : data.reduce((sum, item) => sum + item.expenses, 0))}
              </span>
            </div>

            <div className="flex items-center justify-between">
              <span className="text-xs font-bold text-[#6f6254] flex items-center gap-1.5">
                <span className="h-2.5 w-2.5 rounded-full bg-[#8b5e34]" /> Lợi nhuận ròng
              </span>
              <span className={cn(
                "text-xs font-black",
                (hoveredIndex !== null ? data[hoveredIndex].profit : data.reduce((sum, item) => sum + item.profit, 0)) >= 0
                  ? "text-emerald-700"
                  : "text-rose-700"
              )}>
                {formatCurrency(hoveredIndex !== null ? data[hoveredIndex].profit : data.reduce((sum, item) => sum + item.profit, 0))}
              </span>
            </div>
          </div>
        </div>

        {hoveredIndex === null && (
          <p className="mt-5 text-[10px] font-semibold text-[#8b5e34]/70 leading-5">
            Di chuột qua từng tháng trên biểu đồ để xem chi tiết doanh số, chi phí và lợi nhuận của tháng đó.
          </p>
        )}
      </div>
    </div>
  )
}

// --- Breakdown Card với Progress Bars ---
interface BreakdownCardProps {
  title: string
  subtitle: string
  items: FinancialBreakdownItem[]
  total: number
  barColor: string
}

function BreakdownCard({ title, subtitle, items, total, barColor }: BreakdownCardProps) {
  return (
    <section className="rounded-[2rem] border border-[#3d2a18]/10 bg-white/80 p-5 shadow-xl shadow-[#6b3f1d]/6 backdrop-blur">
      <div className="mb-4">
        <h2 className="text-base font-black tracking-tight text-[#24170d]">{title}</h2>
        <p className="text-xs font-bold text-[#8b5e34]/80 mt-0.5">{subtitle}</p>
      </div>

      {!items.length ? (
        <div className="py-14 text-center rounded-2xl border border-dashed border-[#3d2a18]/10 bg-[#fffaf1]/30">
          <PieChart className="h-8 w-8 mx-auto text-[#8b5e34]/50 mb-2" />
          <p className="text-xs font-bold text-[#8b5e34]">Không ghi nhận phát sinh</p>
        </div>
      ) : (
        <div className="space-y-4">
          {items.map((item, idx) => (
            <div key={idx} className="space-y-1.5">
              <div className="flex items-center justify-between text-xs font-bold">
                <span className="text-[#24170d]">{item.label}</span>
                <div className="space-x-2 text-right">
                  <span className="text-[#6f6254]">{item.percentage}%</span>
                  <span className="text-[#24170d] font-black">{formatCurrency(item.amount)}</span>
                </div>
              </div>

              {/* Progress bar */}
              <div className="h-2 w-full rounded-full bg-[#3d2a18]/5 overflow-hidden">
                <div
                  className={cn("h-full rounded-full", barColor)}
                  style={{ width: `${item.percentage}%` }}
                />
              </div>
            </div>
          ))}

          <div className="border-t border-[#3d2a18]/10 pt-3 flex items-center justify-between font-black text-xs">
            <span className="text-[#8b5e34]">TỔNG CỘNG</span>
            <span className="text-[#24170d] text-sm">{formatCurrency(total)}</span>
          </div>
        </div>
      )}
    </section>
  )
}

// --- Top Buildings Revenue Ranking Card ---
interface TopBuildingsCardProps {
  title: string
  subtitle: string
  items?: { id: number; name: string; revenue: number; percentage: number }[]
  total: number
}

function TopBuildingsCard({ title, subtitle, items = [], total }: TopBuildingsCardProps) {
  return (
    <section className="rounded-[2rem] border border-[#3d2a18]/10 bg-white/80 p-5 shadow-xl shadow-[#6b3f1d]/6 backdrop-blur">
      <div className="mb-4">
        <h2 className="text-base font-black tracking-tight text-[#24170d]">{title}</h2>
        <p className="text-xs font-bold text-[#8b5e34]/80 mt-0.5">{subtitle}</p>
      </div>

      {!items.length ? (
        <div className="py-14 text-center rounded-2xl border border-dashed border-[#3d2a18]/10 bg-[#fffaf1]/30">
          <Building2 className="h-8 w-8 mx-auto text-[#8b5e34]/50 mb-2" />
          <p className="text-xs font-bold text-[#8b5e34]">Không có dữ liệu tòa nhà</p>
        </div>
      ) : (
        <div className="space-y-4">
          {items.map((item, idx) => (
            <div key={idx} className="space-y-1.5">
              <div className="flex items-center justify-between text-xs font-bold">
                <span className="text-[#24170d] flex items-center gap-1.5 font-bold">
                  <span className="inline-flex h-5 w-5 items-center justify-center rounded-md bg-[#8b5e34]/10 text-[10px] font-black text-[#8b5e34]">
                    {idx + 1}
                  </span>
                  {item.name}
                </span>
                <div className="space-x-2 text-right">
                  <span className="text-[#6f6254]">{item.percentage}%</span>
                  <span className="text-[#24170d] font-black">{formatCurrency(item.revenue)}</span>
                </div>
              </div>

              {/* Progress bar */}
              <div className="h-2 w-full rounded-full bg-[#3d2a18]/5 overflow-hidden">
                <div
                  className="h-full rounded-full bg-gradient-to-r from-[#8b5e34] to-[#f3c56b]"
                  style={{ width: `${item.percentage}%` }}
                />
              </div>
            </div>
          ))}

          <div className="border-t border-[#3d2a18]/10 pt-3 flex items-center justify-between font-black text-xs">
            <span className="text-[#8b5e34]">TỔNG CỘNG DOANH THU</span>
            <span className="text-[#24170d] text-sm">{formatCurrency(total)}</span>
          </div>
        </div>
      )}
    </section>
  )
}
