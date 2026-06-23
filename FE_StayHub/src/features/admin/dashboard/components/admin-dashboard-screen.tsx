import { useCallback, useEffect, useMemo, useState } from 'react'
import { fetchAdminBuildings } from '../../facilities/services/facilities.service'
import { fetchUtilityPriceHistory, type UtilityPriceHistoryItem } from '../services/dashboard.service'
import type { AdminBuildingResource } from '../../facilities/types/facility-api.model'
import { AdminSelect } from '../../shared/components/AdminSelect'

const DASHBOARD_STATS = [
  { label: 'Phòng đang thuê', value: '128', change: '+12%', tone: 'from-[#24170d] to-[#a65f16]' },
  { label: 'Khách thuê', value: '246', change: '+18', tone: 'from-[#0f766e] to-[#f3c56b]' },
  { label: 'Doanh thu tháng', value: '486M', change: '+8.4%', tone: 'from-[#a65f16] to-[#f3c56b]' },
  { label: 'Yêu cầu bảo trì', value: '17', change: '5 mới', tone: 'from-rose-700 to-[#a65f16]' },
]

const RECENT_ACTIVITIES = [
  'Hợp đồng A-204 vừa được gia hạn thêm 12 tháng.',
  'Phòng B-503 đã hoàn tất thanh toán kỳ tháng này.',
  'Có 3 yêu cầu bảo trì đang chờ phân công nhân sự.',
  'Tòa C cập nhật chỉ số điện nước thành công.',
]

interface SinglePriceChartProps {
  type: 'electric' | 'water'
  history: UtilityPriceHistoryItem[]
  maxVal: number
  minVal: number
}

