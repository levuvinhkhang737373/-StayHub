import { useCallback, useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { ArrowLeft, Database, Edit3, Eye, Plus, Power, ReceiptText, Search, Tags, Trash2, X } from 'lucide-react'
import { formatDateTime } from '../../../../shared/lib/utils/format'
import { isSuperAdminRole, useAdminSession } from '../../auth/hooks/use-admin-session'
import { AdminSelect } from '../../shared/components/AdminSelect'
import { ExpenseCategoryModal } from './expense-category-modal'
import { cn } from '../../../../shared/lib/utils/cn'
import {
  deleteAdminExpenseCategory,
  fetchAdminExpenseCategories,
  fetchAdminExpenseCategoryDetail,
  updateAdminExpenseCategoryStatus,
} from '../services/expense-categories.service'
import type { AdminExpenseCategoryResource } from '../types/expense-category-api.model'
import type { ExpenseCategoryFormValues } from '../validations/expense-category.validation'

function getResourceList<T>(result: { data?: T[] } | T[] | null | undefined): T[] {
  if (!result) return []
  if (Array.isArray(result)) return result
  return result.data || []
}

const defaultForm: ExpenseCategoryFormValues = {
  name: '',
  description: '',
  is_active: true,
}

const statusOptions = [
  { value: '', label: 'Tất cả trạng thái', tone: 'default' as const },
  { value: '1', label: 'Đang sử dụng', tone: 'success' as const },
  { value: '0', label: 'Hết sử dụng', tone: 'danger' as const },
]

const inputClass = 'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20'

export function ExpenseCategoriesScreen() {
  const { session } = useAdminSession()
  const isSuperAdmin = isSuperAdminRole(session?.admin.role)
  const [keyword, setKeyword] = useState('')
  const [selectedStatus, setSelectedStatus] = useState('')
  const [expenseCategories, setExpenseCategories] = useState<AdminExpenseCategoryResource[]>([])
  const [editingExpenseCategoryId, setEditingExpenseCategoryId] = useState<number | null>(null)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [form, setForm] = useState<ExpenseCategoryFormValues>(defaultForm)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [detailExpenseCategory, setDetailExpenseCategory] = useState<AdminExpenseCategoryResource | null>(null)
  const [isDetailOpen, setIsDetailOpen] = useState(false)
  const [isDetailLoading, setIsDetailLoading] = useState(false)
  const [detailErrorMessage, setDetailErrorMessage] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [statusChangingId, setStatusChangingId] = useState<number | null>(null)

  const loadExpenseCategories = useCallback(async () => {
    setIsLoading(true)
    setErrorMessage(null)

    try {
      const response = await fetchAdminExpenseCategories({
        keyword: keyword.trim() || undefined,
        is_active: selectedStatus === '' ? undefined : selectedStatus === '1',
        per_page: 100,
      })

      setExpenseCategories(getResourceList(response.result))
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Không thể tải danh mục chi phí.')
    } finally {
      setIsLoading(false)
    }
  }, [keyword, selectedStatus])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadExpenseCategories()
    }, 250)

    return () => window.clearTimeout(timer)
  }, [loadExpenseCategories])

  const activeCategories = useMemo(() => expenseCategories.filter((item) => item.is_active).length, [expenseCategories])
  const inactiveCategories = useMemo(() => expenseCategories.filter((item) => !item.is_active).length, [expenseCategories])
  const usedExpenses = useMemo(() => expenseCategories.reduce((sum, item) => sum + Number(item.expenses_count || 0), 0), [expenseCategories])
  const hasActiveFilters = Boolean(keyword.trim() || selectedStatus)

  const openCreateForm = () => {
    if (editingExpenseCategoryId !== null) {
      setForm({ ...defaultForm })
    }
    setEditingExpenseCategoryId(null)
    setErrorMessage(null)
    setSuccessMessage(null)
    setIsFormOpen(true)
  }

  const editExpenseCategory = (expenseCategory: AdminExpenseCategoryResource) => {
    if (editingExpenseCategoryId !== expenseCategory.id) {
      setEditingExpenseCategoryId(expenseCategory.id)
      setForm({
        name: expenseCategory.name || '',
        description: expenseCategory.description || '',
        is_active: Boolean(expenseCategory.is_active),
      })
      setErrorMessage(null)
      setSuccessMessage(null)
    }
    setIsFormOpen(true)
  }

  const viewExpenseCategory = async (expenseCategory: AdminExpenseCategoryResource) => {
    setDetailExpenseCategory(expenseCategory)
    setIsDetailOpen(true)
    setIsDetailLoading(true)
    setDetailErrorMessage(null)

    try {
      const response = await fetchAdminExpenseCategoryDetail(expenseCategory.id)
      setDetailExpenseCategory(response.result)
    } catch (error) {
      setDetailErrorMessage(error instanceof Error ? error.message : 'Không thể tải chi tiết danh mục chi phí.')
    } finally {
      setIsDetailLoading(false)
    }
  }

  const closeExpenseCategoryDetail = () => {
    setIsDetailOpen(false)
    setDetailExpenseCategory(null)
    setDetailErrorMessage(null)
  }

  useEffect(() => {
    if (!isDetailOpen) return

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        setIsDetailOpen(false)
        setDetailExpenseCategory(null)
        setDetailErrorMessage(null)
      }
    }

    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [isDetailOpen])

  const handleCancelForm = () => {
    setIsFormOpen(false)
    setEditingExpenseCategoryId(null)
    setForm({ ...defaultForm })
  }

  const handleCloseForm = () => {
    setIsFormOpen(false)
  }

  const handleSubmitSuccess = () => {
    setIsFormOpen(false)
    setEditingExpenseCategoryId(null)
    setForm({ ...defaultForm })
    setSuccessMessage(editingExpenseCategoryId ? 'Cập nhật danh mục chi phí thành công.' : 'Tạo danh mục chi phí thành công.')
    void loadExpenseCategories()
  }

  const toggleExpenseCategoryStatus = async (expenseCategory: AdminExpenseCategoryResource) => {
    if (!isSuperAdmin) return

    const nextStatus = !expenseCategory.is_active

    if (!nextStatus && !window.confirm(`Bạn có chắc muốn chuyển danh mục ${expenseCategory.name} sang hết sử dụng?`)) return

    try {
      setStatusChangingId(expenseCategory.id)
      setErrorMessage(null)
      setSuccessMessage(null)
      await updateAdminExpenseCategoryStatus(expenseCategory.id, nextStatus)
      setSuccessMessage(`${nextStatus ? 'Kích hoạt' : 'Ngừng sử dụng'} danh mục chi phí thành công.`)
      await loadExpenseCategories()
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Không thể đổi trạng thái danh mục chi phí.')
    } finally {
      setStatusChangingId(null)
    }
  }

  const removeExpenseCategory = async (expenseCategory: AdminExpenseCategoryResource) => {
    if (!isSuperAdmin) return
    if (!window.confirm(`Bạn có chắc chắn muốn xóa danh mục ${expenseCategory.name}? Danh mục đã phát sinh phiếu chi sẽ không thể xóa.`)) return

    try {
      setErrorMessage(null)
      await deleteAdminExpenseCategory(expenseCategory.id)
      setSuccessMessage('Xóa danh mục chi phí thành công.')
      await loadExpenseCategories()
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Không thể xóa danh mục chi phí.')
    }
  }

  const clearFilters = () => {
    setKeyword('')
    setSelectedStatus('')
  }

  return (
    <>
      <section className="space-y-5 sm:space-y-6 text-[#24170d]">
        <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
          <div className="relative p-4 text-[#fff4df] sm:p-6 xl:p-7">
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_14%,rgba(243,197,107,0.28),transparent_32%),radial-gradient(circle_at_82%_8%,rgba(15,118,110,0.26),transparent_34%),linear-gradient(135deg,#24170d_0%,#3d2a18_52%,#0f3f3b_100%)]" />
            <div className="pointer-events-none absolute inset-x-6 bottom-0 h-px bg-gradient-to-r from-transparent via-[#f3c56b]/40 to-transparent" />

            <div className="relative flex min-w-0 flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
              <div className="min-w-0">
                <Link to="/admin/dashboard" className="mb-2 inline-flex items-center gap-2 text-xs font-black text-[#f3c56b] transition hover:text-[#ffd56f]">
                  <ArrowLeft className="h-3.5 w-3.5" /> Về dashboard
                </Link>

                <p className="text-xs font-black uppercase tracking-[0.22em] text-[#f3c56b]/80">Cấu hình khoản chi</p>
                <h1 className="mt-3 max-w-3xl text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl lg:text-[2.65rem]">Danh mục chi phí</h1>
              </div>
              {isSuperAdmin && (
                <button type="button" onClick={openCreateForm} className="inline-flex h-11 shrink-0 items-center justify-center gap-2 self-start whitespace-nowrap rounded-xl bg-[#f3c56b] px-5 text-sm font-black text-[#24170d] shadow-xl shadow-[#a65f16]/20 transition-all hover:bg-[#ffd56f] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/35 active:scale-[0.98] xl:self-center">
                  <Plus className="h-4 w-4 stroke-[2.8]" /> Thêm danh mục
                </button>
              )}
            </div>

            <div className="relative mt-6 grid grid-cols-[repeat(auto-fit,minmax(14rem,1fr))] gap-3">
              <MetricCard icon={<Tags className="h-4 w-4" />} label="Tổng danh mục" value={expenseCategories.length} tone="neutral" />
              <MetricCard icon={<Power className="h-4 w-4" />} label="Đang sử dụng" value={activeCategories} tone="emerald" />
              <MetricCard icon={<Power className="h-4 w-4" />} label="Hết sử dụng" value={inactiveCategories} tone="amber" />
              <MetricCard icon={<ReceiptText className="h-4 w-4" />} label="Phiếu chi liên kết" value={usedExpenses} tone="teal" />
            </div>
          </div>
        </section>

        {(errorMessage || successMessage) && (
          <div className={cn('rounded-3xl border px-4 py-3 text-sm font-black shadow-sm', errorMessage ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700')}>
            {errorMessage || successMessage}
          </div>
        )}



        <div className="grid min-w-0 grid-cols-1 gap-4 xl:gap-6">
          <section className="min-w-0 overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
            <div className="border-b border-[#3d2a18]/10 bg-[#fff8eb]/85 p-4 sm:p-5">
              <div className="grid gap-3 lg:grid-cols-[minmax(18rem,1fr)_minmax(12rem,14rem)]">
                <div className="relative min-w-0">
                  <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#a65f16]" />
                  <input type="text" value={keyword} onChange={(event) => setKeyword(event.target.value)} placeholder="Tìm tên hoặc mô tả danh mục chi phí..." className={`${inputClass} pl-11 pr-28`} />
                  <button type="button" onClick={clearFilters} disabled={!hasActiveFilters} className="absolute right-2 top-1/2 inline-flex h-9 -translate-y-1/2 items-center justify-center gap-1.5 rounded-xl px-3 text-xs font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/16 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20 disabled:cursor-not-allowed disabled:opacity-45">
                    <X className="h-3.5 w-3.5" /> Xóa lọc
                  </button>
                </div>
                <AdminSelect value={selectedStatus} options={statusOptions} onChange={(nextValue) => setSelectedStatus(String(nextValue))} />
              </div>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full min-w-[920px] text-left">
                <thead className="bg-[#24170d] text-[10px] font-black uppercase tracking-[0.18em] text-[#f8e8c8]">
                  <tr>
                    <th className="px-5 py-4">Danh mục</th>
                    <th className="px-5 py-4">Mô tả</th>
                    <th className="px-5 py-4 text-center">Phiếu chi</th>
                    <th className="px-5 py-4 text-center">Người tạo</th>
                    <th className="px-5 py-4 text-center">Trạng thái</th>
                    <th className="px-5 py-4 w-[180px]"><div className="flex justify-end"><div className="w-[180px] text-center">Thao tác</div></div></th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[#3d2a18]/8 bg-[#fffaf1]/70">
                  {isLoading && Array.from({ length: 5 }).map((_, index) => (
                    <tr key={index}>
                      <td colSpan={6} className="px-5 py-4"><div className="h-14 animate-pulse rounded-2xl bg-stone-100" /></td>
                    </tr>
                  ))}

                  {!isLoading && expenseCategories.map((expenseCategory) => (
                    <tr key={expenseCategory.id} className="group transition hover:bg-[#f3c56b]/10">
                      <td className="px-5 py-4">
                        <div className="flex items-center gap-3">
                          <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-[#f3c56b]/35 bg-[#f3c56b]/18 text-[#a65f16] shadow-sm transition group-hover:-translate-y-0.5 group-hover:scale-105">
                            <ReceiptText className="h-4 w-4" />
                          </div>
                          <div className="min-w-0">
                            <p className="truncate text-sm font-black tracking-tight text-[#24170d]">{expenseCategory.name}</p>
                            <p className="mt-0.5 text-xs font-bold text-[#8b5e34]/70">Cập nhật {formatDateTime(expenseCategory.updated_at)}</p>
                          </div>
                        </div>
                      </td>
                      <td className="px-5 py-4">
                        <p className="line-clamp-2 max-w-md text-sm font-semibold leading-6 text-[#6f6254]">{expenseCategory.description || 'Chưa có mô tả'}</p>
                      </td>
                      <td className="px-5 py-4 text-center">
                        <span className="inline-flex items-center justify-center gap-1.5 rounded-full border border-[#0f766e]/15 bg-[#0f766e]/10 px-3 py-1 text-xs font-black text-[#0f5f59] shadow-sm">
                          <Database className="h-3.5 w-3.5" /> <span className="tabular-nums">{Number(expenseCategory.expenses_count || 0)}</span>
                        </span>
                      </td>
                      <td className="px-5 py-4 text-center">
                        <span className="text-sm font-black text-[#3d2a18]">{expenseCategory.creator_name || 'Hệ thống'}</span>
                      </td>
                      <td className="px-5 py-4 text-center">
                        <span className={cn('inline-flex items-center justify-center whitespace-nowrap rounded-full border px-3 py-1 text-xs font-black shadow-sm', expenseCategory.is_active ? 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]' : 'border-[#3d2a18]/10 bg-[#efe2cf]/65 text-[#6f6254]')}>
                          {expenseCategory.status_label || (expenseCategory.is_active ? 'Đang sử dụng' : 'Hết sử dụng')}
                        </span>
                      </td>
                      <td className="px-5 py-4">
                        <div className="flex items-center justify-end gap-2">
                          <button type="button" onClick={() => void viewExpenseCategory(expenseCategory)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#0f766e]/25 hover:bg-[#0f766e]/10 hover:text-[#0f5f59] focus:outline-none focus:ring-4 focus:ring-[#0f766e]/10 active:scale-95" title="Xem chi tiết" aria-label={`Xem chi tiết danh mục ${expenseCategory.name}`}><Eye className="h-5 w-5" /></button>
                          {isSuperAdmin && (
                            <>
                              <button type="button" onClick={() => editExpenseCategory(expenseCategory)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#3d2a18]/25 hover:bg-[#f3c56b]/15 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#3d2a18]/10 active:scale-95" title="Chỉnh sửa" aria-label={`Chỉnh sửa danh mục ${expenseCategory.name}`}><Edit3 className="h-5 w-5" /></button>
                              <button type="button" disabled={statusChangingId === expenseCategory.id} onClick={() => void toggleExpenseCategoryStatus(expenseCategory)} className={cn('inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition focus:outline-none focus:ring-4 active:scale-95 disabled:cursor-not-allowed disabled:opacity-55', expenseCategory.is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 focus:ring-emerald-100' : 'border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100 focus:ring-rose-100')} title={expenseCategory.is_active ? 'Chuyển hết sử dụng' : 'Kích hoạt'} aria-label={`${expenseCategory.is_active ? 'Chuyển hết sử dụng' : 'Kích hoạt'} danh mục ${expenseCategory.name}`}><Power className="h-5 w-5" /></button>
                              <button type="button" onClick={() => void removeExpenseCategory(expenseCategory)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600 focus:outline-none focus:ring-4 focus:ring-rose-100 active:scale-95" title="Xóa" aria-label={`Xóa danh mục ${expenseCategory.name}`}><Trash2 className="h-5 w-5" /></button>
                            </>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}

                  {!isLoading && expenseCategories.length === 0 && (
                    <tr>
                      <td colSpan={6} className="px-5 py-20 text-center">
                        <div className="mx-auto flex max-w-sm flex-col items-center rounded-[2rem] border border-dashed border-[#3d2a18]/12 bg-white/55 px-6 py-8">
                          <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-[1.75rem] border border-dashed border-[#f3c56b] bg-[#f3c56b]/15 text-[#a65f16]"><Tags className="h-9 w-9" /></div>
                          <p className="text-lg font-black tracking-tight text-[#24170d]">Không tìm thấy danh mục chi phí</p>
                          <p className="mt-2 text-sm font-semibold leading-6 text-[#6f6254]">{hasActiveFilters ? 'Thử xóa bộ lọc hoặc đổi từ khóa tìm kiếm.' : 'Hãy tạo danh mục chi phí mới hoặc kiểm tra lại dữ liệu hiện tại.'}</p>
                          {hasActiveFilters && (
                            <button type="button" onClick={clearFilters} className="mt-4 inline-flex h-10 items-center justify-center rounded-2xl bg-[#24170d] px-4 text-xs font-black text-[#fff4df] transition hover:bg-[#3d2a18]">
                              Xóa bộ lọc
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </section>

          {isFormOpen && isSuperAdmin && (
            <ExpenseCategoryModal
              isOpen={isFormOpen}
              onClose={handleCloseForm}
              editingExpenseCategoryId={editingExpenseCategoryId}
              form={form}
              setForm={setForm}
              onCancel={handleCancelForm}
              onSubmitSuccess={handleSubmitSuccess}
            />
          )}
        </div>
      </section>

      {isDetailOpen && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="expense-category-detail-title">
          <button type="button" aria-label="Đóng chi tiết danh mục chi phí" onClick={closeExpenseCategoryDetail} className="absolute inset-0 bg-stone-950/65 backdrop-blur-sm" />
          <div className="relative z-10 w-full max-w-3xl overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] shadow-2xl shadow-stone-950/30">
            <div className="bg-[#24170d] p-5 text-[#fff4df]">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <p className="text-[10px] font-black uppercase tracking-[0.24em] text-[#f3c56b]">Expense category detail</p>
                  <h2 id="expense-category-detail-title" className="mt-2 text-2xl font-black tracking-tight">{detailExpenseCategory?.name || 'Đang tải chi tiết...'}</h2>
                  <p className="mt-1 text-xs font-black uppercase tracking-[0.18em] text-[#f8e8c8]/72">{detailExpenseCategory?.status_label || (detailExpenseCategory?.is_active ? 'Đang sử dụng' : 'Hết sử dụng')}</p>
                </div>
                <button type="button" onClick={closeExpenseCategoryDetail} className="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/10 text-white transition hover:bg-white/20" aria-label="Đóng chi tiết danh mục chi phí">
                  <X className="h-5 w-5" />
                </button>
              </div>
            </div>

            <div className="space-y-4 p-5">
              {isDetailLoading && <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-black text-amber-800">Đang tải chi tiết danh mục chi phí...</div>}
              {detailErrorMessage && <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-black text-rose-700">{detailErrorMessage}</div>}

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <DetailTile label="Trạng thái" value={detailExpenseCategory?.status_label || (detailExpenseCategory?.is_active ? 'Đang sử dụng' : 'Hết sử dụng')} />
                <DetailTile label="Phiếu chi liên kết" value={detailExpenseCategory?.expenses_count ?? 0} />
                <DetailTile label="Người tạo" value={detailExpenseCategory?.creator_name || detailExpenseCategory?.creator?.full_name || 'Hệ thống'} />
              </div>

              <section className="rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/60 p-4">
                <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/60">Mô tả</p>
                <p className="mt-3 whitespace-pre-wrap text-sm font-semibold leading-6 text-[#3d2a18]">{detailExpenseCategory?.description || 'Chưa có mô tả cho danh mục này.'}</p>
              </section>

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <DetailTile label="Ngày tạo" value={formatDateTime(detailExpenseCategory?.created_at)} />
                <DetailTile label="Ngày cập nhật" value={formatDateTime(detailExpenseCategory?.updated_at)} />
              </div>
            </div>
          </div>
        </div>
      )}
    </>
  )
}

function MetricCard({ icon, label, value, tone }: { icon: ReactNode; label: string; value: number; tone: 'neutral' | 'emerald' | 'amber' | 'teal' }) {
  const toneClassNames = {
    neutral: 'border-[#f8e8c8]/12 bg-[#f8e8c8]/10 text-[#fff4df]',
    emerald: 'border-[#0f766e]/35 bg-[#0f766e]/16 text-[#c8fff4]',
    amber: 'border-[#f3c56b]/35 bg-[#f3c56b]/18 text-[#fff4df]',
    teal: 'border-cyan-200/25 bg-cyan-100/10 text-cyan-50',
  }[tone]

  return (
    <div className={cn('flex h-full min-h-[6.75rem] min-w-0 flex-col rounded-[1.45rem] border p-4 shadow-lg shadow-black/5 backdrop-blur-sm transition duration-200 hover:-translate-y-0.5 hover:bg-white/12', toneClassNames)}>
      <div className="flex min-w-0 items-center gap-3">
        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-white/12 text-[#f3c56b]">{icon}</div>
        <p className="min-w-0 whitespace-nowrap text-[10px] font-black uppercase leading-none tracking-[0.14em] opacity-75">{label}</p>
      </div>
      <p className="mt-5 min-w-0 whitespace-nowrap text-[clamp(1.35rem,1.5vw,1.6rem)] font-black leading-none tracking-[-0.04em] tabular-nums">{value}</p>
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
