import { useCallback, useEffect, useMemo, useState, Fragment } from 'react'
import { ConfirmModal } from '../../../../shared/components/ConfirmModal'
import { useConfirmModal } from '../../../../shared/lib/hooks/use-confirm-modal'
import { Boxes, ChevronLeft, ChevronRight, Edit3, Eye, Plus, Power, Search, Trash2, X } from 'lucide-react'
import { AssetTemplateModal } from './asset-template-modal'
import { cn } from '../../../../shared/lib/utils/cn'
import { AdminSelect } from '../../shared/components/AdminSelect'
import {
  deleteAdminAssetTemplate,
  fetchAdminAssetTemplateDetail,
  fetchAdminAssetTemplates,
  updateAdminAssetTemplateStatus,
} from '../services/asset-templates.service'
import type { AdminAssetTemplateResource, AdminPaginationMeta } from '../types/asset-template-api.model'
import type { AssetTemplateFormValues } from '../validations/asset-template.validation'



const defaultForm: AssetTemplateFormValues = {
  name: '',
  default_unit_name: 1,
  description: '',
  status: 1,
}

const unitLabels: Record<number, string> = {
  1: 'cái',
  2: 'bộ',
  3: 'chiếc',
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

export function AssetTemplatesScreen() {
  const [keyword, setKeyword] = useState('')
  const [selectedStatus, setSelectedStatus] = useState('')
  const [assetTemplates, setAssetTemplates] = useState<AdminAssetTemplateResource[]>([])
  const [allAssetTemplates, setAllAssetTemplates] = useState<AdminAssetTemplateResource[]>([])
  const [perPage, setPerPage] = useState(10)
  const [currentPage, setCurrentPage] = useState(1)
  const [paginationMeta, setPaginationMeta] = useState<AdminPaginationMeta | null>(null)
  const [editingAssetTemplateId, setEditingAssetTemplateId] = useState<number | null>(null)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [form, setForm] = useState<AssetTemplateFormValues>(defaultForm)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const { confirmState, isConfirmLoading, setIsConfirmLoading, showConfirm, closeConfirm } = useConfirmModal()

  const [activeMessage, setActiveMessage] = useState<string | null>(null)
  const [activeType, setActiveType] = useState<'success' | 'error' | null>(null)

  useEffect(() => {
    if (successMessage) {
      const timer = setTimeout(() => {
        setSuccessMessage(null)
      }, 3000)
      return () => clearTimeout(timer)
    }
  }, [successMessage])

  useEffect(() => {
    if (errorMessage) {
      const timer = setTimeout(() => {
        setErrorMessage(null)
      }, 3000)
      return () => clearTimeout(timer)
    }
  }, [errorMessage])

  useEffect(() => {
    if (successMessage) {
      setActiveMessage(successMessage)
      setActiveType('success')
    } else if (errorMessage) {
      setActiveMessage(errorMessage)
      setActiveType('error')
    } else {
      const timer = setTimeout(() => {
        setActiveMessage(null)
        setActiveType(null)
      }, 500)
      return () => clearTimeout(timer)
    }
  }, [successMessage, errorMessage])

  const [detailAssetTemplate, setDetailAssetTemplate] = useState<AdminAssetTemplateResource | null>(null)
  const [isDetailOpen, setIsDetailOpen] = useState(false)
  const [isDetailLoading, setIsDetailLoading] = useState(false)
  const [detailErrorMessage, setDetailErrorMessage] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [statusChangingId, setStatusChangingId] = useState<number | null>(null)

  const loadAllAssetTemplates = useCallback(async () => {
    try {
      const response = await fetchAdminAssetTemplates({ per_page: 1000 })
      const data = response.result?.data ?? []
      setAllAssetTemplates(data)
    } catch (error) {
      console.error('Failed to load all asset templates:', error)
    }
  }, [])

  const loadAssetTemplates = useCallback(async () => {
    setIsLoading(true)
    setErrorMessage(null)

    try {
      const assetTemplateResponse = await fetchAdminAssetTemplates({
        keyword: keyword.trim() || undefined,
        status: selectedStatus ? Number(selectedStatus) : undefined,
        page: currentPage,
        per_page: perPage,
      })

      const result = assetTemplateResponse.result
      const data = result?.data ?? []
      const meta = result?.meta ?? null

      setAssetTemplates(data)
      setPaginationMeta(meta)

      if (meta?.last_page && currentPage > meta.last_page) {
        setCurrentPage(meta.last_page)
      }
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Không thể tải danh sách mẫu tài sản.')
    } finally {
      setIsLoading(false)
    }
  }, [keyword, selectedStatus, currentPage, perPage])

  useEffect(() => {
    void loadAllAssetTemplates()
  }, [loadAllAssetTemplates])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadAssetTemplates()
    }, 250)

    return () => window.clearTimeout(timer)
  }, [loadAssetTemplates])

  useEffect(() => {
    setCurrentPage(1)
  }, [keyword, selectedStatus])

  const activeAssetTemplates = useMemo(() => allAssetTemplates.filter((item) => Number(item.status) === 1).length, [allAssetTemplates])

  const openCreateForm = () => {
    if (editingAssetTemplateId !== null) {
      setForm({ ...defaultForm })
    }
    setEditingAssetTemplateId(null)
    setErrorMessage(null)
    setSuccessMessage(null)
    setIsFormOpen(true)
  }

  const editAssetTemplate = (assetTemplate: AdminAssetTemplateResource) => {
    if (editingAssetTemplateId !== assetTemplate.id) {
      setEditingAssetTemplateId(assetTemplate.id)
      setForm({
        name: assetTemplate.name || '',
        default_unit_name: Number(assetTemplate.default_unit_name || 1),
        description: assetTemplate.description || '',
        status: Number(assetTemplate.status || 1),
      })
      setErrorMessage(null)
      setSuccessMessage(null)
    }
    setIsFormOpen(true)
  }

  const viewAssetTemplate = async (assetTemplate: AdminAssetTemplateResource) => {
    setDetailAssetTemplate(assetTemplate)
    setIsDetailOpen(true)
    setIsDetailLoading(true)
    setDetailErrorMessage(null)

    try {
      const response = await fetchAdminAssetTemplateDetail(assetTemplate.id)
      setDetailAssetTemplate(response.result)
    } catch (error) {
      setDetailErrorMessage(error instanceof Error ? error.message : 'Không thể tải chi tiết mẫu tài sản.')
    } finally {
      setIsDetailLoading(false)
    }
  }

  const closeAssetTemplateDetail = () => {
    setIsDetailOpen(false)
    setDetailAssetTemplate(null)
    setDetailErrorMessage(null)
  }

  const handleCancelForm = () => {
    setIsFormOpen(false)
    setEditingAssetTemplateId(null)
    setForm({ ...defaultForm })
  }

  const handleCloseForm = () => {
    setIsFormOpen(false)
  }

  const handleSubmitSuccess = () => {
    setIsFormOpen(false)
    setEditingAssetTemplateId(null)
    setForm({ ...defaultForm })
    setSuccessMessage(editingAssetTemplateId ? 'Cập nhật mẫu tài sản thành công.' : 'Tạo mẫu tài sản thành công.')
    void loadAssetTemplates()
    void loadAllAssetTemplates()
  }

  const toggleAssetTemplateStatus = (assetTemplate: AdminAssetTemplateResource) => {
    const nextStatus = Number(assetTemplate.status) === 1 ? 2 : 1

    const doToggle = async () => {
      try {
        setIsConfirmLoading(true)
        setStatusChangingId(assetTemplate.id)
        setErrorMessage(null)
        setSuccessMessage(null)
        await updateAdminAssetTemplateStatus(assetTemplate.id, nextStatus)
        setSuccessMessage(`${nextStatus === 1 ? 'Kích hoạt' : 'Ngừng hoạt động'} mẫu tài sản thành công.`)
        await loadAssetTemplates()
        await loadAllAssetTemplates()
      } catch (error) {
        setErrorMessage(error instanceof Error ? error.message : 'Không thể đổi trạng thái mẫu tài sản.')
      } finally {
        setIsConfirmLoading(false)
        setStatusChangingId(null)
        closeConfirm()
      }
    }

    if (nextStatus === 2) {
      showConfirm({
        title: 'Tắt hoạt động mẫu tài sản',
        message: `Bạn có chắc muốn tắt hoạt động mẫu tài sản ${assetTemplate.name}?`,
        confirmLabel: 'Tắt hoạt động',
        onConfirm: doToggle,
        variant: 'warning',
      })
    } else {
      void doToggle()
    }
  }

  const removeAssetTemplate = (assetTemplate: AdminAssetTemplateResource) => {
    showConfirm({
      title: 'Xóa mẫu tài sản',
      message: `Bạn có chắc chắn muốn xóa mẫu tài sản ${assetTemplate.name}?`,
      confirmLabel: 'Xóa',
      onConfirm: async () => {
        try {
          setIsConfirmLoading(true)
          setErrorMessage(null)
          await deleteAdminAssetTemplate(assetTemplate.id)
          setSuccessMessage('Xóa mẫu tài sản thành công.')
          await loadAssetTemplates()
          await loadAllAssetTemplates()
        } catch (error) {
          setErrorMessage(error instanceof Error ? error.message : 'Không thể xóa mẫu tài sản.')
        } finally {
          setIsConfirmLoading(false)
          closeConfirm()
        }
      },
      variant: 'danger',
    })
  }

  const clearFilters = () => {
    setKeyword('')
    setSelectedStatus('')
    setCurrentPage(1)
  }

  const safeCurrentPage = Math.max(1, Math.min(currentPage, paginationMeta?.last_page ?? currentPage))
  const totalPages = Math.max(1, paginationMeta?.last_page ?? (assetTemplates.length >= perPage ? currentPage + 1 : currentPage))
  const paginationStart = paginationMeta?.from ?? (assetTemplates.length === 0 ? 0 : (safeCurrentPage - 1) * perPage + 1)
  const paginationEnd = paginationMeta?.to ?? (assetTemplates.length === 0 ? 0 : (safeCurrentPage - 1) * perPage + assetTemplates.length)

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
              <div className="relative flex min-w-0 flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div className="min-w-0">
                  <span className="block text-xs font-black uppercase tracking-[0.18em] text-[#f3c56b]/80">QUẢN LÝ LƯU TRÚ</span>
                  <h1 className="mt-3 text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl lg:text-[2.65rem] flex items-center gap-3">
                    <Boxes className="h-8 w-8 text-[#f3c56b] shrink-0" />
                    Mẫu tài sản
                  </h1>
                </div>
                <button type="button" onClick={openCreateForm} className="inline-flex h-9 w-fit self-end lg:self-auto items-center justify-center gap-2 rounded-xl bg-[#f3c56b] px-4 text-sm font-black text-[#24170d] shadow-xl shadow-[#a65f16]/20 transition-all hover:bg-[#ffd56f] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/35 active:scale-[0.98]">
                  <Plus className="h-4 w-4 stroke-[2.8]" /> Thêm mẫu tài sản
                </button>
              </div>

              <div className="relative mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <MetricCard label="Tổng số mẫu" value={allAssetTemplates.length} tone="neutral" />
                <MetricCard label="Đang hoạt động" value={activeAssetTemplates} tone="emerald" />
              </div>
            </div>
          </div>

          <div
            className={cn(
              'rounded-3xl border px-4 text-sm font-black shadow-sm transition-all duration-500 ease-in-out transform overflow-hidden',
              (successMessage || errorMessage)
                ? 'opacity-100 max-h-20 py-3 translate-y-0 scale-100'
                : 'opacity-0 max-h-0 py-0 -translate-y-2 scale-95 pointer-events-none border-transparent',
              (errorMessage || activeType === 'error')
                ? 'border-rose-200 bg-rose-50 text-rose-700'
                : 'border-emerald-200 bg-emerald-50 text-emerald-700'
            )}
          >
            {activeMessage || errorMessage || successMessage}
          </div>

          <div className="grid min-w-0 grid-cols-1 gap-4 lg:gap-6">
            <section className="min-w-0 overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/88 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
              <div className="border-b border-[#3d2a18]/10 bg-[#fff7e8]/72 p-4 sm:p-5">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
                  <div className="relative min-w-0 flex-1">
                    <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#a65f16]" />
                    <input type="text" value={keyword} onChange={(event) => setKeyword(event.target.value)} placeholder="Tìm tên hoặc mô tả mẫu tài sản..." className={`${inputClass} pl-11 pr-28`} />
                    <button type="button" onClick={clearFilters} className="absolute right-2 top-1/2 inline-flex h-9 -translate-y-1/2 items-center justify-center gap-1.5 rounded-xl px-3 text-xs font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/16 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20">
                      <X className="h-3.5 w-3.5" /> Xóa lọc
                    </button>
                  </div>
                  <div className="ml-auto grid w-full grid-cols-1 gap-3 sm:grid-cols-1 lg:w-auto">
                    <AdminSelect value={selectedStatus} options={statusOptions} className="lg:w-64" onChange={(nextValue) => setSelectedStatus(String(nextValue))} />
                  </div>
                </div>
              </div>

              <div className="overflow-x-auto">
                <table className="min-w-[880px] w-full text-left">
                  <thead className="bg-[#24170d] text-[10px] font-black uppercase tracking-[0.18em] text-[#f8e8c8]">
                    <tr>
                      <th className="px-5 py-4">Tên tài sản</th>
                      <th className="px-5 py-4 text-center">Đơn vị</th>
                      <th className="px-5 py-4 text-center">Số lượng đang dùng</th>
                      <th className="px-5 py-4 text-center">Trạng thái</th>
                      <th className="px-5 py-4 w-[190px]"><div className="flex justify-end"><div className="w-[190px] text-center">Thao tác</div></div></th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-[#3d2a18]/8">
                    {isLoading && Array.from({ length: 5 }).map((_, index) => (
                      <tr key={index}>
                        <td colSpan={6} className="px-5 py-4"><div className="h-12 animate-pulse rounded-2xl bg-stone-100" /></td>
                      </tr>
                    ))}

                    {!isLoading && assetTemplates.map((assetTemplate) => {

                      return (
                        <tr key={assetTemplate.id} className="group transition hover:bg-[#f3c56b]/12">
                          <td className="px-4 py-3">
                            <div className="flex items-center gap-2.5">
                              <div className="flex h-10 w-10 items-center justify-center rounded-xl border border-[#f3c56b]/35 bg-[#f3c56b]/18 text-[#a65f16] shadow-sm transition group-hover:scale-105">
                                <Boxes className="h-4 w-4" />
                              </div>
                              <div className="min-w-0">
                                <p className="truncate text-[13px] font-black tracking-tight text-[#24170d]">{assetTemplate.name}</p>
                              </div>
                            </div>
                          </td>
                          <td className="px-4 py-3 text-center text-[13px] font-black text-[#24170d]">{assetTemplate.default_unit_label || unitLabels[Number(assetTemplate.default_unit_name || 1)]}</td>
                          <td className="px-4 py-3 text-center text-[13px] font-black text-[#24170d] tabular-nums">{assetTemplate.room_assets_count ?? 0}</td>
                          <td className="px-4 py-3 text-center">
                            <span className={cn('inline-flex items-center justify-center whitespace-nowrap rounded-full border px-2.5 py-0.5 text-[11px] font-black shadow-sm', Number(assetTemplate.status) === 1 ? 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]' : 'border-[#3d2a18]/10 bg-[#efe2cf]/65 text-[#6f6254]')}>
                              {assetTemplate.status_label || statusLabels[Number(assetTemplate.status)]}
                            </span>
                          </td>
                          <td className="px-4 py-3">
                            <div className="flex items-center justify-end gap-2.5">
                              <button type="button" onClick={() => void viewAssetTemplate(assetTemplate)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#0f766e]/25 hover:bg-[#0f766e]/10 hover:text-[#0f5f59] focus:outline-none focus:ring-4 focus:ring-[#0f766e]/10 active:scale-95" title="Xem chi tiết"><Eye className="h-5 w-5" /></button>
                              <button type="button" onClick={() => editAssetTemplate(assetTemplate)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#3d2a18]/25 hover:bg-[#f3c56b]/15 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#3d2a18]/10 active:scale-95 disabled:cursor-not-allowed disabled:opacity-45" title={'Chỉnh sửa'}><Edit3 className="h-5 w-5" /></button>
                              <button type="button" disabled={statusChangingId === assetTemplate.id} onClick={() => void toggleAssetTemplateStatus(assetTemplate)} className={cn('inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition focus:outline-none focus:ring-4 active:scale-95 disabled:cursor-not-allowed disabled:opacity-55', Number(assetTemplate.status) === 1 ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 focus:ring-emerald-100' : 'border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100 focus:ring-rose-100')} title={Number(assetTemplate.status) === 1 ? 'Ngừng hoạt động' : 'Kích hoạt'}><Power className="h-5 w-5" /></button>
                              <button type="button" onClick={() => void removeAssetTemplate(assetTemplate)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600 focus:outline-none focus:ring-4 focus:ring-rose-100 active:scale-95 disabled:cursor-not-allowed disabled:opacity-45" title={'Xóa'}><Trash2 className="h-5 w-5" /></button>
                            </div>
                          </td>
                        </tr>
                      )
                    })}

                    {!isLoading && assetTemplates.length === 0 && (
                      <tr>
                        <td colSpan={6} className="px-5 py-20 text-center">
                          <div className="mx-auto flex max-w-sm flex-col items-center">
                            <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-[1.75rem] border border-dashed border-[#f3c56b] bg-[#f3c56b]/15 text-[#a65f16]"><Boxes className="h-9 w-9" /></div>
                            <p className="text-lg font-black tracking-tight text-[#24170d]">Không tìm thấy mẫu tài sản</p>
                            <p className="mt-2 text-sm font-semibold leading-6 text-[#6f6254]">Hãy tạo mẫu mới hoặc đổi bộ lọc hiện tại.</p>
                          </div>
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>

              <div className="flex flex-col gap-3 border-t border-[#3d2a18]/10 bg-[#fff8eb]/85 px-4 py-4 lg:flex-row lg:items-center lg:justify-between">
                <p className="text-xs font-black text-[#6f6254]">
                  Hiển thị <span className="tabular-nums text-[#24170d]">{paginationStart}</span>-<span className="tabular-nums text-[#24170d]">{paginationEnd}</span> / <span className="tabular-nums text-[#24170d]">{paginationMeta?.total ?? allAssetTemplates.length}</span> mẫu tài sản
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

            <AssetTemplateModal
              isOpen={isFormOpen}
              onClose={handleCloseForm}
              editingAssetTemplateId={editingAssetTemplateId}
              form={form}
              setForm={setForm}
              onCancel={handleCancelForm}
              onSubmitSuccess={handleSubmitSuccess}
            />
          </div>
        </section>

        {isDetailOpen && (
          <div className="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <button type="button" aria-label="Đóng chi tiết mẫu tài sản" onClick={closeAssetTemplateDetail} className="absolute inset-0 bg-stone-950/65 backdrop-blur-sm" />
            <div className="relative z-10 w-full max-w-2xl overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] shadow-2xl shadow-stone-950/30">
              <div className="bg-[#24170d] p-5 text-[#fff4df]">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <p className="text-[10px] font-black uppercase tracking-[0.24em] text-[#f3c56b]">Chi tiết mẫu tài sản</p>
                    <h2 id="asset-modal-title" className="mt-2 text-2xl font-black tracking-tight">{detailAssetTemplate?.name || 'Đang tải chi tiết...'}</h2>
                  </div>
                  <button type="button" onClick={closeAssetTemplateDetail} className="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/10 text-white transition hover:bg-white/20">
                    <X className="h-5 w-5" />
                  </button>
                </div>
              </div>

              <div className="space-y-4 p-5">
                {isDetailLoading && <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-black text-amber-800">Đang tải chi tiết mẫu tài sản...</div>}
                {detailErrorMessage && <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-black text-rose-700">{detailErrorMessage}</div>}

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                  <DetailTile label="Đơn vị" value={detailAssetTemplate?.default_unit_label || unitLabels[Number(detailAssetTemplate?.default_unit_name || 1)]} />
                  <DetailTile label="Đang sử dụng" value={detailAssetTemplate?.room_assets_count ?? 0} />
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                  <DetailTile label="Trạng thái" value={detailAssetTemplate?.status_label || statusLabels[Number(detailAssetTemplate?.status || 1)]} />
                  <DetailTile label="Người tạo" value={detailAssetTemplate?.creator_name || 'Chưa cập nhật'} />
                </div>

                <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
                  <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">Mô tả</p>
                  <p className="mt-2 text-sm font-semibold leading-6 text-[#3d2a18]">{detailAssetTemplate?.description || 'Chưa có mô tả.'}</p>
                </section>
              </div>
            </div>
          </div>
        )}
      </>
      <ConfirmModal {...confirmState} onCancel={closeConfirm} isLoading={isConfirmLoading} />
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

