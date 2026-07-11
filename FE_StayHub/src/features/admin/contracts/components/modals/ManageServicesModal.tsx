import React, { useState } from 'react'
import { ArrowRight, X, Plus, Check } from 'lucide-react'
import { cn } from '../../../../../shared/lib/utils/cn'
import { formatMoneyInput } from '../../../../../shared/lib/utils/format'
import { AdminSelect } from '../../../shared/components/AdminSelect'
import { createAdminService } from '../../../services/services/services.service'
import type { ContractServiceFormRow } from '../../types/contract-api.model'

interface ManageServicesModalProps {
  isOpen: boolean
  onClose: () => void
  buildingServices: any[]
  setBuildingServices: React.Dispatch<React.SetStateAction<any[]>>
  selectedServices: ContractServiceFormRow[]
  onUpdateServices: (services: ContractServiceFormRow[]) => void
}

const isFixedService = (name: string, slug?: string) => {
  const s = (slug || '').toLowerCase()
  const n = (name || '').toLowerCase()
  return (
    ['electric', 'water', 'electricity', 'dien-sinh-hoat', 'nuoc-sinh-hoat', 'dien', 'nuoc'].includes(s) ||
    n.includes('điện') ||
    n.includes('nước')
  )
}

const moneyNumber = (value: string | number | null | undefined) => {
  if (value === null || value === undefined) return 0
  if (typeof value === 'number') return value

  const valStr = String(value).trim()
  // Handle decimal prices from API (e.g. "1000.00")
  if (/^\d+\.\d{1,2}$/.test(valStr)) {
    return Math.max(Math.round(Number(valStr)), 0)
  }

  const parsed = Number(valStr.replace(/\./g, '').replace(/,/g, '').trim() || '0')
  return Number.isFinite(parsed) ? Math.max(parsed, 0) : 0
}

const displayPrice = (value: string | number | null | undefined) => formatMoneyInput(String(Math.round(moneyNumber(value)))) || '0'

