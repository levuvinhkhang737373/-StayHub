import { useEffect, useState, useCallback, useRef, useMemo, Fragment } from 'react'
import { cn } from '../../../../shared/lib/utils/cn'
import type { AdminRoomResource } from '../types/rooms.model'
import { createPortal } from 'react-dom'
import { deleteAdminRoom, fetchAdminRoomDetail, fetchAdminRooms, updateAdminRoomStatus, fetchBuilding, fetchRoomType } from '../services/rooms.service'
import { Eye, Trash2, Pencil, PackageOpen, Plus, Search, X, Power, ChevronLeft, ChevronRight, DoorOpen } from 'lucide-react'
import { Link } from 'react-router-dom'
import { useAdminSession } from '../../auth/hooks/admin-session-store'
import { AdminSelect } from '../../shared/components/AdminSelect'
import { formatCurrency } from '../../../../shared/lib/utils/format'
import { resolveAssetUrl } from '../../../../shared/lib/utils/asset-url'

const statusLabels: Record<number, string> = {
  1: 'Hoạt động',
  2: 'Đang bảo trì',
  3: 'Ngưng sử dụng'
}

function MetricCard({ label, value, tone }: { label: string; value: number; tone: "neutral" | "emerald" | "amber" | "stone" }) {
  const toneClassNames = {
    neutral: "border-[#f8e8c8]/12 bg-[#f8e8c8]/10 text-[#fff4df]",
    emerald: "border-[#0f766e]/35 bg-[#0f766e]/16 text-[#c8fff4]",
    amber: "border-[#f3c56b]/35 bg-[#f3c56b]/18 text-[#fff4df]",
    stone: "border-[#f8e8c8]/16 bg-[#f8e8c8]/10 text-[#f8e8c8]",
  }[tone];

  return (
    <div className={cn("rounded-2xl border px-4 py-3 backdrop-blur shadow-sm", toneClassNames)}>
      <p className="text-[10px] font-black uppercase tracking-[0.18em] opacity-60">{label}</p>
      <p className="mt-1.5 text-2xl font-black tracking-tight tabular-nums leading-none">{value}</p>
    </div>
  );
}

