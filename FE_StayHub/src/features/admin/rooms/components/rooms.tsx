import { useEffect, useState } from 'react'
import { cn } from '../../../../shared/lib/utils/cn'
import type { AdminRoomResource } from '../types/rooms.model'
// Đã import thêm updateAdminRoomStatus vào đây
import { deleteAdminRoom, fetchAdminRoomDetail, fetchAdminRooms, updateAdminRoomStatus } from '../services/rooms.service'
import { Eye, Trash2, Pencil, PackageOpen, RefreshCw } from 'lucide-react'
import { Link } from 'react-router-dom'
import { useAdminSession } from '../../auth/hooks/admin-session-store'

const statusLabels: Record<number, string> = {
  1: 'Hoạt động',
  2: 'Ngừng hoạt động',
  3: 'Bảo Trì'
}

export function Rooms() {
  const [rooms, setRooms] = useState<AdminRoomResource[]>([])
  const [room, setRoom] = useState<AdminRoomResource | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [currentPage, setCurrentPage] = useState(1)
  const [isDetailOpen, setIsDetailOpen] = useState(false)
  const { session } = useAdminSession()
  
  const itemsPerPage = 10
  const totalPages = Math.ceil(rooms.length / itemsPerPage)
  const paginatedRooms = rooms.slice(
    (currentPage - 1) * itemsPerPage,
    currentPage * itemsPerPage,
  )

  // CHUYỂN ĐỔI ROLE SANG NUMBER ĐỂ SO SÁNH CHÍNH XÁC
  const SUPERADMIN_ROLE = Number(import.meta.env.VITE_SUPERADMIN_ROLE)
  const isSuperAdmin = session?.admin?.role === SUPERADMIN_ROLE

  const loadRooms = async () => {
    try {
      setIsLoading(true)
      const res = await fetchAdminRooms()
      setRooms(res.result)
    } catch (error) {
      console.log(error)
    } finally {
      setIsLoading(false)
    }
  }

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

  // HÀM XỬ LÝ ĐỔI TRẠNG THÁI PHÒNG (HOẠT ĐỘNG <-> BẢO TRÌ)
  const toggleRoomStatus = async (id: number, currentStatus: number) => {
  // Chỉ dùng currentStatus để hiển thị câu hỏi Confirm cho thân thiện với user
  const nextStatusText = Number(currentStatus) === 1 ? 'Bảo Trì' : 'Hoạt động'
  
  if (!confirm(`Bạn có chắc chắn muốn đổi trạng thái phòng này sang "${nextStatusText}" không?`)) {
    return
  }

  try {
    // Gọi API gọn gàng: Chỉ truyền mỗi ID
    const res = await updateAdminRoomStatus(id)

    if (res && res.status !== false) {
      alert("Cập nhật trạng thái phòng thành công")
      await loadRooms() // Tải lại danh sách ngoài bảng

      // Nếu đang mở xem chi tiết của chính phòng này, tải lại để cập nhật Modal
      if (isDetailOpen && room?.id === id) {
        const detailRes = await fetchAdminRoomDetail(id)
        setRoom(detailRes.result)
      }
    } else {
      alert(res?.message || "Cập nhật trạng thái thất bại.")
    }
  } catch (error: any) {
    console.error("Lỗi cập nhật trạng thái:", error)
    const errorMessage = error?.response?.data?.message || error?.message || "Đã xảy ra lỗi hệ thống."
    alert("Thất bại: " + errorMessage)
  }
}

  const deleteRoom = async (id: any) => {
    try {
      if (confirm("Bạn có muốn xóa phòng này không?")) {
        const res = await deleteAdminRoom(id);
        
        if (res && res.status !== false) {
          alert("Xóa thành công");
          await loadRooms();
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

  useEffect(() => {
    void loadRooms()
  }, [])

  return (
    <>
      {isSuperAdmin && (
        <Link to='/admin/room' className='inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-white font-medium shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2'>
          Thêm Phòng
        </Link>
      )}
      
      <div className="overflow-x-auto mt-4">
        <table className="min-w-190 w-full text-left">
          <thead className="bg-[#24170d] text-[11px] font-black uppercase tracking-[0.18em] text-[#f8e8c8]">
            <tr>
              <th className="px-5 py-4">Số Phòng</th>
              <th className="px-5 py-4">Tòa nhà</th>
              <th className="px-5 py-4 text-center">Loại Phòng</th>
              <th className="px-5 py-4 text-center">Tầng</th>
              <th className="px-5 py-4 text-center">Số người đang ở</th>
              <th className="px-5 py-4">Trạng thái</th>
              <th className="px-5 py-4">
                <span className="flex justify-end">
                  <span className="w-47.5 text-center">Thao tác</span>
                </span>
              </th>
            </tr>
          </thead>

          <tbody className="divide-y divide-[#3d2a18]/8">
            {isLoading &&
              Array.from({ length: 5 }).map((_, index) => (
                <tr key={index}>
                  <td colSpan={8} className="px-5 py-4">
                    <div className="h-12 animate-pulse rounded-2xl bg-stone-100" />
                  </td>
                </tr>
              ))}

            {!isLoading &&
              paginatedRooms.map((roomItem) => (
                <tr key={roomItem.id} className="group transition hover:bg-[#f3c56b]/12">
                  <td className="px-4 py-3">
                    <p className="truncate text-[13px] font-black tracking-tight text-[#24170d]">
                      {roomItem.room_number}
                    </p>
                  </td>

                  <td className="px-4 py-3 text-[13px] font-bold text-[#6f6254]">
                    {roomItem.building.name}
                  </td>

                  <td className="px-4 py-3 text-center text-[13px] font-bold text-[#6f6254]">
                    {roomItem.room_type.name}
                  </td>

                  <td className="px-4 py-3 text-center text-[13px] font-bold text-[#6f6254]">
                    {roomItem.floor}
                  </td>

                  <td className="px-4 py-3 text-center text-[13px] font-bold text-[#6f6254]">
                    {roomItem.current_occupants}
                  </td>

                  <td className="px-4 py-3">
                    <span
                      className={cn(
                        'inline-flex items-center justify-center whitespace-nowrap rounded-full border px-2.5 py-0.5 text-[11px] font-black shadow-sm',
                        Number(roomItem.status) === 1
                          ? 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]'
                          : Number(roomItem.status) === 3
                          ? 'border-amber-500/20 bg-amber-50 text-amber-700' // CSS cho trạng thái bảo trì
                          : 'border-[#3d2a18]/10 bg-[#efe2cf]/65 text-[#6f6254]',
                      )}
                    >
                      {statusLabels[Number(roomItem.status)] || 'Không xác định'}
                    </span>
                  </td>

                  <td className="px-4 py-3">
                    <div className="flex items-center justify-end gap-2.5">
                       <button type="button" onClick={() => void viewRoom(roomItem.id)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#0f766e]/25 hover:bg-[#0f766e]/10 hover:text-[#0f5f59] focus:outline-none focus:ring-4 focus:ring-[#0f766e]/10 active:scale-95" title="Xem chi tiết"><Eye className="h-5 w-5" /></button>
                       
                       {/* NÚT ĐỔI TRẠNG THÁI NHANH (Chỉ SuperAdmin mới thấy) */}
                      
                         <button 
                           type="button" 
                           onClick={() => void toggleRoomStatus(roomItem.id, Number(roomItem.status))} 
                           className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-blue-500/25 hover:bg-blue-50 hover:text-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-500/10 active:scale-95" 
                           title="Đổi trạng thái phòng"
                         >
                           <RefreshCw className="h-4 w-4" />
                         </button>
                       

                       {isSuperAdmin && (
                         <Link to={`/admin/rooms/update/${roomItem.id}`} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-amber-500/30 hover:bg-amber-50 hover:text-amber-700 focus:outline-none focus:ring-4 focus:ring-amber-500/10 active:scale-95" title="Sửa thông tin phòng">
                           <Pencil className="h-4.5 w-4.5" />
                         </Link>
                       )}
                    
                       {isSuperAdmin && (
                         <button type="button" onClick={() => void deleteRoom(roomItem.id)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-red-500/25 hover:bg-red-50 hover:text-red-600 focus:outline-none focus:ring-4 focus:ring-red-500/10 active:scale-95" title="Xóa phòng"><Trash2 className="h-5 w-5" /></button>
                       )}
                    </div>
                  </td>
                </tr>
              ))}

            {!isLoading && rooms.length === 0 && (
              <tr>
                <td colSpan={8} className="px-5 py-20 text-center">
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
          <div className="flex items-center justify-between border-t border-[#3d2a18]/10 px-5 py-4">
            <p className="text-xs font-bold text-[#8b5e34]">
              Hiển thị {(currentPage - 1) * itemsPerPage + 1}–
              {Math.min(currentPage * itemsPerPage, rooms.length)} / {rooms.length} phòng
            </p>

            <div className="flex items-center gap-1.5">
              <button
                type="button"
                disabled={currentPage === 1}
                onClick={() => setCurrentPage((p) => p - 1)}
                className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-sm font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:cursor-not-allowed disabled:opacity-40"
              >
                ‹
              </button>

              {Array.from({ length: totalPages }).map((_, i) => (
                <button
                  key={i}
                  type="button"
                  onClick={() => setCurrentPage(i + 1)}
                  className={cn(
                    'inline-flex h-9 w-9 items-center justify-center rounded-xl border text-sm font-black transition',
                    currentPage === i + 1
                      ? 'border-[#f3c56b] bg-[#f3c56b] text-[#24170d]'
                      : 'border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] hover:bg-[#f3c56b]/15',
                  )}
                >
                  {i + 1}
                </button>
              ))}

              <button
                type="button"
                disabled={currentPage === totalPages}
                onClick={() => setCurrentPage((p) => p + 1)}
                className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-sm font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:cursor-not-allowed disabled:opacity-40"
              >
                ›
              </button>
            </div>
          </div>
        )}

        {/** MODAL SHOW DETAIL ROOM WITH ASSETS */}
        {isDetailOpen && (
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
                  <span className="text-[13px] font-bold text-[#24170d]">{Number(room?.base_price).toLocaleString('vi-VN', { style: 'currency', currency: 'VND' })}</span>
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
                      : Number(room?.status) === 3
                      ? 'border-amber-500/20 bg-amber-50 text-amber-700'
                      : 'border-[#3d2a18]/10 bg-[#efe2cf]/65 text-[#6f6254]',
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
                          const apiBaseUrl = import.meta.env.VITE_BASE_URL || '';
                          const fullImageUrl = `${apiBaseUrl}${item.image_path}`;
                          
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
          </div>
        )}
      </div>
    </>
  )
}