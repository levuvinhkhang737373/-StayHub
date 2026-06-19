import { AdminDateInput } from '../../../../../shared/components/AdminDateInput'
import { cn } from '../../../../../shared/lib/utils/cn'
import { AdminSelect } from '../../../shared/components/AdminSelect'
import type { AdminContractResource } from '../../types/contract-api.model'
import { getStatusLabel, getStatusChangeOptions, STATUS_PENDING_SIGN } from '../../utils/contract.helpers'
import { inputClass, labelClass } from '../form/form-elements'

export function StatusModal({
  contract,
  form,
  isSaving,
  onChange,
  onClose,
  onSubmit,
}: {
  contract: AdminContractResource
  form: { status: number; actual_end_date: string; note: string }
  isSaving: boolean
  onChange: (value: { status: number; actual_end_date: string; note: string }) => void
  onClose: () => void
  onSubmit: () => void
}) {
  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center p-4">
      <button type="button" className="absolute inset-0 bg-stone-950/65 backdrop-blur-sm" onClick={onClose} aria-label="Đóng cập nhật trạng thái" />
      <div className="relative z-10 w-full max-w-md rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] p-5 shadow-2xl">
        <h2 className="text-lg font-black text-[#24170d]">Cập nhật trạng thái hợp đồng</h2>
        <p className="mt-1 text-xs font-bold text-[#8b5e34]/70">
          {contract.contract_code} · trạng thái hiện tại: {contract.status_label || getStatusLabel(contract.status)}
        </p>
        <div className="mt-5 space-y-4">
          <div>
            <label className={labelClass}>Trạng thái mới</label>
            <AdminSelect value={form.status} options={getStatusChangeOptions(Number(contract.status))} onChange={(value: string | number) => onChange({ ...form, status: Number(value) })} />
          </div>
          {Number(contract.status) !== STATUS_PENDING_SIGN && (
            <div>
              <label className={labelClass}>Ngày kết thúc thực tế</label>
              <AdminDateInput className={inputClass} value={form.actual_end_date} onChange={(value: string) => onChange({ ...form, actual_end_date: value })} />
            </div>
          )}
          <div>
            <label className={labelClass}>Ghi chú</label>
            <textarea
              className={cn(inputClass, 'min-h-24')}
              value={form.note}
              onChange={(event) => onChange({ ...form, note: event.target.value })}
              placeholder="Lý do đổi trạng thái"
            />
          </div>
          <div className="flex gap-3">
            <button type="button" onClick={onClose} className="h-12 flex-1 rounded-xl border border-[#3d2a18]/10 bg-white text-sm font-black text-[#8b5e34]">
              Hủy
            </button>
            <button
              type="button"
              disabled={isSaving}
              onClick={onSubmit}
              className="h-12 flex-1 rounded-xl bg-[#24170d] text-sm font-black text-[#fff4df] disabled:opacity-60"
            >
              {isSaving ? 'Đang lưu...' : 'Cập nhật'}
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}