export function RoomsScreen() {
  const [rooms, setRooms] = useState<AdminRoomResource[]>([])
  const [allRooms, setAllRooms] = useState<AdminRoomResource[]>([])
  const [room, setRoom] = useState<AdminRoomResource | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [currentPage, setCurrentPage] = useState(1)
  const [isDetailOpen, setIsDetailOpen] = useState(false)
  const [statusRoom, setStatusRoom] = useState<AdminRoomResource | null>(null)
  const [newStatus, setNewStatus] = useState<number>(1)
  const [isStatusSaving, setIsStatusSaving] = useState(false)
  const { session } = useAdminSession()

  const [keyword, setKeyword] = useState('')
  const [selectedBuildingId, setSelectedBuildingId] = useState<string>('')
  const [selectedRoomTypeId, setSelectedRoomTypeId] = useState<string>('')
  const [selectedStatus, setSelectedStatus] = useState<string>('')
  const [buildings, setBuildings] = useState<any[]>([])
  const [roomTypes, setRoomTypes] = useState<any[]>([])

  const [itemsPerPage, setItemsPerPage] = useState(10)
  const isInitialMount = useRef(true)

  const filteredRooms = rooms

  const totalPages = Math.ceil(filteredRooms.length / itemsPerPage)
  const safeCurrentPage = Math.max(1, Math.min(currentPage, totalPages))
  const paginatedRooms = filteredRooms.slice(
    (safeCurrentPage - 1) * itemsPerPage,
    safeCurrentPage * itemsPerPage,
  )

  const visiblePages = useMemo(() => {
    const pages = new Set<number>([1, totalPages, safeCurrentPage - 1, safeCurrentPage, safeCurrentPage + 1])
    return Array.from(pages)
      .filter((page) => page >= 1 && page <= totalPages)
      .sort((a, b) => a - b)
  }, [safeCurrentPage, totalPages])

  useEffect(() => {
    setCurrentPage(1)
  }, [keyword, selectedBuildingId, selectedRoomTypeId, selectedStatus])

  const SUPERADMIN_ROLE = Number(import.meta.env.VITE_SUPERADMIN_ROLE)
  const isSuperAdmin = session?.admin?.role === SUPERADMIN_ROLE

  const loadRooms = useCallback(async (isInitial = false) => {
    try {
      setIsLoading(true)
      const res = await fetchAdminRooms({
        keyword: keyword.trim() || undefined,
        building_id: selectedBuildingId ? Number(selectedBuildingId) : undefined,
        room_type_id: selectedRoomTypeId ? Number(selectedRoomTypeId) : undefined,
        status: selectedStatus ? Number(selectedStatus) : undefined,
      })
      const list = res.result || []
      setRooms(list)
      if (isInitial) {
        setAllRooms(list)
      }
    } catch (error) {
      console.log(error)
    } finally {
      setIsLoading(false)
    }
  }, [keyword, selectedBuildingId, selectedRoomTypeId, selectedStatus])

  const closeRoomDetail = () => {
    setIsDetailOpen(false)
  }

  const viewRoom = async (detail: any) => {
    setRoom(null)
    setIsDetailOpen(true)
    try {
      const res = await fetchAdminRoomDetail(detail)
      setRoom(res.result)
    } catch (error) {
      console.log(error)
    }
  }

  const submitRoomStatus = async () => {
    if (!statusRoom) return
    try {
      setIsStatusSaving(true)
      const res = await updateAdminRoomStatus(statusRoom.id, newStatus)

      if (res && res.status !== false) {
        setStatusRoom(null)
        await loadRooms(false)

        if (isDetailOpen && room?.id === statusRoom.id) {
          const detailRes = await fetchAdminRoomDetail(statusRoom.id)
          setRoom(detailRes.result)
        }
      } else {
        alert(res?.message || "Cập nhật trạng thái thất bại.")
      }
    } catch (error: any) {
      console.error("Lỗi cập nhật trạng thái:", error)
      const errorMessage = error?.response?.data?.message || error?.message || "Đã xảy ra lỗi hệ thống."
      alert("Thất bại: " + errorMessage)
    } finally {
      setIsStatusSaving(false)
    }
  }

  const deleteRoom = async (id: any) => {
    try {
      if (confirm("Bạn có muốn xóa phòng này không?")) {
        const res = await deleteAdminRoom(id);

        if (res && res.status !== false) {
          alert("Xóa thành công");
          await loadRooms(false);
        } else {
          alert(res?.message || "Không thể xóa phòng này do vi phạm điều kiện ràng buộc.");
        }
      }
    } catch (error: any) {
      console.error("Lỗi xóa phòng:", error);
      const errorMessage = error?.response?.data?.message || error?.message || "Đã xảy ra lỗi hệ thống khi xóa.";
      alert("Xóa thất bại: " + errorMessage);
    }
  };

  const loadFiltersData = async () => {
    try {
      const [buildingsRes, roomTypesRes] = await Promise.all([
        fetchBuilding(),
        fetchRoomType(),
      ])
      setBuildings(buildingsRes.result || [])
      setRoomTypes(roomTypesRes.result || [])
    } catch (error) {
      console.error("Lỗi tải thông tin bộ lọc:", error)
    }
  }

  useEffect(() => {
    void loadRooms(true)
    void loadFiltersData()
  }, [])

  useEffect(() => {
    if (isInitialMount.current) {
      isInitialMount.current = false
      return
    }
    const timer = setTimeout(() => {
      void loadRooms(false)
    }, 250)
    return () => clearTimeout(timer)
  }, [keyword, selectedBuildingId, selectedRoomTypeId, selectedStatus, loadRooms])

  return (
    <div className="space-y-6 text-[#24170d]">
      {/* Premium Header Container */}
      <div className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
        <div className="relative p-5 text-[#fff4df] lg:p-6">
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_18%,rgba(243,197,107,0.24),transparent_30%),radial-gradient(circle_at_82%_16%,rgba(15,118,110,0.22),transparent_32%),linear-gradient(135deg,#24170d_0%,#3d2a18_54%,#0f3f3b_100%)]" />
          <div className="relative flex min-w-0 flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div className="min-w-0">
              <span className="block text-xs font-black uppercase tracking-[0.18em] text-[#f3c56b]/80">QUẢN LÝ LƯU TRÚ</span>
              <h1 className="mt-3 text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl lg:text-[2.65rem] flex items-center gap-3">
                <DoorOpen className="h-8 w-8 text-[#f3c56b] shrink-0" />
                Quản lý phòng
              </h1>
            </div>

            {isSuperAdmin && (
              <div className="flex w-full flex-col gap-3 items-end sm:flex-row sm:justify-end xl:w-auto">
                <Link
                  to="/admin/rooms/create"
                  className="inline-flex h-10 w-fit self-end xl:self-auto items-center justify-center gap-2 whitespace-nowrap rounded-xl bg-[#f3c56b] px-4 text-sm font-black text-[#24170d] shadow-xl shadow-[#a65f16]/20 transition hover:bg-[#ffd56f] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/35 active:scale-[0.98] xl:min-w-40"
                >
                  <Plus className="h-4 w-4 shrink-0 text-[#24170d] stroke-[2.8]" />
                  Thêm Phòng
                </Link>
              </div>
            )}
          </div>

          {/* Metric Cards */}
          <div className="relative mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <MetricCard label="Tổng số phòng" value={allRooms.length || rooms.length} tone="neutral" />
            <MetricCard label="Đang hoạt động" value={allRooms.filter(r => Number(r.status) === 1).length} tone="emerald" />
            <MetricCard label="Đang bảo trì" value={allRooms.filter(r => Number(r.status) === 2).length} tone="amber" />
            <MetricCard label="Ngưng sử dụng" value={allRooms.filter(r => Number(r.status) === 3).length} tone="stone" />
          </div>
        </div>
      </div>

      {/* Search & Filter Controls */}
      <div className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/60 p-5 shadow-sm backdrop-blur-sm space-y-4">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 items-start">
          <div className="space-y-1.5">
            <label className="text-[10px] font-black uppercase tracking-wider text-[#8b5e34]/70">Tìm theo Số Phòng</label>
            <div className="relative">
              <Search className="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#a65f16]" />
              <input
                type="text"
                placeholder="Nhập số phòng..."
                value={keyword}
                onChange={(e) => setKeyword(e.target.value)}
                className="h-12 w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] pl-10 pr-3 text-xs font-bold text-[#3d2a18] shadow-sm outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20"
              />
            </div>
          </div>

          <div className="space-y-1.5">
            <label className="text-[10px] font-black uppercase tracking-wider text-[#8b5e34]/70">Tòa nhà</label>
            <AdminSelect
              value={selectedBuildingId}
              options={[
                { value: '', label: 'Tất cả tòa nhà' },
                ...buildings.map((b) => ({ value: String(b.id), label: b.name }))
              ]}
              onChange={(val) => setSelectedBuildingId(String(val))}
              placeholder="Tất cả tòa nhà"
            />
          </div>

          <div className="space-y-1.5">
            <label className="text-[10px] font-black uppercase tracking-wider text-[#8b5e34]/70">Loại phòng</label>
            <AdminSelect
              value={selectedRoomTypeId}
              options={[
                { value: '', label: 'Tất cả loại phòng' },
                ...roomTypes.map((rt) => ({ value: String(rt.id), label: rt.name }))
              ]}
              onChange={(val) => setSelectedRoomTypeId(String(val))}
              placeholder="Tất cả loại phòng"
            />
          </div>

          <div className="space-y-1.5">
            <label className="text-[10px] font-black uppercase tracking-wider text-[#8b5e34]/70">Trạng thái</label>
            <AdminSelect
              value={selectedStatus}
              options={[
                { value: '', label: 'Tất cả trạng thái' },
                { value: '1', label: 'Hoạt động', tone: 'success' },
                { value: '2', label: 'Đang bảo trì', tone: 'warning' },
                { value: '3', label: 'Ngưng sử dụng', tone: 'danger' }
              ]}
              onChange={(val) => setSelectedStatus(String(val))}
              placeholder="Tất cả trạng thái"
            />
          </div>
        </div>

        {(keyword || selectedBuildingId || selectedRoomTypeId || selectedStatus) && (
          <div className="flex flex-wrap items-center gap-2 pt-1 border-t border-[#3d2a18]/5">
            <span className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/65">Bộ lọc hoạt động:</span>
            {keyword && (
              <span className="inline-flex items-center gap-1.5 rounded-full border border-[#f3c56b]/45 bg-[#f3c56b]/15 px-3 py-1 text-[11px] font-black text-[#8a4f18]">
                Số phòng: {keyword}
                <button type="button" onClick={() => setKeyword('')} className="text-[#a65f16] hover:text-[#8a4f18]"><X className="h-3 w-3" /></button>
              </span>
            )}
            {selectedBuildingId && (
              <span className="inline-flex items-center gap-1.5 rounded-full border border-[#f3c56b]/45 bg-[#f3c56b]/15 px-3 py-1 text-[11px] font-black text-[#8a4f18]">
                Tòa nhà: {buildings.find(b => String(b.id) === selectedBuildingId)?.name}
                <button type="button" onClick={() => setSelectedBuildingId('')} className="text-[#a65f16] hover:text-[#8a4f18]"><X className="h-3 w-3" /></button>
              </span>
            )}
            {selectedRoomTypeId && (
              <span className="inline-flex items-center gap-1.5 rounded-full border border-[#f3c56b]/45 bg-[#f3c56b]/15 px-3 py-1 text-[11px] font-black text-[#8a4f18]">
                Loại phòng: {roomTypes.find(rt => String(rt.id) === selectedRoomTypeId)?.name}
                <button type="button" onClick={() => setSelectedRoomTypeId('')} className="text-[#a65f16] hover:text-[#8a4f18]"><X className="h-3 w-3" /></button>
              </span>
            )}
            {selectedStatus && (
              <span className="inline-flex items-center gap-1.5 rounded-full border border-[#f3c56b]/45 bg-[#f3c56b]/15 px-3 py-1 text-[11px] font-black text-[#8a4f18]">
                Trạng thái: {selectedStatus === '1' ? 'Hoạt động' : selectedStatus === '2' ? 'Đang bảo trì' : 'Ngưng sử dụng'}
                <button type="button" onClick={() => setSelectedStatus('')} className="text-[#a65f16] hover:text-[#8a4f18]"><X className="h-3 w-3" /></button>
              </span>
            )}
            <button
              type="button"
              onClick={() => {
                setKeyword('')
                setSelectedBuildingId('')
                setSelectedRoomTypeId('')
                setSelectedStatus('')
              }}
              className="text-xs font-black text-[#8b5e34]/65 underline underline-offset-4 transition hover:text-[#24170d]"
            >
              Xóa bộ lọc
            </button>
          </div>
        )}
      </div>

      {/* Table Section */}
      <div className="overflow-x-auto rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
        <table className="w-full min-w-[1000px] text-left">
          <thead className="bg-[#24170d] text-[10px] font-black uppercase tracking-[0.18em] text-[#f8e8c8]">
            <tr>
              <th className="px-5 py-4">Số Phòng</th>
              <th className="px-5 py-4">Tòa nhà</th>
              <th className="px-5 py-4 text-center">Loại Phòng</th>
              <th className="px-5 py-4 text-center">Tầng</th>
              <th className="px-5 py-4 text-center">Số người đang ở</th>
              <th className="px-5 py-4 text-center">Trạng thái</th>
              <th className="px-5 py-4"><div className="flex justify-end"><div className="w-[184px] text-center">Thao tác</div></div></th>
            </tr>
          </thead>

          <tbody className="divide-y divide-[#3d2a18]/8">
            {isLoading &&
              Array.from({ length: 5 }).map((_, index) => (
                <tr key={index}>
                  <td colSpan={7} className="px-5 py-4">
                    <div className="h-12 animate-pulse rounded-2xl bg-stone-100" />
                  </td>
                </tr>
              ))}

            {!isLoading &&
              paginatedRooms.map((roomItem) => (
                <tr key={roomItem.id} className="group transition hover:bg-[#f3c56b]/12">
                  <td className="px-5 py-4">
                    <p className="truncate text-[13px] font-black tracking-tight text-[#24170d]">
                      {roomItem.room_number}
                    </p>
                  </td>

                  <td className="px-5 py-4 text-[13px] font-bold text-[#6f6254]">
                    {roomItem.building?.name}
                  </td>

                  <td className="px-5 py-4 text-center text-[13px] font-bold text-[#6f6254]">
                    {roomItem.room_type?.name}
                  </td>

                  <td className="px-5 py-4 text-center text-[13px] font-bold text-[#6f6254]">
                    {roomItem.floor}
                  </td>

                  <td className="px-5 py-4 text-center text-[13px] font-bold text-[#6f6254]">
                    {roomItem.current_occupants}
                  </td>

                  <td className="px-5 py-4 text-center">
                    <span
                      className={cn(
                        'inline-flex items-center justify-center whitespace-nowrap rounded-full border px-2.5 py-0.5 text-[11px] font-black shadow-sm',
                        Number(roomItem.status) === 1
                          ? 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]'
                          : Number(roomItem.status) === 2
                            ? 'border-amber-500/20 bg-amber-50 text-amber-700'
                            : 'border-red-500/20 bg-red-50 text-red-700',
                      )}
                    >
                      {statusLabels[Number(roomItem.status)] || 'Không xác định'}
                    </span>
                  </td>

                  <td className="px-5 py-4">
                    <div className="flex items-center justify-end gap-2">
                      <button
                        type="button"
                        onClick={() => void viewRoom(roomItem.id)}
                        className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#0f766e]/25 hover:bg-[#0f766e]/10 hover:text-[#0f5f59] focus:outline-none focus:ring-4 focus:ring-[#0f766e]/10 active:scale-95"
                        title="Xem chi tiết"
                      >
                        <Eye className="h-5 w-5" />
                      </button>

                      <button
                        type="button"
                        onClick={() => {
                          setStatusRoom(roomItem)
                          setNewStatus(Number(roomItem.status))
                        }}
                        className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#3d2a18]/25 hover:bg-[#f3c56b]/15 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#3d2a18]/10 active:scale-95"
                        title="Đổi trạng thái phòng"
                      >
                        <Power className="h-5 w-5" />
                      </button>

                      {isSuperAdmin && (
                        <Link
                          to={`/admin/rooms/update/${roomItem.id}`}
                          className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#3d2a18]/25 hover:bg-[#f3c56b]/15 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#3d2a18]/10 active:scale-95"
                          title="Sửa thông tin phòng"
                        >
                          <Pencil className="h-4.5 w-4.5" />
                        </Link>
                      )}

                      {isSuperAdmin && (
                        <button
                          type="button"
                          onClick={() => void deleteRoom(roomItem.id)}
                          className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600 focus:outline-none focus:ring-4 focus:ring-rose-100 active:scale-95"
                          title="Xóa phòng"
                        >
                          <Trash2 className="h-5 w-5" />
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}

            {!isLoading && filteredRooms.length === 0 && (
              <tr>
                <td colSpan={7} className="px-5 py-20 text-center">
                  <div className="mx-auto flex max-w-sm flex-col items-center">
                    <p className="text-lg font-black tracking-tight text-[#24170d]">
                      Không tìm thấy phòng
                    </p>
                    <p className="mt-2 text-sm font-semibold leading-6 text-[#6f6254]">
                      Hãy tạo phòng mới hoặc đổi bộ lọc hiện tại.
                    </p>
                  </div>
                </td>
              </tr>
            )}
          </tbody>
        </table>

        {/** Pagination */}
        {!isLoading && totalPages > 1 && (
          <div className="flex flex-col gap-4 border-t border-[#3d2a18]/10 bg-[#fff8eb]/85 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
            <p className="text-xs font-black text-[#6f6254]">
              Hiển thị <span className="tabular-nums text-[#24170d]">{filteredRooms.length === 0 ? 0 : (currentPage - 1) * itemsPerPage + 1}</span>–
              <span className="tabular-nums text-[#24170d]">{Math.min(currentPage * itemsPerPage, filteredRooms.length)}</span> / <span className="tabular-nums text-[#24170d]">{filteredRooms.length}</span> phòng
            </p>

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
              <div className="w-full sm:w-36">
                <AdminSelect
                  value={itemsPerPage}
                  options={[
                    { value: 5, label: '5 dòng', tone: 'default' as const },
                    { value: 10, label: '10 dòng', tone: 'default' as const },
                    { value: 20, label: '20 dòng', tone: 'default' as const },
                    { value: 50, label: '50 dòng', tone: 'default' as const }
                  ]}
                  onChange={(nextValue) => {
                    setItemsPerPage(Number(nextValue))
                    setCurrentPage(1)
                  }}
                  menuPlacement="top"
                />
              </div>

              <div className="flex items-center justify-end gap-1.5">
                <button
                  type="button"
                  disabled={currentPage <= 1}
                  onClick={() => setCurrentPage((p) => p - 1)}
                  className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:cursor-not-allowed disabled:opacity-40"
                  aria-label="Trang trước"
                >
                  <ChevronLeft className="h-4 w-4" />
                </button>

                {visiblePages.map((page, index) => {
                  const previousPage = visiblePages[index - 1]
                  const hasGap = previousPage && page - previousPage > 1

                  return (
                    <Fragment key={page}>
                      {hasGap && <span className="px-1 text-xs font-black text-[#8b5e34]/60">...</span>}
                      <button
                        type="button"
                        onClick={() => setCurrentPage(page)}
                        className={cn(
                          'inline-flex h-9 min-w-9 items-center justify-center rounded-xl border px-3 text-xs font-black transition',
                          page === safeCurrentPage
                            ? 'border-[#24170d] bg-[#24170d] text-[#fff4df] shadow-sm'
                            : 'border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] hover:bg-[#f3c56b]/15',
                        )}
                      >
                        {page}
                      </button>
                    </Fragment>
                  )
                })}

                <button
                  type="button"
                  disabled={currentPage >= totalPages}
                  onClick={() => setCurrentPage((p) => p + 1)}
                  className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:cursor-not-allowed disabled:opacity-40"
                  aria-label="Trang sau"
                >
                  <ChevronRight className="h-4 w-4" />
                </button>
              </div>
            </div>
          </div>
        )}
      </div>

      {/** MODAL SHOW DETAIL ROOM WITH ASSETS */}
      {isDetailOpen && createPortal(
        <div className="fixed inset-0 z-[60] flex items-center justify-center p-4">
          <button type="button" aria-label="Đóng chi tiết phòng" onClick={closeRoomDetail} className="absolute inset-0 bg-stone-950/65 backdrop-blur-sm" />

          <div className="relative z-10 w-full max-w-2xl overflow-hidden rounded-[2rem] bg-[#fffaf1] shadow-2xl border border-[#3d2a18]/10 flex flex-col max-h-[90vh]">
            {/* Modal Header */}
            <div className="bg-[#24170d] px-6 py-5 text-[#fff4df] text-base font-black tracking-tight shrink-0 flex items-center justify-between">
              <span>Thông tin chi tiết của phòng {room?.room_number}</span>
              <button type="button" onClick={closeRoomDetail} className="text-[#f8e8c8] hover:text-white text-lg font-bold">✕</button>
            </div>

            {/* Modal Body Container */}
            <div className="divide-y divide-[#3d2a18]/8 px-6 py-2 overflow-y-auto custom-scrollbar">
              <div className="flex items-center justify-between py-3">
                <span className="text-[11px] font-black uppercase tracking-widest text-[#8b5e34]">Số phòng</span>
                <span className="text-[13px] font-bold text-[#24170d]">{room?.room_number}</span>
              </div>
              <div className="flex items-center justify-between py-3">
                <span className="text-[11px] font-black uppercase tracking-widest text-[#8b5e34]">Tòa nhà</span>
                <span className="text-[13px] font-bold text-[#24170d]">{room?.building?.name}</span>
              </div>
              <div className="flex items-center justify-between py-3">
                <span className="text-[11px] font-black uppercase tracking-widest text-[#8b5e34]">Loại phòng</span>
                <span className="text-[13px] font-bold text-[#24170d]">{room?.room_type?.name}</span>
              </div>
              <div className="flex items-center justify-between py-3">
                <span className="text-[11px] font-black uppercase tracking-widest text-[#8b5e34]">Tầng</span>
                <span className="text-[13px] font-bold text-[#24170d]">{room?.floor}</span>
              </div>
              <div className="flex items-center justify-between py-3">
                <span className="text-[11px] font-black uppercase tracking-widest text-[#8b5e34]">Diện tích</span>
                <span className="text-[13px] font-bold text-[#24170d]">{room?.area_m2} m²</span>
              </div>
              <div className="flex items-center justify-between py-3">
                <span className="text-[11px] font-black uppercase tracking-widest text-[#8b5e34]">Giá</span>
                <span className="text-[13px] font-bold text-[#24170d]">{formatCurrency(room?.base_price)}</span>
              </div>
              <div className="flex items-center justify-between py-3">
                <span className="text-[11px] font-black uppercase tracking-widest text-[#8b5e34]">Tổng số người</span>
                <span className="text-[13px] font-bold text-[#24170d]">{room?.max_occupants}</span>
              </div>
              <div className="flex items-center justify-between py-3">
                <span className="text-[11px] font-black uppercase tracking-widest text-[#8b5e34]">Số người hiện tại</span>
                <span className="text-[13px] font-bold text-[#24170d]">{room?.current_occupants}</span>
              </div>
              <div className="flex items-center justify-between py-3">
                <span className="text-[11px] font-black uppercase tracking-widest text-[#8b5e34]">Trạng thái</span>
                <span className={cn(
                  'inline-flex items-center justify-center rounded-full border px-2.5 py-0.5 text-[11px] font-black',
                  Number(room?.status) === 1
                    ? 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]'
                    : Number(room?.status) === 2
                      ? 'border-amber-500/20 bg-amber-50 text-amber-700'
                      : 'border-red-500/20 bg-red-50 text-red-700',
                )}>
                  {statusLabels[Number(room?.status)] || 'Không xác định'}
                </span>
              </div>
              <div className="flex items-start justify-between gap-4 py-3">
                <span className="text-[11px] font-black uppercase tracking-widest text-[#8b5e34]">Mô tả</span>
                <span className="text-right text-[13px] font-bold leading-relaxed text-[#24170d]">{room?.description ?? '—'}</span>
              </div>

              {/* DANH SÁCH TÀI SẢN BÀN GIAO */}
              <div className="flex flex-col items-start gap-3 py-4">
                <span className="text-[11px] font-black uppercase tracking-widest text-[#8b5e34] flex items-center gap-1.5">
                  <PackageOpen size={14} className="text-[#8b5e34]" />
                  Tài sản kèm theo ({room?.assets?.length ?? 0})
                </span>

                <div className="w-full">
                  {room?.assets && room.assets.length > 0 ? (
                    <div className="flex flex-col gap-2 w-full">
                      {room.assets.map((ast: any, idx: number) => (
                        <div key={ast.id ?? idx} className="flex flex-col sm:flex-row sm:items-center justify-between gap-1.5 rounded-xl border border-[#3d2a18]/10 bg-[#fefcf7] p-3 text-[13px]">
                          <div className="flex items-center gap-2">
                            <span className="font-extrabold text-[#24170d]">
                              {ast.asset_template?.name || 'Tài sản không tên'}
                            </span>
                            <span className="rounded-md bg-[#24170d]/5 border border-[#24170d]/10 px-1.5 py-0.5 text-xs font-bold text-[#8b5e34]">
                              SL: {ast.quantity ?? 1}
                            </span>
                          </div>
                          {ast.note && (
                            <p className="text-xs text-stone-500 italic truncate max-w-xs sm:text-right">
                              Ghi chú: {ast.note}
                            </p>
                          )}
                        </div>
                      ))}
                    </div>
                  ) : (
                    <span className="text-[13px] font-bold text-stone-400 italic">Phòng này chưa được bàn giao tài sản nào.</span>
                  )}
                </div>
              </div>

              {/* Danh mục Hình ảnh */}
              <div className="flex flex-col items-start gap-3 py-4">
                <span className="text-[11px] font-black uppercase tracking-widest text-[#8b5e34]">
                  Hình ảnh ({room?.images?.length ?? 0})
                </span>

                <div className="w-full">
                  {room?.images && room.images.length > 0 ? (
                    <div className="grid grid-cols-3 gap-3 p-1">
                      {room.images.map((item, index) => {
                        const fullImageUrl = resolveAssetUrl(item.image_path);

                        return (
                          <div
                            key={item.id ?? index}
                            className="group relative aspect-square overflow-hidden rounded-xl border border-[#3d2a18]/15 bg-stone-100 shadow-sm transition-all"
                          >
                            <img
                              src={fullImageUrl}
                              alt={`room-media-${index}`}
                              className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                              onError={(e) => {
                                (e.target as HTMLImageElement).src = 'https://placehold.co/150?text=Khong+Tim+Thay+Anh';
                              }}
                            />
                          </div>
                        );
                      })}
                    </div>
                  ) : (
                    <span className="text-[13px] font-bold text-stone-400 italic">Phòng này chưa cập nhật hình ảnh.</span>
                  )}
                </div>
              </div>
            </div>

            {/* Modal Footer */}
            <div className="border-t border-[#3d2a18]/8 px-6 py-3 flex justify-end bg-stone-50/50 rounded-b-[2rem] shrink-0">
              <button
                type="button"
                onClick={closeRoomDetail}
                className="px-4 py-2 rounded-xl border border-[#3d2a18]/20 text-[#24170d] text-xs font-bold transition hover:bg-[#3d2a18]/5"
              >
                Đóng
              </button>
            </div>
          </div>
        </div>,
        document.body
      )}

      {/** MODAL CHANGE STATUS ROOM */}
      {statusRoom && createPortal(
        <div className="fixed inset-0 z-[60] flex items-center justify-center p-4">
          <button type="button" aria-label="Đóng cập nhật trạng thái" onClick={() => setStatusRoom(null)} className="absolute inset-0 bg-stone-950/65 backdrop-blur-sm" />

          <div className="relative z-10 w-full max-w-md overflow-hidden rounded-[2rem] bg-[#fffaf1] shadow-2xl border border-[#3d2a18]/10 flex flex-col">
            {/* Modal Header */}
            <div className="bg-[#24170d] px-6 py-5 text-[#fff4df] text-base font-black tracking-tight shrink-0 flex items-center justify-between">
              <span>Cập nhật trạng thái phòng</span>
              <button type="button" onClick={() => setStatusRoom(null)} className="text-[#f8e8c8] hover:text-white text-lg font-bold">✕</button>
            </div>

            {/* Modal Body */}
            <div className="p-6 space-y-5">
              <div>
                <p className="text-xs font-bold text-[#8b5e34]/70">
                  Phòng {statusRoom.room_number} · Trạng thái hiện tại: <span className="font-black text-[#24170d]">{statusLabels[Number(statusRoom.status)]}</span>
                </p>
              </div>

              <div className="space-y-1.5">
                <label className="text-[10px] font-black uppercase tracking-wider text-[#8b5e34]/70">Trạng thái mới *</label>
                <AdminSelect
                  value={newStatus}
                  options={[
                    { value: 1, label: 'Hoạt động', tone: 'success' },
                    { value: 2, label: 'Đang bảo trì', tone: 'warning' },
                    { value: 3, label: 'Ngưng sử dụng', tone: 'danger' }
                  ]}
                  onChange={(val) => setNewStatus(Number(val))}
                  placeholder="Chọn trạng thái"
                />
              </div>

              {/* Modal Footer */}
              <div className="flex gap-3 pt-2">
                <button
                  type="button"
                  onClick={() => setStatusRoom(null)}
                  className="h-12 flex-1 rounded-xl border border-[#3d2a18]/20 text-[#24170d] text-xs font-bold transition hover:bg-[#3d2a18]/5"
                >
                  Hủy
                </button>
                <button
                  type="button"
                  disabled={isStatusSaving}
                  onClick={() => void submitRoomStatus()}
                  className="h-12 flex-1 rounded-xl bg-[#24170d] text-[#fff4df] text-xs font-bold transition hover:bg-stone-900 disabled:opacity-60"
                >
                  {isStatusSaving ? 'Đang lưu...' : 'Cập nhật'}
                </button>
              </div>
            </div>
          </div>
        </div>,
        document.body
      )}
    </div>
  )
}
