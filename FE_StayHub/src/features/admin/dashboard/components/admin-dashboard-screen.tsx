import { useCallback, useEffect, useMemo, useState, useRef } from 'react'
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

export function AdminDashboardScreen() {
  const [buildings, setBuildings] = useState<AdminBuildingResource[]>([])
  const [selectedBuildingId, setSelectedBuildingId] = useState('')
  const [history, setHistory] = useState<UtilityPriceHistoryItem[]>([])
  const [isLoading, setIsLoading] = useState(false)
  const [selectedMonthsCount, setSelectedMonthsCount] = useState(6)

  // Chart Interactive Hover States
  const [hoveredIndex, setHoveredIndex] = useState<number | null>(null)
  const [tooltipPos, setTooltipPos] = useState({ x: 0, y: 0 })
  const containerRef = useRef<HTMLDivElement>(null)

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

  const points = useMemo(() => {
    const width = 800
    const height = 300
    const paddingLeft = 60
    const paddingRight = 60
    const paddingTop = 30
    const paddingBottom = 40
    
    const chartWidth = width - paddingLeft - paddingRight
    const chartHeight = height - paddingTop - paddingBottom
    
    if (history.length === 0) return []
    
    return history.map((item, idx) => {
      const x = paddingLeft + (idx * chartWidth) / (history.length - 1 || 1)
      
      const eRange = maxElectric - minElectric || 1
      const yElectric = paddingTop + chartHeight - ((item.electric_price - minElectric) / eRange) * chartHeight
      
      const wRange = maxWater - minWater || 1
      const yWater = paddingTop + chartHeight - ((item.water_price - minWater) / wRange) * chartHeight
      
      return {
        x,
        yElectric,
        yWater,
        item,
      }
    })
  }, [history, maxElectric, minElectric, maxWater, minWater])

  const electricPathD = useMemo(() => {
    if (points.length === 0) return ''
    return points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.yElectric}`).join(' ')
  }, [points])

  const electricAreaD = useMemo(() => {
    if (points.length === 0) return ''
    const height = 300
    const paddingBottom = 40
    const chartBottom = height - paddingBottom
    const firstPoint = points[0]
    const lastPoint = points[points.length - 1]
    return `${electricPathD} L ${lastPoint.x} ${chartBottom} L ${firstPoint.x} ${chartBottom} Z`
  }, [points, electricPathD])

  const waterPathD = useMemo(() => {
    if (points.length === 0) return ''
    return points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.yWater}`).join(' ')
  }, [points])

  const waterAreaD = useMemo(() => {
    if (points.length === 0) return ''
    const height = 300
    const paddingBottom = 40
    const chartBottom = height - paddingBottom
    const firstPoint = points[0]
    const lastPoint = points[points.length - 1]
    return `${waterPathD} L ${lastPoint.x} ${chartBottom} L ${firstPoint.x} ${chartBottom} Z`
  }, [points, waterPathD])

  const gridLines = useMemo(() => {
    const height = 300
    const paddingTop = 30
    const paddingBottom = 40
    const chartHeight = height - paddingTop - paddingBottom
    
    return Array.from({ length: 4 }).map((_, idx) => {
      const y = paddingTop + (idx * chartHeight) / 3
      const pct = (3 - idx) / 3
      const electricValue = minElectric + pct * (maxElectric - minElectric)
      const waterValue = minWater + pct * (maxWater - minWater)
      
      return {
        y,
        electricValue,
        waterValue,
      }
    })
  }, [minElectric, maxElectric, minWater, maxWater])

  const formatElectricPrice = (val: number) => {
    return new Intl.NumberFormat('vi-VN').format(Math.round(val)) + ' đ'
  }
  const formatWaterPrice = (val: number) => {
    return new Intl.NumberFormat('vi-VN').format(Math.round(val)) + ' đ'
  }

  const handleMouseMove = (e: React.MouseEvent<SVGSVGElement, MouseEvent>) => {
    if (points.length === 0 || !containerRef.current) return
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
    const highestY = Math.min(hoveredPoint.yElectric, hoveredPoint.yWater)
    const tooltipY = (highestY * rect.height) / 300 - 15
    setTooltipPos({ x: tooltipX, y: tooltipY })
  }

  const handleMouseLeave = () => {
    setHoveredIndex(null)
  }

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
      <div ref={containerRef} className="relative rounded-3xl border border-[#3d2a18]/10 bg-[#fffaf1]/82 p-6 shadow-lg shadow-[#6b3f1d]/8 backdrop-blur-md">
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

        <div className="mb-4 flex items-center gap-4 text-xs font-bold">
          <div className="flex items-center gap-1.5 text-[#24170d]">
            <span className="h-3 w-3 rounded-md bg-[#f3c56b] shadow-sm" />
            <span>Điện (đ/kWh)</span>
          </div>
          <div className="flex items-center gap-1.5 text-[#24170d]">
            <span className="h-3 w-3 rounded-md bg-[#0f766e] shadow-sm" />
            <span>Nước (đ/m³)</span>
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
          <div className="relative">
            <svg
              viewBox="0 0 800 300"
              className="w-full overflow-visible"
              onMouseMove={handleMouseMove}
              onMouseLeave={handleMouseLeave}
            >
              <defs>
                <linearGradient id="electricGradient" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stopColor="#f3c56b" stopOpacity="0.32" />
                  <stop offset="100%" stopColor="#f3c56b" stopOpacity="0.0" />
                </linearGradient>
                <linearGradient id="waterGradient" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stopColor="#0f766e" stopOpacity="0.32" />
                  <stop offset="100%" stopColor="#0f766e" stopOpacity="0.0" />
                </linearGradient>
              </defs>

              {/* Grid Lines */}
              {gridLines.map((line, idx) => (
                <g key={idx}>
                  <line
                    x1={60}
                    y1={line.y}
                    x2={740}
                    y2={line.y}
                    stroke="#3d2a18"
                    strokeOpacity={0.07}
                    strokeDasharray="4 4"
                  />
                  
                  {/* Left Axis: Electric */}
                  <text
                    x={50}
                    y={line.y + 4}
                    textAnchor="end"
                    className="text-[10px] font-black fill-[#a65f16]/70"
                  >
                    {formatElectricPrice(line.electricValue)}
                  </text>

                  {/* Right Axis: Water */}
                  <text
                    x={750}
                    y={line.y + 4}
                    textAnchor="start"
                    className="text-[10px] font-black fill-[#0f766e]/75"
                  >
                    {formatWaterPrice(line.waterValue)}
                  </text>
                </g>
              ))}

              {/* Fills */}
              <path d={electricAreaD} fill="url(#electricGradient)" />
              <path d={waterAreaD} fill="url(#waterGradient)" />

              {/* Stroke Lines */}
              <path
                d={electricPathD}
                fill="none"
                stroke="#f3c56b"
                strokeWidth={3}
                strokeLinecap="round"
                strokeLinejoin="round"
              />
              <path
                d={waterPathD}
                fill="none"
                stroke="#0f766e"
                strokeWidth={3}
                strokeLinecap="round"
                strokeLinejoin="round"
              />

              {/* Nodes circles */}
              {points.map((p, idx) => (
                <g key={idx}>
                  <circle
                    cx={p.x}
                    cy={p.yElectric}
                    r={4}
                    fill="#fffaf1"
                    stroke="#f3c56b"
                    strokeWidth={2}
                  />
                  <circle
                    cx={p.x}
                    cy={p.yWater}
                    r={4}
                    fill="#fffaf1"
                    stroke="#0f766e"
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
                    cy={points[hoveredIndex].yElectric}
                    r={7}
                    fill="#f3c56b"
                    stroke="#fffaf1"
                    strokeWidth={2}
                    className="drop-shadow-[0_0_6px_rgba(243,197,107,0.8)]"
                  />
                  <circle
                    cx={points[hoveredIndex].x}
                    cy={points[hoveredIndex].yWater}
                    r={7}
                    fill="#0f766e"
                    stroke="#fffaf1"
                    strokeWidth={2}
                    className="drop-shadow-[0_0_6px_rgba(15,118,110,0.8)]"
                  />
                </g>
              )}
            </svg>

            {/* Tooltip Overlay */}
            {hoveredIndex !== null && points[hoveredIndex] && (
              <div
                className="absolute z-10 rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1]/96 p-4 shadow-2xl backdrop-blur-md text-xs pointer-events-none space-y-2 min-w-44"
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
                  <span className="flex items-center gap-1.5 text-[#a65f16]">
                    <span className="h-2 w-2 rounded-full bg-[#f3c56b]" />
                    Điện:
                  </span>
                  <span className="font-black text-[#24170d]">
                    {new Intl.NumberFormat('vi-VN').format(points[hoveredIndex].item.electric_price)} đ/kWh
                  </span>
                </div>
                <div className="flex items-center justify-between gap-4 font-semibold">
                  <span className="flex items-center gap-1.5 text-[#0f766e]">
                    <span className="h-2 w-2 rounded-full bg-[#0f766e]" />
                    Nước:
                  </span>
                  <span className="font-black text-[#24170d]">
                    {new Intl.NumberFormat('vi-VN').format(points[hoveredIndex].item.water_price)} đ/m³
                  </span>
                </div>
              </div>
            )}
          </div>
        )}
      </div>
    </section>
  )
}
