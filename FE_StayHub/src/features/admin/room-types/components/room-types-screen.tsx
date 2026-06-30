import { useCallback, useEffect, useMemo, useState, Fragment } from 'react'
import { BedDouble, ChevronLeft, ChevronRight, Edit3, Eye, Plus, Power, Search, Trash2, X } from 'lucide-react'
import { RoomTypeModal } from './room-type-modal'
import { cn } from '../../../../shared/lib/utils/cn'
import { AdminSelect } from '../../shared/components/AdminSelect'
import {
  deleteAdminRoomType,
  fetchAdminRoomTypeDetail,
  fetchAdminRoomTypes,
  updateAdminRoomTypeStatus,
} from '../services/room-types.service'
import type { AdminRoomTypeResource, AdminPaginationMeta } from '../types/room-type-api.model'
import type { RoomTypeFormValues } from '../validations/room-type.validation'



const defaultForm: RoomTypeFormValues = {
  name: '',
  description: '',
  status: 1,
}

const statusLabels: Record<number, string> = {
  1: 'Hoạt động',
  2: 'Ngừng hoạt động',
}

const statusOptions = [
  { value: '', label: 'Tất cả trạng thái', tone: 'default' as const },
  { value: '1', label: 'Hoạt động', tone: 'success' as const },
  { value: '2', label: 'Ngừng hoạt động', tone: 'danger' as const },
]

const inputClass = 'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20'