function SinglePriceChart({ type, history, maxVal, minVal }: SinglePriceChartProps) {
  const [hoveredIndex, setHoveredIndex] = useState<number | null>(null)
  const [tooltipPos, setTooltipPos] = useState({ x: 0, y: 0 })

  const color = type === 'electric' ? '#f3c56b' : '#0f766e'
  const textColor = type === 'electric' ? '#a65f16' : '#0f766e'
  const label = type === 'electric' ? 'Đơn giá Điện (đ/kWh)' : 'Đơn giá Nước (đ/m³)'
  const unit = type === 'electric' ? 'đ/kWh' : 'đ/m³'

  const points = useMemo(() => {
    const width = 800
    const height = 300
    const paddingLeft = 60
    const paddingRight = 20
    const paddingTop = 30
    const paddingBottom = 40
    
    const chartWidth = width - paddingLeft - paddingRight
    const chartHeight = height - paddingTop - paddingBottom
    
    return history.map((item, idx) => {
      const x = paddingLeft + (idx * chartWidth) / (history.length - 1 || 1)
      const val = type === 'electric' ? item.electric_price : item.water_price
      const range = maxVal - minVal || 1
      const y = paddingTop + chartHeight - ((val - minVal) / range) * chartHeight
      
      return {
        x,
        y,
        val,
        item,
      }
    })
  }, [history, type, maxVal, minVal])

  const pathD = useMemo(() => {
    if (points.length === 0) return ''
    return points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ')
  }, [points])

  const areaD = useMemo(() => {
    if (points.length === 0) return ''
    const chartBottom = 300 - 40
    const firstPoint = points[0]
    const lastPoint = points[points.length - 1]
    return `${pathD} L ${lastPoint.x} ${chartBottom} L ${firstPoint.x} ${chartBottom} Z`
  }, [points, pathD])

  const gridLines = useMemo(() => {
    return Array.from({ length: 4 }).map((_, idx) => {
      const y = 30 + (idx * 230) / 3
      const pct = (3 - idx) / 3
      const val = minVal + pct * (maxVal - minVal)
      
      return {
        y,
        val,
      }
    })
  }, [minVal, maxVal])

  const formatPrice = (val: number) => {
    return new Intl.NumberFormat('vi-VN').format(Math.round(val)) + ' đ'
  }

  const handleMouseMove = (e: React.MouseEvent<SVGSVGElement, MouseEvent>) => {
    if (points.length === 0) return
    const rect = e.currentTarget.getBoundingClientRect()
    const mouseX = e.clientX - rect.left
    
    const svgWidth = 800
    const scaleX = svgWidth / rect.width
    const svgMouseX = mouseX * scaleX
    
    let closestIdx = 0
    let minDiff = Infinity
    points.forEach((p, idx) => {
      const diff = Math.abs(p.x - svgMouseX)
      if (diff < minDiff) {
        minDiff = diff
        closestIdx = idx
      }
    })
    
    setHoveredIndex(closestIdx)
    
    const hoveredPoint = points[closestIdx]
    const tooltipX = (hoveredPoint.x * rect.width) / 800
    const tooltipY = (hoveredPoint.y * rect.height) / 300 - 15
    setTooltipPos({ x: tooltipX, y: tooltipY })
  }

  const handleMouseLeave = () => {
    setHoveredIndex(null)
  }

  const gradientId = `${type}Gradient`

  return (
    <div className="rounded-2xl border border-[#3d2a18]/8 bg-[#fff7e8]/30 p-4 shadow-sm">
      <div className="mb-2 flex items-center justify-between text-xs font-black text-[#24170d] uppercase tracking-[0.06em]">
        <span>{label}</span>
      </div>
      <div className="relative">
        <svg
          viewBox="0 0 800 300"
          className="w-full overflow-visible"
          onMouseMove={handleMouseMove}
          onMouseLeave={handleMouseLeave}
        >
          <defs>
            <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor={color} stopOpacity="0.32" />
              <stop offset="100%" stopColor={color} stopOpacity="0.0" />
            </linearGradient>
          </defs>

          {/* Grid Lines */}
          {gridLines.map((line, idx) => (
            <g key={idx}>
              <line
                x1={60}
                y1={line.y}
                x2={780}
                y2={line.y}
                stroke="#3d2a18"
                strokeOpacity={0.07}
                strokeDasharray="4 4"
              />
              <text
                x={50}
                y={line.y + 4}
                textAnchor="end"
                className="text-[10px] font-black fill-[#8b5e34]/70"
              >
                {formatPrice(line.val)}
              </text>
            </g>
          ))}

          {/* Area fill */}
          <path d={areaD} fill={`url(#${gradientId})`} />

          {/* Stroke Line */}
          <path
            d={pathD}
            fill="none"
            stroke={color}
            strokeWidth={3}
            strokeLinecap="round"
            strokeLinejoin="round"
          />

          {/* Nodes circles */}
          {points.map((p, idx) => (
            <g key={idx}>
              <circle
                cx={p.x}
                cy={p.y}
                r={4}
                fill="#fffaf1"
                stroke={color}
                strokeWidth={2}
              />
              <text
                x={p.x}
                y={280}
                textAnchor="middle"
                className="text-[10px] font-black fill-[#8b5e34]/70"
              >
                {p.item.month}
              </text>
            </g>
          ))}

          {/* Vertical Hover Tracking */}
          {hoveredIndex !== null && points[hoveredIndex] && (
            <g>
              <line
                x1={points[hoveredIndex].x}
                y1={30}
                x2={points[hoveredIndex].x}
                y2={260}
                stroke="#3d2a18"
                strokeOpacity={0.15}
                strokeWidth={1.5}
                strokeDasharray="3 3"
              />
              <circle
                cx={points[hoveredIndex].x}
                cy={points[hoveredIndex].y}
                r={7}
                fill={color}
                stroke="#fffaf1"
                strokeWidth={2}
                className="drop-shadow-[0_0_6px_rgba(0,0,0,0.2)]"
              />
            </g>
          )}
        </svg>

        {/* Tooltip Overlay */}
        {hoveredIndex !== null && points[hoveredIndex] && (
          <div
            className="absolute z-10 rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1]/96 p-4 shadow-2xl backdrop-blur-md text-xs pointer-events-none space-y-1 min-w-40"
            style={{
              left: `${tooltipPos.x}px`,
              top: `${tooltipPos.y}px`,
              transform: 'translate(-50%, -100%)',
            }}
          >
            <div className="font-black text-[#8b5e34] border-b border-[#3d2a18]/5 pb-1">
              Kỳ tháng {points[hoveredIndex].item.month}
            </div>
            <div className="flex items-center justify-between gap-4 font-semibold">
              <span className="flex items-center gap-1.5 font-bold" style={{ color: textColor }}>
                <span className="h-2 w-2 rounded-full" style={{ backgroundColor: color }} />
                Đơn giá:
              </span>
              <span className="font-black text-[#24170d]">
                {new Intl.NumberFormat('vi-VN').format(points[hoveredIndex].val)} {unit}
              </span>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}

export function AdminDashboardScreen() {
  const [buildings, setBuildings] = useState<AdminBuildingResource[]>([])
  const [selectedBuildingId, setSelectedBuildingId] = useState('')
  const [history, setHistory] = useState<UtilityPriceHistoryItem[]>([])
  const [isLoading, setIsLoading] = useState(false)
  const [selectedMonthsCount, setSelectedMonthsCount] = useState(6)

  const loadBuildings = useCallback(async () => {
    try {
      const response = await fetchAdminBuildings({ per_page: 100 })
      const list = response.result || []
      setBuildings(Array.isArray(list) ? list : [])
      if (list.length > 0) {
        setSelectedBuildingId(String(list[0].id))
      }
    } catch (e) {
      console.error('Không thể tải danh sách tòa nhà', e)
    }
  }, [])

  const loadHistoryData = useCallback(async () => {
    if (!selectedBuildingId) return
    setIsLoading(true)
    try {
      const response = await fetchUtilityPriceHistory({
        building_id: Number(selectedBuildingId),
        months: selectedMonthsCount,
      })
      setHistory(response.result || [])
    } catch (e) {
      console.error('Không thể tải lịch sử giá', e)
    } finally {
      setIsLoading(false)
    }
  }, [selectedBuildingId, selectedMonthsCount])

  useEffect(() => {
    void loadBuildings()
  }, [loadBuildings])

  useEffect(() => {
    if (selectedBuildingId) {
      void loadHistoryData()
    }
  }, [selectedBuildingId, selectedMonthsCount, loadHistoryData])

  // Chart Scaling & Math
  const { maxElectric, minElectric, maxWater, minWater } = useMemo(() => {
    if (history.length === 0) {
      return { maxElectric: 5000, minElectric: 3000, maxWater: 20000, minWater: 10000 }
    }
    const electrics = history.map(d => d.electric_price)
    const waters = history.map(d => d.water_price)
    
    const maxE = Math.max(...electrics)
    const minE = Math.min(...electrics)
    const maxW = Math.max(...waters)
    const minW = Math.min(...waters)
    
    const rangeE = maxE - minE || 1000
    const rangeW = maxW - minW || 5000

    return {
      maxElectric: maxE + rangeE * 0.15,
      minElectric: Math.max(0, minE - rangeE * 0.15),
      maxWater: maxW + rangeW * 0.15,
      minWater: Math.max(0, minW - rangeW * 0.15),
    }
  }, [history])

  return (
    <section className="space-y-6 text-[#24170d]">
      <div className="relative overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] p-5 text-[#fff4df] shadow-2xl shadow-[#6b3f1d]/18 sm:p-6">
        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_12%,rgba(243,197,107,0.26),transparent_28%),radial-gradient(circle_at_86%_22%,rgba(15,118,110,0.26),transparent_30%),linear-gradient(135deg,#24170d_0%,#3d2a18_48%,#0f3f3b_100%)]" />

        <div className="relative max-w-3xl space-y-2">
          <p className="text-xs font-black uppercase tracking-[0.24em] text-[#f3c56b]">StayHub Admin</p>
          <h1 className="text-2xl font-black tracking-tight sm:text-3xl lg:text-4xl">Tổng quan vận hành ký túc xá</h1>
          <p className="max-w-2xl text-sm font-semibold leading-5 text-[#f8e8c8]/82">
            Theo dõi tình trạng phòng, khách thuê, doanh thu và các yêu cầu vận hành trong cùng một bảng điều khiển.
          </p>
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {DASHBOARD_STATS.map((stat) => (
          <article key={stat.label} className="rounded-3xl border border-[#3d2a18]/10 bg-[#fffaf1]/82 p-5 shadow-lg shadow-[#6b3f1d]/8 backdrop-blur-md transition duration-200 hover:-translate-y-1 hover:bg-[#fff7e8] hover:shadow-2xl hover:shadow-[#6b3f1d]/14">
            <div className={`mb-5 h-12 w-12 rounded-2xl bg-gradient-to-br ${stat.tone} shadow-lg shadow-[#6b3f1d]/18`} />
            <p className="text-sm font-bold text-[#6f6254]">{stat.label}</p>
            <div className="mt-2 flex items-end justify-between gap-3">
              <strong className="text-3xl font-black text-[#24170d]">{stat.value}</strong>
              <span className="rounded-full border border-[#0f766e]/10 bg-[#0f766e]/10 px-3 py-1 text-xs font-black text-[#0f5f59]">{stat.change}</span>
            </div>
          </article>
        ))}
      </div>

      <div className="grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
        <div className="rounded-3xl border border-[#3d2a18]/10 bg-[#fffaf1]/82 p-6 shadow-lg shadow-[#6b3f1d]/8 backdrop-blur-md">
          <div className="mb-6 flex items-center justify-between gap-4">
            <div>
              <h2 className="text-xl font-black text-[#24170d]">Hiệu suất doanh thu</h2>
              <p className="text-sm font-semibold text-[#6f6254]">Dữ liệu mẫu để kiểm tra Tailwind CSS đã hoạt động đầy đủ.</p>
            </div>
            <span className="rounded-full border border-[#a65f16]/10 bg-[#f3c56b]/18 px-4 py-2 text-xs font-black text-[#8a4f18]">Tháng 04/2026</span>
          </div>
          <div className="flex h-72 items-end gap-3 rounded-[1.5rem] border border-[#3d2a18]/8 bg-[#fff7e8]/78 p-5 shadow-inner shadow-[#6b3f1d]/6">
            {[42, 58, 51, 72, 66, 84, 76, 91, 88, 96, 89, 100].map((height, index) => (
              <div key={index} className="flex flex-1 flex-col items-center gap-2">
                <div className="w-full rounded-t-2xl bg-gradient-to-t from-[#a65f16] via-[#f3c56b] to-[#0f766e] shadow-sm shadow-[#6b3f1d]/12" style={{ height: `${height}%` }} />
                <span className="text-[10px] font-bold text-[#8b5e34]/60">T{index + 1}</span>
              </div>
            ))}
          </div>
        </div>

        <div className="rounded-3xl border border-[#3d2a18]/10 bg-[#fffaf1]/82 p-6 shadow-lg shadow-[#6b3f1d]/8 backdrop-blur-md">
          <h2 className="text-xl font-black text-[#24170d]">Hoạt động gần đây</h2>
          <div className="mt-5 space-y-4">
            {RECENT_ACTIVITIES.map((activity) => (
              <div key={activity} className="rounded-2xl border border-[#3d2a18]/8 bg-[#fff7e8]/82 p-4 text-sm font-semibold leading-6 text-[#3d2a18] shadow-sm shadow-[#6b3f1d]/5">
                {activity}
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Utility Price History Card */}
      <div className="relative rounded-3xl border border-[#3d2a18]/10 bg-[#fffaf1]/82 p-6 shadow-lg shadow-[#6b3f1d]/8 backdrop-blur-md">
        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 className="text-xl font-black text-[#24170d]">Biến động đơn giá điện & nước</h2>
            <p className="text-sm font-semibold text-[#6f6254]">Lịch sử thay đổi đơn giá dịch vụ điện và nước qua các tháng.</p>
          </div>
          
          <div className="flex flex-wrap items-center gap-3">
            {buildings.length > 0 && (
              <div className="w-56">
                <AdminSelect
                  value={selectedBuildingId}
                  options={buildings.map(b => ({ value: String(b.id), label: b.name }))}
                  onChange={(val) => setSelectedBuildingId(String(val))}
                />
              </div>
            )}
            
            <div className="w-32">
              <AdminSelect
                value={selectedMonthsCount}
                options={[
                  { value: 6, label: '6 tháng' },
                  { value: 12, label: '12 tháng' },
                  { value: 24, label: '24 tháng' },
                ]}
                onChange={(val) => setSelectedMonthsCount(Number(val))}
              />
            </div>
          </div>
        </div>

        {isLoading && history.length === 0 ? (
          <div className="flex h-[320px] items-center justify-center rounded-2xl bg-[#fff7e8]/40">
            <span className="text-sm font-bold text-[#8b5e34]/70 animate-pulse">Đang tải lịch sử đơn giá...</span>
          </div>
        ) : history.length === 0 ? (
          <div className="flex h-[320px] items-center justify-center rounded-2xl bg-[#fff7e8]/40 border border-dashed border-[#3d2a18]/10">
            <span className="text-sm font-bold text-[#8b5e34]/60">Chưa có lịch sử thay đổi đơn giá</span>
          </div>
        ) : (
          <div className="grid gap-6 lg:grid-cols-2">
            <SinglePriceChart type="electric" history={history} maxVal={maxElectric} minVal={minElectric} />
            <SinglePriceChart type="water" history={history} maxVal={maxWater} minVal={minWater} />
          </div>
        )}
      </div>
    </section>
  )
}