export function ManageServicesModal({
  isOpen,
  onClose,
  buildingServices,
  setBuildingServices,
  selectedServices,
  onUpdateServices,
}: ManageServicesModalProps) {
  const [showQuickCreate, setShowQuickCreate] = useState(false)
  const [newServiceName, setNewServiceName] = useState('')
  const [newChargeMethod, setNewChargeMethod] = useState('2') // Cố định
  const [newUnitName, setNewUnitName] = useState('tháng')
  const [newPrice, setNewPrice] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [quickCreateError, setQuickCreateError] = useState<string | null>(null)

  const isChecked = (serviceId: string) => {
    return selectedServices.some((s) => String(s.service_id) === String(serviceId))
  }

  const handleToggleService = (service: any) => {
    const serviceIdStr = String(service.id)
    if (isFixedService(service.name, service.slug)) return // Điện nước cố định, không được tắt

    if (isChecked(serviceIdStr)) {
      // Remove
      const updated = selectedServices.filter((s) => String(s.service_id) !== serviceIdStr)
      onUpdateServices(updated)
    } else {
      // Add
      const newRow: ContractServiceFormRow = {
        service_id: serviceIdStr,
        room_service_id: service.room_service_id ? String(service.room_service_id) : '',
        name: service.name || '',
        slug: service.slug || '',
        charge_method_label: service.charge_method_label || '',
        unit_name: service.unit_name || '',
        default_price: displayPrice(service.price || service.base_price || service.effective_price || 0),
        price: displayPrice(service.price || service.base_price || service.effective_price || 0),
      }
      onUpdateServices([...selectedServices, newRow])
    }
  }

  const handleQuickCreate = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!newServiceName.trim()) {
      setQuickCreateError('Vui lòng nhập tên dịch vụ.')
      return
    }
    if (!newUnitName.trim()) {
      setQuickCreateError('Vui lòng nhập đơn vị tính.')
      return
    }
    const parsedPrice = parseFloat(newPrice.replace(/\./g, '')) || 0
    if (parsedPrice < 0) {
      setQuickCreateError('Đơn giá không được âm.')
      return
    }

    setIsSubmitting(true)
    setQuickCreateError(null)

    try {
      const response = await createAdminService({
        name: newServiceName.trim(),
        charge_method: Number(newChargeMethod),
        unit_name: newUnitName.trim(),
        is_required: false,
        is_active: true,
      })

      const newService = response.result
      if (newService) {
        const formattedPrice = String(Math.round(parsedPrice))
        const newServiceRow = {
          id: newService.id,
          name: newService.name,
          slug: newService.slug || '',
          charge_method: newService.charge_method,
          charge_method_label: newService.charge_method === 1 ? 'Theo chỉ số' : (newService.charge_method === 2 ? 'Cố định' : 'Miễn phí'),
          unit_name: newService.unit_name,
          price: formattedPrice,
        }

        // 1. Thêm vào danh sách dịch vụ của tòa nhà
        setBuildingServices((prev) => [...prev, newServiceRow])

        // 2. Tự động tick chọn dịch vụ này vào hợp đồng
        const newFormRow: ContractServiceFormRow = {
          service_id: String(newService.id),
          room_service_id: '',
          name: newService.name || '',
          slug: newService.slug || '',
          charge_method_label: newServiceRow.charge_method_label,
          unit_name: newService.unit_name || '',
          default_price: displayPrice(formattedPrice),
          price: displayPrice(formattedPrice),
        }
        onUpdateServices([...selectedServices, newFormRow])

        // Reset form nhanh
        setNewServiceName('')
        setNewUnitName('tháng')
        setNewPrice('')
        setShowQuickCreate(false)
      }
    } catch (error: any) {
      setQuickCreateError(error?.message || 'Có lỗi xảy ra khi tạo dịch vụ.')
    } finally {
      setIsSubmitting(false)
    }
  }

  if (!isOpen) return null

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#24170d]/60 backdrop-blur-sm animate-in fade-in duration-200">
      <div className="relative w-full max-w-xl overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] shadow-2xl animate-in zoom-in-95 duration-200 flex flex-col max-h-[85vh]">
        
        {/* Header */}
        <div className="flex items-center justify-between border-b border-[#3d2a18]/10 bg-[#fff8eb] p-5">
          <div>
            <h3 className="text-lg font-black text-[#24170d]">Dịch vụ phòng & giá deal</h3>
            <p className="text-xs text-[#8b5e34]/70 mt-0.5">Giá mặc định lấy từ room_service_prices, giá nhập ở hợp đồng sẽ lưu theo contract.</p>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-xl p-2 text-[#8b5e34]/70 hover:bg-[#3d2a18]/5 transition"
          >
            <X className="h-5 w-5 stroke-[2.5]" />
          </button>
        </div>

        {/* Content */}
        <div className="flex-1 overflow-y-auto p-5 space-y-5 custom-scrollbar">
          
          {/* Quick Create Section */}
          <div className="border border-[#3d2a18]/10 rounded-2xl p-4 bg-white/40">
            <button
              type="button"
              onClick={() => setShowQuickCreate(!showQuickCreate)}
              className="flex w-full items-center justify-between text-sm font-black text-[#24170d] hover:text-[#a65f16] transition"
            >
              <span className="flex items-center gap-2">
                <Plus className="h-4 w-4 stroke-[2.8]" />
                Tạo nhanh dịch vụ mới
              </span>
              <span className="text-xs font-bold text-[#8b5e34]">{showQuickCreate ? 'Thu gọn' : 'Mở rộng'}</span>
            </button>

            {showQuickCreate && (
              <form onSubmit={handleQuickCreate} className="mt-4 space-y-3 border-t border-[#3d2a18]/10 pt-4">
                {quickCreateError && (
                  <div className="p-3 rounded-xl border border-rose-200 bg-rose-50 text-rose-700 text-xs font-black">
                    {quickCreateError}
                  </div>
                )}
                
                <div className="grid grid-cols-2 gap-3">
                  <div className="col-span-2">
                    <label className="mb-1 block px-1 text-[9px] font-black uppercase tracking-widest text-[#8b5e34]/65">Tên dịch vụ</label>
                    <input
                      type="text"
                      value={newServiceName}
                      onChange={(e) => setNewServiceName(e.target.value)}
                      placeholder="Ví dụ: Dịch vụ dọn phòng, Gym..."
                      className="w-full rounded-xl border border-[#3d2a18]/10 bg-white px-3 py-2 text-xs font-bold text-[#3d2a18] outline-none"
                    />
                  </div>

                  <div>
                    <label className="mb-1 block px-1 text-[9px] font-black uppercase tracking-widest text-[#8b5e34]/65">Tính phí theo</label>
                    <AdminSelect
                      value={newChargeMethod}
                      options={[
                        { value: '1', label: 'Theo chỉ số' },
                        { value: '2', label: 'Cố định hàng tháng' },
                        { value: '3', label: 'Miễn phí' },
                      ]}
                      onChange={(val) => setNewChargeMethod(String(val))}
                    />
                  </div>

                  <div>
                    <label className="mb-1 block px-1 text-[9px] font-black uppercase tracking-widest text-[#8b5e34]/65">Đơn vị tính</label>
                    <input
                      type="text"
                      value={newUnitName}
                      onChange={(e) => setNewUnitName(e.target.value)}
                      placeholder="Ví dụ: tháng, người, khối..."
                      className="w-full rounded-xl border border-[#3d2a18]/10 bg-white px-3 py-2 text-xs font-bold text-[#3d2a18] outline-none"
                    />
                  </div>

                  <div className="col-span-2">
                    <label className="mb-1 block px-1 text-[9px] font-black uppercase tracking-widest text-[#8b5e34]/65">Đơn giá mặc định (VND)</label>
                    <input
                      type="text"
                      value={newPrice}
                      onChange={(e) => setNewPrice(formatMoneyInput(e.target.value))}
                      placeholder="100.000"
                      className="w-full rounded-xl border border-[#3d2a18]/10 bg-white px-3 py-2 text-xs font-bold text-[#3d2a18] outline-none"
                    />
                  </div>
                </div>

                <div className="flex justify-end pt-2">
                  <button
                    type="submit"
                    disabled={isSubmitting}
                    className="inline-flex h-9 items-center justify-center rounded-xl bg-[#24170d] px-4 text-xs font-black text-[#fff4df] shadow-md transition disabled:opacity-50"
                  >
                    {isSubmitting ? 'Đang tạo...' : 'Tạo & thêm ngay'}
                  </button>
                </div>
              </form>
            )}
          </div>

          {/* Services Checklist */}
          <div className="space-y-2">
            <label className="block px-1 text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/65">
              Danh sách dịch vụ theo phòng
            </label>
            <div className="divide-y divide-[#3d2a18]/8 border border-[#3d2a18]/10 rounded-2xl overflow-hidden bg-white/60">
              {buildingServices.map((service) => {
                const checked = isChecked(service.id)
                const fixed = isFixedService(service.name, service.slug)
                const selected = selectedServices.find((item) => String(item.service_id) === String(service.id))
                const defaultPrice = displayPrice(service.price || service.base_price || service.effective_price || 0)
                const contractPrice = selected?.price || defaultPrice

                return (
                  <button
                    key={service.id}
                    type="button"
                    onClick={() => handleToggleService(service)}
                    disabled={fixed}
                    className={cn(
                      'flex w-full items-center justify-between gap-4 p-4 text-left transition',
                      checked ? 'bg-[#fff4df]' : 'bg-white/40',
                      fixed ? 'cursor-not-allowed opacity-70' : 'hover:bg-[#f3c56b]/10'
                    )}
                  >
                    <div className="min-w-0">
                      <h4 className="text-sm font-black text-[#24170d]">{service.name}</h4>
                      <p className="text-xs text-[#8b5e34]/70 mt-0.5">
                        Hình thức: {service.charge_method_label} · Đơn vị: {service.unit_name}
                      </p>

                    </div>

                    <div className="flex shrink-0 items-center gap-3">
                      <div className="hidden items-center gap-2 rounded-2xl border border-[#3d2a18]/10 bg-white/75 px-3 py-2 sm:flex">
                        <div>
                          <p className="text-[9px] font-black uppercase tracking-widest text-[#8b5e34]/60">Mặc định</p>
                          <p className="text-xs font-black tabular-nums text-[#6f6254]">{defaultPrice} đ</p>
                        </div>
                        <ArrowRight className="h-3.5 w-3.5 text-[#b7894f]" />
                        <div>
                          <p className="text-[9px] font-black uppercase tracking-widest text-[#8b5e34]/60">Hợp đồng</p>
                          <p className="text-xs font-black tabular-nums text-[#24170d]">{contractPrice} đ</p>
                        </div>
                      </div>
                      <div
                        className={cn(
                          'flex h-5 w-5 items-center justify-center rounded-md border transition',
                          checked
                            ? 'border-[#24170d] bg-[#24170d] text-white'
                            : 'border-[#3d2a18]/20 bg-white',
                          fixed && 'opacity-60'
                        )}
                      >
                        {checked && <Check className="h-3.5 w-3.5 stroke-[3]" />}
                      </div>
                    </div>
                  </button>
                )
              })}
              {buildingServices.length === 0 && (
                <div className="p-8 text-center text-sm font-bold text-[#8b5e34]/60 bg-white/40">
                  Chưa có dịch vụ nào cấu hình cho tòa nhà này.
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Footer */}
        <div className="border-t border-[#3d2a18]/10 bg-[#fff8eb] p-4 flex justify-end">
          <button
            type="button"
            onClick={onClose}
            className="rounded-xl bg-[#24170d] px-5 py-2.5 text-xs font-black text-[#fff4df] shadow-md transition"
          >
            Đồng ý
          </button>
        </div>
      </div>
    </div>
  )
}