export function RoomTypesScreen() {
  const [keyword, setKeyword] = useState('')
  const [selectedStatus, setSelectedStatus] = useState('')
  const [roomTypes, setRoomTypes] = useState<AdminRoomTypeResource[]>([])
  const [allRoomTypes, setAllRoomTypes] = useState<AdminRoomTypeResource[]>([])
  const [perPage, setPerPage] = useState(10)
  const [currentPage, setCurrentPage] = useState(1)
  const [paginationMeta, setPaginationMeta] = useState<AdminPaginationMeta | null>(null)
  const [editingRoomTypeId, setEditingRoomTypeId] = useState<number | null>(null)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [form, setForm] = useState<RoomTypeFormValues>(defaultForm)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [detailRoomType, setDetailRoomType] = useState<AdminRoomTypeResource | null>(null)
  const [isDetailOpen, setIsDetailOpen] = useState(false)
  const [isDetailLoading, setIsDetailLoading] = useState(false)
  const [detailErrorMessage, setDetailErrorMessage] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [statusChangingId, setStatusChangingId] = useState<number | null>(null)

  const loadAllRoomTypes = useCallback(async () => {
    try {
      const response = await fetchAdminRoomTypes({ per_page: 1000 })
      const data = response.result?.data ?? []
      setAllRoomTypes(data)
    } catch (error) {
      console.error('Failed to load all room types:', error)
    }
  }, [])

  const loadRoomTypes = useCallback(async () => {
    setIsLoading(true)
    setErrorMessage(null)

    try {
      const roomTypeResponse = await fetchAdminRoomTypes({
        keyword: keyword.trim() || undefined,
        status: selectedStatus ? Number(selectedStatus) : undefined,
        page: currentPage,
        per_page: perPage,
      })

      const result = roomTypeResponse.result
      const data = result?.data ?? []
      const meta = result?.meta ?? null

      setRoomTypes(data)
      setPaginationMeta(meta)

      if (meta?.last_page && currentPage > meta.last_page) {
        setCurrentPage(meta.last_page)
      }
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Không thể tải danh sách loại phòng.')
    } finally {
      setIsLoading(false)
    }
  }, [keyword, selectedStatus, currentPage, perPage])

  useEffect(() => {
    void loadAllRoomTypes()
  }, [loadAllRoomTypes])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadRoomTypes()
    }, 250)

    return () => window.clearTimeout(timer)
  }, [loadRoomTypes])

  useEffect(() => {
    setCurrentPage(1)
  }, [keyword, selectedStatus])

  const activeRoomTypes = useMemo(() => allRoomTypes.filter((item) => Number(item.status) === 1).length, [allRoomTypes])
  const totalRooms = useMemo(() => allRoomTypes.reduce((sum, item) => sum + Number(item.rooms_count || 0), 0), [allRoomTypes])
  const openCreateForm = () => {
    if (editingRoomTypeId !== null) {
      setForm({ ...defaultForm })
    }
    setEditingRoomTypeId(null)
    setErrorMessage(null)
    setSuccessMessage(null)
    setIsFormOpen(true)
  }

  const editRoomType = (roomType: AdminRoomTypeResource) => {
    if (editingRoomTypeId !== roomType.id) {
      setEditingRoomTypeId(roomType.id)
      setForm({
        name: roomType.name || '',
        description: roomType.description || '',
        status: Number(roomType.status || 1),
      })
      setErrorMessage(null)
      setSuccessMessage(null)
    }
    setIsFormOpen(true)
  }

  const viewRoomType = async (roomType: AdminRoomTypeResource) => {
    setDetailRoomType(roomType)
    setIsDetailOpen(true)
    setIsDetailLoading(true)
    setDetailErrorMessage(null)

    try {
      const response = await fetchAdminRoomTypeDetail(roomType.id)
      setDetailRoomType(response.result)
    } catch (error) {
      setDetailErrorMessage(error instanceof Error ? error.message : 'Không thể tải chi tiết loại phòng.')
    } finally {
      setIsDetailLoading(false)
    }
  }

  const closeRoomTypeDetail = () => {
    setIsDetailOpen(false)
    setDetailRoomType(null)
    setDetailErrorMessage(null)
  }

  const handleCancelForm = () => {
    setIsFormOpen(false)
    setEditingRoomTypeId(null)
    setForm({ ...defaultForm })
  }

  const handleCloseForm = () => {
    setIsFormOpen(false)
  }

  const handleSubmitSuccess = () => {
    setIsFormOpen(false)
    setEditingRoomTypeId(null)
    setForm({ ...defaultForm })
    setSuccessMessage(editingRoomTypeId ? 'Cập nhật loại phòng thành công.' : 'Tạo loại phòng thành công.')
    void loadRoomTypes()
    void loadAllRoomTypes()
  }

  const toggleRoomTypeStatus = async (roomType: AdminRoomTypeResource) => {
    const nextStatus = Number(roomType.status) === 1 ? 2 : 1

    try {
      setStatusChangingId(roomType.id)
      setErrorMessage(null)
      setSuccessMessage(null)
      await updateAdminRoomTypeStatus(roomType.id, nextStatus)
      setSuccessMessage(`${nextStatus === 1 ? 'Kích hoạt' : 'Ngừng hoạt động'} loại phòng thành công.`)
      await loadRoomTypes()
      await loadAllRoomTypes()
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Không thể đổi trạng thái loại phòng.')
    } finally {
      setStatusChangingId(null)
    }
  }

  const removeRoomType = async (roomType: AdminRoomTypeResource) => {
    if (!window.confirm(`Bạn có chắc chắn muốn xóa loại phòng ${roomType.name}?`)) return

    try {
      setErrorMessage(null)
      await deleteAdminRoomType(roomType.id)
      setSuccessMessage('Xóa loại phòng thành công.')
      await loadRoomTypes()
      await loadAllRoomTypes()
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Không thể xóa loại phòng.')
    }
  }

  const clearFilters = () => {
    setKeyword('')
    setSelectedStatus('')
    setCurrentPage(1)
  }

  const safeCurrentPage = Math.max(1, Math.min(currentPage, paginationMeta?.last_page ?? currentPage))
  const totalPages = Math.max(1, paginationMeta?.last_page ?? (roomTypes.length >= perPage ? currentPage + 1 : currentPage))
  const paginationStart = paginationMeta?.from ?? (roomTypes.length === 0 ? 0 : (safeCurrentPage - 1) * perPage + 1)
  const paginationEnd = paginationMeta?.to ?? (roomTypes.length === 0 ? 0 : (safeCurrentPage - 1) * perPage + roomTypes.length)

  const visiblePages = useMemo(() => {
    const pages = new Set<number>([1, totalPages, safeCurrentPage - 1, safeCurrentPage, safeCurrentPage + 1])
    return Array.from(pages)
      .filter((page) => page >= 1 && page <= totalPages)
      .sort((a, b) => a - b)
  }, [safeCurrentPage, totalPages])

  return (
    <>
      <>
      <section className="space-y-5 sm:space-y-6 text-[#24170d]">
        <div className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
          <div className="relative p-5 text-[#fff4df] sm:p-6 lg:p-7">
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_18%,rgba(243,197,107,0.24),transparent_30%),radial-gradient(circle_at_82%_16%,rgba(15,118,110,0.22),transparent_32%),linear-gradient(135deg,#24170d_0%,#3d2a18_54%,#0f3f3b_100%)]" />
            <div className="relative flex min-w-0 flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
              <div className="min-w-0">
                <span className="block text-xs font-black uppercase tracking-[0.18em] text-[#f3c56b]/80">QUẢN LÝ LƯU TRÚ</span>
                <h1 className="mt-3 text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl lg:text-[2.65rem] flex items-center gap-3">
                  <BedDouble className="h-8 w-8 text-[#f3c56b] shrink-0" />
                  Loại phòng
                </h1>
                <p className="mt-2.5 text-xs font-semibold text-[#f8e8c8]/70">Cấu hình thông tin, diện tích và định giá cho các loại phòng trong hệ thống.</p>
              </div>
              <button type="button" onClick={openCreateForm} className="inline-flex h-9 w-fit self-end xl:self-auto items-center justify-center gap-2 rounded-xl bg-[#f3c56b] px-4 text-sm font-black text-[#24170d] shadow-xl shadow-[#a65f16]/20 transition-all hover:bg-[#ffd56f] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/35 active:scale-[0.98]">
                <Plus className="h-4 w-4 stroke-[2.8]" /> Thêm loại phòng
              </button>
            </div>

            <div className="relative mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3">
              <MetricCard label="Tổng loại" value={allRoomTypes.length} tone="neutral" />
              <MetricCard label="Hoạt động" value={activeRoomTypes} tone="emerald" />
              <MetricCard label="Số lượng phòng đã áp dụng" value={totalRooms} tone="amber" />
            </div>
          </div>
        </div>

        {(errorMessage || successMessage) && (
          <div className={cn('rounded-3xl border p-4 text-sm font-black', errorMessage ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700')}>
            {errorMessage || successMessage}
          </div>
        )}

        <div className="grid min-w-0 grid-cols-1 gap-4 xl:gap-6">
          <section className="min-w-0 overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/88 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
            <div className="border-b border-[#3d2a18]/10 bg-[#fff7e8]/72 p-4 sm:p-5">
              <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
                <div className="relative min-w-0 flex-1">
                  <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#a65f16]" />
                  <input type="text" value={keyword} onChange={(event) => setKeyword(event.target.value)} placeholder="Tìm tên hoặc mô tả loại phòng..." className={`${inputClass} pl-11 pr-28`} />
                  <button type="button" onClick={clearFilters} className="absolute right-2 top-1/2 inline-flex h-9 -translate-y-1/2 items-center justify-center gap-1.5 rounded-xl px-3 text-xs font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/16 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20">
                    <X className="h-3.5 w-3.5" /> Xóa lọc
                  </button>
                </div>
                <div className="grid w-full gap-3 sm:grid-cols-1 lg:ml-auto lg:w-64">
                  <AdminSelect value={selectedStatus} options={statusOptions} onChange={(nextValue) => setSelectedStatus(String(nextValue))} />
                </div>
              </div>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full min-w-[800px] text-left">
                <thead className="bg-[#24170d] text-[10px] font-black uppercase tracking-[0.18em] text-[#f8e8c8]">
                  <tr>
                    <th className="px-4 py-4">Loại phòng</th>
                    <th className="px-4 py-4 text-center">Số lượng phòng đang áp dụng</th>
                    <th className="px-4 py-4 text-center">Trạng thái</th>
                    <th className="px-4 py-4 w-[190px]"><div className="flex justify-end"><div className="w-[190px] text-center">Thao tác</div></div></th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[#3d2a18]/8">
                  {isLoading && Array.from({ length: 5 }).map((_, index) => (
                    <tr key={index}>
                      <td colSpan={5} className="px-5 py-4"><div className="h-12 animate-pulse rounded-2xl bg-stone-100" /></td>
                    </tr>
                  ))}

                  {!isLoading && roomTypes.map((roomType) => {

                    return (
<tr key={roomType.id} className="group transition hover:bg-[#f3c56b]/12">
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-2.5">
                          <div className="flex h-10 w-10 items-center justify-center rounded-xl border border-[#f3c56b]/35 bg-[#f3c56b]/18 text-[#a65f16] shadow-sm transition group-hover:scale-105">
                            <BedDouble className="h-4 w-4" />
                          </div>
                          <div className="min-w-0">
                            <p className="truncate text-[13px] font-black tracking-tight text-[#24170d]">{roomType.name}</p>
                          </div>
                        </div>
                      </td>
                      <td className="px-4 py-3 text-center text-[13px] font-black text-[#24170d] tabular-nums">{roomType.rooms_count ?? 0}</td>
                      <td className="px-4 py-3 text-center">
                        <span className={cn('inline-flex items-center justify-center whitespace-nowrap rounded-full border px-2.5 py-0.5 text-[11px] font-black shadow-sm', Number(roomType.status) === 1 ? 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]' : 'border-[#3d2a18]/10 bg-[#efe2cf]/65 text-[#6f6254]')}>
                          {roomType.status_label || statusLabels[Number(roomType.status)]}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex items-center justify-end gap-2.5">
                          <button type="button" onClick={() => void viewRoomType(roomType)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#0f766e]/25 hover:bg-[#0f766e]/10 hover:text-[#0f5f59] focus:outline-none focus:ring-4 focus:ring-[#0f766e]/10 active:scale-95" title="Xem chi tiết"><Eye className="h-5 w-5" /></button>
                          <button type="button" onClick={() => editRoomType(roomType)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#3d2a18]/25 hover:bg-[#f3c56b]/15 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#3d2a18]/10 active:scale-95 disabled:cursor-not-allowed disabled:opacity-45" title={'Chỉnh sửa'}><Edit3 className="h-5 w-5" /></button>
                          <button type="button" disabled={statusChangingId === roomType.id} onClick={() => void toggleRoomTypeStatus(roomType)} className={cn('inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition focus:outline-none focus:ring-4 active:scale-95 disabled:cursor-not-allowed disabled:opacity-55', Number(roomType.status) === 1 ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 focus:ring-emerald-100' : 'border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100 focus:ring-rose-100')} title={Number(roomType.status) === 1 ? 'Ngừng hoạt động' : 'Kích hoạt'}><Power className="h-5 w-5" /></button>
                          <button type="button" onClick={() => void removeRoomType(roomType)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600 focus:outline-none focus:ring-4 focus:ring-rose-100 active:scale-95 disabled:cursor-not-allowed disabled:opacity-45" title={'Xóa'}><Trash2 className="h-5 w-5" /></button>
                        </div>
                      </td>
                    </tr>
  )
                  })}

                  {!isLoading && roomTypes.length === 0 && (
                    <tr>
                      <td colSpan={5} className="px-5 py-20 text-center">
                        <div className="mx-auto flex max-w-sm flex-col items-center">
                          <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-[1.75rem] border border-dashed border-[#f3c56b] bg-[#f3c56b]/15 text-[#a65f16]"><BedDouble className="h-9 w-9" /></div>
                          <p className="text-lg font-black tracking-tight text-[#24170d]">Không tìm thấy loại phòng</p>
                          <p className="mt-2 text-sm font-semibold leading-6 text-[#6f6254]">Hãy tạo loại phòng mới hoặc đổi bộ lọc hiện tại.</p>
                        </div>
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>

            <div className="flex flex-col gap-3 border-t border-[#3d2a18]/10 bg-[#fff8eb]/85 px-4 py-4 lg:flex-row lg:items-center lg:justify-between">
              <p className="text-xs font-black text-[#6f6254]">
                Hiển thị <span className="tabular-nums text-[#24170d]">{paginationStart}</span>-<span className="tabular-nums text-[#24170d]">{paginationEnd}</span> / <span className="tabular-nums text-[#24170d]">{paginationMeta?.total ?? allRoomTypes.length}</span> loại phòng
              </p>
              <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div className="w-full sm:w-36">
                  <AdminSelect value={perPage} options={[{ value: 5, label: '5 dòng', tone: 'default' as const }, { value: 10, label: '10 dòng', tone: 'default' as const }, { value: 20, label: '20 dòng', tone: 'default' as const }, { value: 50, label: '50 dòng', tone: 'default' as const }]} onChange={(nextValue) => setPerPage(Number(nextValue))} menuPlacement="top" />
                </div>
                <div className="flex items-center justify-end gap-1.5">
                  <button type="button" disabled={safeCurrentPage <= 1} onClick={() => setCurrentPage(safeCurrentPage - 1)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:cursor-not-allowed disabled:opacity-45" aria-label="Trang trước">
                    <ChevronLeft className="h-4 w-4" />
                  </button>
                  {visiblePages.map((page, index) => {
                    const previousPage = visiblePages[index - 1]
                    const hasGap = previousPage && page - previousPage > 1

                    return (
                      <Fragment key={page}>
                        {hasGap && <span className="px-1 text-xs font-black text-[#8b5e34]/60">...</span>}
                        <button type="button" onClick={() => setCurrentPage(page)} className={cn('inline-flex h-9 min-w-9 items-center justify-center rounded-xl border px-3 text-xs font-black transition', page === safeCurrentPage ? 'border-[#24170d] bg-[#24170d] text-[#fff4df] shadow-sm' : 'border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] hover:bg-[#f3c56b]/15')}>
                          {page}
                        </button>
                      </Fragment>
                    )
                  })}
                  <button type="button" disabled={safeCurrentPage >= totalPages} onClick={() => setCurrentPage(safeCurrentPage + 1)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:cursor-not-allowed disabled:opacity-45" aria-label="Trang sau">
                    <ChevronRight className="h-4 w-4" />
                  </button>
                </div>
              </div>
            </div>
          </section>

          <RoomTypeModal
            isOpen={isFormOpen}
            onClose={handleCloseForm}
            editingRoomTypeId={editingRoomTypeId}
            form={form}
            setForm={setForm}
            onCancel={handleCancelForm}
            onSubmitSuccess={handleSubmitSuccess}
          />
        </div>
      </section>

      {isDetailOpen && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center p-4">
          <button type="button" aria-label="Đóng chi tiết loại phòng" onClick={closeRoomTypeDetail} className="absolute inset-0 bg-stone-950/65 backdrop-blur-sm" />
          <div className="relative z-10 w-full max-w-2xl overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] shadow-2xl shadow-stone-950/30">
            <div className="bg-[#24170d] p-5 text-[#fff4df]">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <p className="text-[10px] font-black uppercase tracking-[0.24em] text-[#f3c56b]">Room type detail</p>
                  <h2 className="mt-2 text-2xl font-black tracking-tight">{detailRoomType?.name || 'Đang tải chi tiết...'}</h2>
                </div>
                <button type="button" onClick={closeRoomTypeDetail} className="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/10 text-white transition hover:bg-white/20">
                  <X className="h-5 w-5" />
                </button>
              </div>
            </div>

            <div className="space-y-4 p-5">
              {isDetailLoading && <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-black text-amber-800">Đang tải chi tiết loại phòng...</div>}
              {detailErrorMessage && <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-black text-rose-700">{detailErrorMessage}</div>}

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-1">
                <DetailTile label="Số phòng" value={detailRoomType?.rooms_count ?? 0} />
              </div>

              <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
                <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">Mô tả</p>
                <p className="mt-2 text-sm font-semibold leading-6 text-[#3d2a18]">{detailRoomType?.description || 'Chưa có mô tả.'}</p>
              </section>

              <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
                <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">Phòng đang dùng</p>
                <div className="mt-3 max-h-52 space-y-2 overflow-y-auto pr-1">
                  {detailRoomType?.rooms?.map((room) => (
                    <div key={room.id} className="flex items-center justify-between gap-3 rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-3 py-2 text-sm font-bold text-[#24170d]">
                      <span>{room.room_number}</span>
                      <span className="text-xs text-[#8b5e34]">{room.building_name || 'Chưa có tòa nhà'}</span>
                    </div>
                  ))}
                  {(!detailRoomType?.rooms || detailRoomType.rooms.length === 0) && <p className="text-sm font-semibold text-[#6f6254]">Chưa có phòng nào dùng loại phòng này.</p>}
                </div>
              </section>
            </div>
          </div>
        </div>
      )}
    </>
    </>
  )
}

function MetricCard({ label, value, tone }: { label: string; value: number; tone: 'neutral' | 'emerald' | 'amber' }) {
  const toneClassNames = {
    neutral: 'border-[#f8e8c8]/12 bg-[#f8e8c8]/10 text-[#fff4df]',
    emerald: 'border-[#0f766e]/35 bg-[#0f766e]/16 text-[#c8fff4]',
    amber: 'border-[#f3c56b]/35 bg-[#f3c56b]/18 text-[#fff4df]',
  }[tone]

  return (
    <div className={cn('rounded-2xl border px-3 py-2.5 backdrop-blur', toneClassNames)}>
      <p className="text-[10px] font-black uppercase tracking-[0.18em] opacity-60">{label}</p>
      <p className="mt-0.5 text-2xl font-black tracking-tight tabular-nums">{value}</p>
    </div>
  )
}

function DetailTile({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="rounded-2xl border border-[#3d2a18]/10 bg-white/70 p-4">
      <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">{label}</p>
      <p className="mt-2 text-lg font-black text-[#24170d] tabular-nums">{value}</p>
    </div>
  )
}

