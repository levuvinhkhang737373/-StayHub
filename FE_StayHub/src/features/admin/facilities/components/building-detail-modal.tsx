import { useEffect } from 'react'
import type { FC } from 'react'
import { AnimatePresence, motion } from 'motion/react'
import { Clock, LayoutDashboard, MapPin, Phone, User, X } from 'lucide-react'
import { cn } from '../../../../shared/lib/utils/cn'
import type { Building } from '../types/building.model'

interface BuildingDetailModalProps {
  isOpen: boolean
  onClose: () => void
  building: Building | null
  isLoading?: boolean
  errorMessage?: string | null
  onEdit: (building: Building) => void
}

const stayHubImage = '/images/stayhub.png'

export const BuildingDetailModal: FC<BuildingDetailModalProps> = ({ isOpen, onClose, building, isLoading = false, errorMessage = null, onEdit }) => {
  useEffect(() => {
    if (!isOpen) return

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') onClose()
    }

    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [isOpen, onClose])

  if (!isOpen) return null

  const images = building?.image_urls || []
  const genderPolicyLabel = building?.gender_policy === 2 ? 'Nam' : building?.gender_policy === 3 ? 'Nữ' : 'Hỗn hợp'

  return (
    <AnimatePresence>
      {isOpen && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center p-3 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="building-detail-title">
          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} onClick={onClose} className="absolute inset-0 bg-stone-950/70 backdrop-blur-md" />
          <motion.div
            initial={{ y: 40, opacity: 0, scale: 0.98 }}
            animate={{ y: 0, opacity: 1, scale: 1 }}
            exit={{ y: 40, opacity: 0, scale: 0.98 }}
            className="relative z-10 flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-[2rem] border border-white/10 bg-[#f5f0e7] shadow-2xl shadow-black/40 md:flex-row"
          >
            <div className="relative min-h-[320px] w-full overflow-hidden bg-[#221f1a] md:w-[42%]">
              <div className="absolute inset-0">
                <img
                  src={building?.primary_image?.image_url || building?.image_urls?.[0] || stayHubImage}
                  alt={building?.name || 'Tòa nhà'}
                  onError={(event) => { event.currentTarget.src = stayHubImage }}
                  className="h-full w-full object-cover opacity-55 grayscale-[18%]"
                />
                <div className="absolute inset-0 bg-[linear-gradient(to_right,rgba(255,255,255,0.08)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.08)_1px,transparent_1px)] bg-[size:34px_34px] mix-blend-overlay" />
                <div className="absolute inset-0 bg-gradient-to-t from-[#181511] via-[#181511]/55 to-transparent" />
              </div>

              <div className="relative flex h-full min-h-[320px] flex-col justify-between p-6 sm:p-8">
                <div className="flex items-center justify-between">
                  <span className="rounded-full border border-amber-200/20 bg-amber-200/15 px-3 py-1 text-[10px] font-black uppercase tracking-[0.24em] text-amber-100 backdrop-blur-md">
                    Building dossier
                  </span>
                  <button type="button" onClick={onClose} className="flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-white/10 text-white backdrop-blur-md transition-all hover:bg-white/20 focus:outline-none focus:ring-4 focus:ring-amber-200/20" aria-label="Đóng chi tiết tòa nhà">
                    <X className="h-5 w-5" />
                  </button>
                </div>

                <div className="space-y-6">
                  <div>
                    <span className="rounded-full border border-white/10 bg-white/15 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-white backdrop-blur-md">
                      Tòa nhà
                    </span>
                    <h2 id="building-detail-title" className="mt-4 text-3xl font-black leading-tight tracking-[-0.04em] text-white sm:text-4xl">{building?.name || 'Đang tải chi tiết...'}</h2>
                    <p className="mt-3 flex items-start gap-2 text-sm font-semibold leading-6 text-white/65">
                      <MapPin className="mt-1 h-4 w-4 shrink-0 text-amber-200" />
                      {building?.address || 'Đang tải địa chỉ...'}
                    </p>
                  </div>

                  <div className="grid grid-cols-2 gap-3">
                    <DetailStat label="Tổng số tầng" value={building?.total_floors || 0} />
                    <DetailStat label="Số ảnh" value={building?.images_count || images.length} />
                    <div className="rounded-3xl border border-white/10 bg-white/10 p-4 backdrop-blur-md">
                      <p className="text-[9px] font-black uppercase tracking-[0.22em] text-white/45">Trạng thái</p>
                      <div className="mt-3 flex items-center gap-2">
                        <div className={cn('h-2.5 w-2.5 animate-pulse rounded-full', building?.status === 'active' ? 'bg-emerald-300' : building?.status === 'inactive' ? 'bg-stone-300' : 'bg-amber-300')} />
                        <span className="text-xs font-black text-white">
                          {building?.status === 'active' ? 'Hoạt động' : building?.status === 'inactive' ? 'Ngừng hoạt động' : 'Bảo trì'}
                        </span>
                      </div>
                    </div>
                    <div className="rounded-3xl border border-white/10 bg-white/10 p-4 backdrop-blur-md">
                      <p className="text-[9px] font-black uppercase tracking-[0.22em] text-white/45">Giới tính</p>
                      <p className="mt-2 text-lg font-black text-white">{genderPolicyLabel}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div className="flex flex-1 flex-col overflow-y-auto bg-[#f5f0e7]">
              <div className="space-y-5 p-5 sm:p-7">
                {isLoading && <div className="rounded-3xl border border-amber-200 bg-amber-50 p-4 text-sm font-black text-amber-800">Đang tải chi tiết tòa nhà từ API...</div>}
                {errorMessage && <div className="rounded-3xl border border-rose-200 bg-rose-50 p-4 text-sm font-black text-rose-700">{errorMessage}</div>}

                <section className="rounded-[1.75rem] border border-stone-900/10 bg-white/80 p-5 shadow-sm">
                  <SectionTitle label="Mô tả tòa nhà" />
                  <p className="mt-3 text-sm font-semibold leading-7 text-stone-600">{building?.description || 'Chưa có mô tả chi tiết cho tòa nhà này.'}</p>
                </section>

                <section className="rounded-[1.75rem] border border-stone-900/10 bg-white/80 p-5 shadow-sm">
                  <SectionTitle label="Hình ảnh tòa nhà" />
                  <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    {images.map((imageUrl) => (
                      <div key={imageUrl} className="overflow-hidden rounded-2xl border border-stone-200 bg-stone-50">
                        <img src={imageUrl || stayHubImage} alt={building?.name || 'Ảnh tòa nhà'} onError={(event) => { event.currentTarget.src = stayHubImage }} className="h-28 w-full object-cover" />
                      </div>
                    ))}
                    {images.length === 0 && (
                      <div className="overflow-hidden rounded-2xl border border-stone-200 bg-stone-50">
                        <img src={stayHubImage} alt={building?.name || 'Ảnh mặc định StayHub'} className="h-28 w-full object-cover" />
                      </div>
                    )}
                  </div>
                </section>

                <section className="rounded-[1.75rem] border border-stone-900/10 bg-white/80 p-5 shadow-sm">
                  <SectionTitle label="Dữ liệu liên quan" />
                  <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <InfoStat label="Phòng" value={building?.rooms_count || 0} />
                    <InfoStat label="Loại phòng" value={building?.room_types_count || 0} />
                    <InfoStat label="Mẫu tài sản" value={building?.asset_templates_count || 0} />
                    <InfoStat label="Bảng giá" value={building?.service_prices_count || 0} />
                    <InfoStat label="Cài đặt" value={building?.settings_count || 0} />
                    <InfoStat label="Thông báo" value={building?.notifications_count || 0} />
                    <InfoStat label="Chi phí" value={building?.expenses_count || 0} />
                  </div>
                </section>

                <section className="rounded-[1.75rem] border border-stone-900/10 bg-white/80 p-5 shadow-sm">
                  <SectionTitle label="Cấu hình nền" />
                  <div className="mt-4 grid gap-3 sm:grid-cols-2">
                    <PreviewGroup label="Loại phòng" items={building?.room_types?.map((item) => item.name) || []} />
                    <PreviewGroup label="Mẫu tài sản" items={building?.asset_templates?.map((item) => item.name) || []} />
                    <PreviewGroup label="Bảng giá" items={building?.service_prices?.map((item) => `${item.service_name || item.service?.name || 'Dịch vụ'} · ${formatMoneyText(item.price)}đ`) || []} />
                    <PreviewGroup label="Cài đặt" items={building?.settings?.map((item) => `${item.setting_label}: ${item.setting_value || '—'}`) || []} />
                  </div>
                </section>

                <section className="overflow-hidden rounded-[1.75rem] border border-stone-900/10 bg-[#221f1a] p-5 text-white shadow-xl shadow-stone-950/10">
                  <SectionTitle label="Thông tin quản lý" inverse />
                  <div className="mt-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-4">
                      <div className="flex h-13 w-13 items-center justify-center rounded-2xl border border-amber-200/20 bg-amber-200/15 text-amber-100">
                        <User className="h-6 w-6" />
                      </div>
                      <div>
                        <p className="text-[10px] font-black uppercase tracking-[0.22em] text-white/40">Người chịu trách nhiệm</p>
                        <p className="mt-1 text-base font-black text-white">{building?.manager_name || 'Chưa phân công'}</p>
                      </div>
                    </div>
                    <div className="flex gap-2">
                      <a href={`tel:${building?.manager_phone || ''}`} className="flex h-11 w-11 items-center justify-center rounded-full border border-white/10 bg-white/10 text-white transition-all hover:bg-white hover:text-stone-950 focus:outline-none focus:ring-4 focus:ring-amber-200/20">
                        <Phone className="h-4 w-4" />
                      </a>
                      <button type="button" className="flex h-11 items-center gap-2 rounded-full bg-amber-300 px-5 text-[11px] font-black uppercase tracking-tight text-stone-950 transition-all hover:bg-amber-200 focus:outline-none focus:ring-4 focus:ring-amber-200/30">
                        <LayoutDashboard className="h-3.5 w-3.5" />
                        <span>Thống kê</span>
                      </button>
                    </div>
                  </div>
                </section>

                <div className="flex flex-col gap-3 border-t border-stone-200 pt-4 sm:flex-row sm:items-center sm:justify-between">
                  <div className="flex items-center gap-2 text-stone-400">
                    <Clock className="h-3.5 w-3.5" />
                    <span className="text-[10px] font-black uppercase tracking-widest">Khởi tạo: {building?.created_at || 'Chưa cập nhật'}</span>
                  </div>
                  <button type="button" onClick={() => building && onEdit(building)} className="self-start text-xs font-black text-stone-950 underline underline-offset-4 transition-colors hover:text-amber-700 sm:self-auto">
                    Chỉnh sửa tòa nhà
                  </button>
                </div>
              </div>
            </div>
          </motion.div>
        </div>
      )}
    </AnimatePresence>
  )
}

