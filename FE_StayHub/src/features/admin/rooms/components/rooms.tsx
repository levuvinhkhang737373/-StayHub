import { useEffect, useState } from 'react'
import { cn } from '../../../../shared/lib/utils/cn'
import type { AdminRoomResource } from '../types/rooms.model'
import { deleteAdminRoom, fetchAdminRoomDetail, fetchAdminRooms } from '../services/rooms.service'
import { Eye, Trash2 } from 'lucide-react'
import { Link } from 'react-router-dom'

const statusLabels: Record<number, string> = {
  1: 'Hoạt động',
  2: 'Ngừng hoạt động',
}

export function Rooms() {
  const [rooms, setRooms] = useState<AdminRoomResource[]>([])
  const [room,setRoom]=useState<AdminRoomResource|null>(null);
  const [isLoading, setIsLoading] = useState(true)
  const [currentPage, setCurrentPage] = useState(1)
  const [isDetailOpen,setIsDetailOpen]=useState(false);
  const itemsPerPage = 10
  const totalPages = Math.ceil(rooms.length / itemsPerPage)
  const paginatedRooms = rooms.slice(
    (currentPage - 1) * itemsPerPage,
    currentPage * itemsPerPage,
  )
  const loadRooms = async () => {
    try {
      const res = await fetchAdminRooms()
      setRooms(res.result)
    } catch (error) {
      console.log(error)
    } finally {
      setIsLoading(false)
    }
  }
   const closeRoomDetail=()=>{
     setIsDetailOpen(false);
   }
   const viewRoom=async(detail:any)=>{
    setRoom(null);
    setIsDetailOpen(true);
    try {
      const res=await fetchAdminRoomDetail(detail);
    setRoom(res.result);
    } catch (error) {
      console.log(error);
    }
    
   }
   const deleteRoom=(id:any)=>{
    try {
       if(confirm("Bạn có muốn xóa phòng này không?"))
      {
          deleteAdminRoom(id);
          alert("Xóa thành công");
      }
    } catch (error) {
      console.log(error);
    }
     
   }
  useEffect(() => {
    void loadRooms()
  }, [])

  return (
    <>
    <Link  to='/admin/room' className='inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-white font-medium shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2'>Thêm Phòng</Link>
     
     <div className="overflow-x-auto">
      <table className="min-w-190 w-full text-left">
        <thead className="bg-[#24170d] text-[11px] font-black uppercase tracking-[0.18em] text-[#f8e8c8]">
          <tr>
            <th className="px-5 py-4">Số Phòng</th>
            <th className="px-5 py-4">Tòa nhà</th>
            <th className="px-5 py-4 text-center">Loại Phòng</th>
            <th className="px-5 py-4 text-center">Tầng</th>
            <th className="px-5 py-4">Giá</th>
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
            paginatedRooms.map((room) => (
              <tr key={room.id} className="group transition hover:bg-[#f3c56b]/12">
                <td className="px-4 py-3">
                  <p className="truncate text-[13px] font-black tracking-tight text-[#24170d]">
                    {room.room_number}
                  </p>
                </td>

                <td className="px-4 py-3 text-[13px] font-bold text-[#6f6254]">
                  {room.building.name}
                </td>

                <td className="px-4 py-3 text-center text-[13px] font-bold text-[#6f6254]">
                  {room.room_type.name}
                </td>

                <td className="px-4 py-3 text-center text-[13px] font-bold text-[#6f6254]">
                  {room.floor}
                </td>

                <td className="px-4 py-3 text-[13px] font-bold text-[#6f6254]">
                  {Number(room.base_price).toLocaleString('vi-VN', {
                    style: 'currency',
                    currency: 'VND',
                  })}
                </td>

                <td className="px-4 py-3 text-center text-[13px] font-bold text-[#6f6254]">
                  {room.current_occupants}
                </td>

                <td className="px-4 py-3">
                  <span
                    className={cn(
                      'inline-flex items-center justify-center whitespace-nowrap rounded-full border px-2.5 py-0.5 text-[11px] font-black shadow-sm',
                      Number(room.status) === 1
                        ? 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]'
                        : 'border-[#3d2a18]/10 bg-[#efe2cf]/65 text-[#6f6254]',
                    )}
                  >
                    {statusLabels[Number(room.status)]}
                  </span>
                </td>

                <td className="px-4 py-3">
                  <div className="flex items-center justify-end gap-2.5">
                     <button type="button" onClick={() => void viewRoom(room.id)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#0f766e]/25 hover:bg-[#0f766e]/10 hover:text-[#0f5f59] focus:outline-none focus:ring-4 focus:ring-[#0f766e]/10 active:scale-95" title="Xem chi tiết"><Eye className="h-5 w-5" /></button>
                     <button type="button" onClick={() => void deleteRoom(room.id)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#0f766e]/25 hover:bg-[#0f766e]/10 hover:text-[#0f5f59] focus:outline-none focus:ring-4 focus:ring-[#0f766e]/10 active:scale-95" title="Xem chi tiết"><Trash2 className="h-5 w-5" /></button>

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
      {/**Modal show room detail */}
      {isDetailOpen && (
  <div className="fixed inset-0 z-[60] flex items-center justify-center p-4">
    {/* backdrop */}
    <button type="button" aria-label="Đóng chi tiết loại phòng" onClick={closeRoomDetail} className="absolute inset-0 bg-stone-950/65 backdrop-blur-sm" />
    
    {/* modal box */}
    <div className="relative z-10 w-full max-w-2xl overflow-hidden rounded-[2rem] bg-[#fffaf1] shadow-2xl border border-[#3d2a18]/10">
      
      {/* header */}
      <div className="bg-[#24170d] px-6 py-5 text-[#fff4df] text-base font-black tracking-tight">
        Thông tin chi tiết của phòng {room?.room_number}
      </div>

      {/* body */}
      <div className="divide-y divide-[#3d2a18]/8 px-6 py-2">
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
              : 'border-[#3d2a18]/10 bg-[#efe2cf]/65 text-[#6f6254]',
          )}>
            {statusLabels[Number(room?.status)]}
          </span>
        </div>
        <div className="flex items-start justify-between gap-4 py-3">
          <span className="text-[11px] font-black uppercase tracking-widest text-[#8b5e34]">Mô tả</span>
          <span className="text-right text-[13px] font-bold leading-relaxed text-[#24170d]">{room?.description ?? '—'}</span>
        </div>
      </div>

    </div>
  </div>
)}
    </div>
    </>
   
   
  )

}