function DetailStat({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-3xl border border-white/10 bg-white/10 p-4 backdrop-blur-md">
      <p className="text-[9px] font-black uppercase tracking-[0.22em] text-white/45">{label}</p>
      <p className="mt-2 text-2xl font-black text-white tabular-nums">{value}</p>
    </div>
  )
}

function SectionTitle({ label, inverse = false }: { label: string; inverse?: boolean }) {
  return <h3 className={cn('text-[10px] font-black uppercase tracking-[0.24em]', inverse ? 'text-amber-100/70' : 'text-stone-400')}>{label}</h3>
}

function InfoStat({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-2xl border border-stone-100 bg-stone-50/70 p-4 text-center">
      <p className="text-xl font-black text-stone-950 tabular-nums">{value}</p>
      <p className="mt-1 text-[10px] font-black uppercase tracking-widest text-stone-400">{label}</p>
    </div>
  )
}

function PreviewGroup({ label, items }: { label: string; items: string[] }) {
  const previewItems = items.slice(0, 3)

  return (
    <div className="rounded-2xl border border-stone-100 bg-stone-50/70 p-4">
      <p className="text-[10px] font-black uppercase tracking-widest text-stone-400">{label}</p>
      <div className="mt-3 space-y-2">
        {previewItems.map((item) => (
          <p key={item} className="truncate text-xs font-black text-stone-700">{item}</p>
        ))}
        {previewItems.length === 0 && <p className="text-xs font-semibold text-stone-400">Chưa có dữ liệu.</p>}
        {items.length > 3 && <p className="text-[10px] font-black text-amber-700">+{items.length - 3} mục khác</p>}
      </div>
    </div>
  )
}

function formatMoneyText(value: string | null | undefined) {
  const [integerPart, decimalPart] = String(value || '0').split('.')
  const formattedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.')

  if (!decimalPart || /^0+$/.test(decimalPart)) return formattedInteger

  return `${formattedInteger},${decimalPart}`
}
