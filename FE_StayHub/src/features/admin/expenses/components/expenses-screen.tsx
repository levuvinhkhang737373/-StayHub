import { useCallback, useEffect, useMemo, useState, Fragment } from 'react'
import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { ArrowLeft, Banknote, Building2, CalendarDays, DoorOpen, Edit3, Eye, ImageIcon, Plus, ReceiptText, Search, Trash2, UploadCloud, WalletCards, X, ChevronLeft, ChevronRight } from 'lucide-react'
import { AdminSelect } from '../../shared/components/AdminSelect'
import type { AdminSelectOption } from '../../shared/components/AdminSelect'
import { formatCurrency, formatDate } from '../../../../shared/lib/utils/format'
import { cn } from '../../../../shared/lib/utils/cn'
import { AdminDateInput } from '../../../../shared/components/AdminDateInput'
import { fetchAdminBuildings } from '../../facilities/services/facilities.service'
import { fetchAdminRooms } from '../../rooms/services/rooms.service'
import { fetchAdminExpenseCategories } from '../../expense-categories/services/expense-categories.service'
import type { AdminBuildingResource } from '../../facilities/types/facility-api.model'
import type { AdminRoomResource } from '../../rooms/types/rooms.model'
import type { AdminExpenseCategoryResource } from '../../expense-categories/types/expense-category-api.model'
import { cancelAdminExpense, createAdminExpense, fetchAdminExpenseDetail, fetchAdminExpenses, updateAdminExpense } from '../services/expenses.service'
import type { AdminExpensePaginationMeta, AdminExpensePayload, AdminExpenseResource } from '../types/expense-api.model'

interface ExpenseFormValues {
  building_id: string
  room_id: string
  expense_category_id: string
  title: string
  amount: string
  expense_date: string
  payment_method: string
  note: string
  receipt_images: File[]
  deleted_receipt_images: string[]
}

const inputClass = 'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20 disabled:cursor-not-allowed disabled:opacity-60'
const labelClass = 'mb-1.5 block text-[11px] font-black uppercase tracking-[0.18em] text-[#8b5e34]'

const expenseStatusOptions: AdminSelectOption[] = [
  { value: '', label: 'Tất cả trạng thái', tone: 'default' },
  { value: '1', label: 'Đã ghi nhận', tone: 'success' },
  { value: '2', label: 'Đã hủy', tone: 'danger' },
]

const paymentMethodOptions: AdminSelectOption[] = [
  { value: '', label: 'Tất cả phương thức', tone: 'default' },
  { value: '1', label: 'Tiền mặt', tone: 'warning' },
  { value: '2', label: 'Chuyển khoản', tone: 'success' },
]

const formPaymentMethodOptions: AdminSelectOption[] = paymentMethodOptions.filter((option) => option.value !== '')
const perPageOptions: AdminSelectOption[] = [10, 20, 50, 100].map((value) => ({ value, label: `${value} dòng`, tone: 'default' as const }))

function todayString() {
  return new Date().toISOString().slice(0, 10)
}

function defaultForm(buildingId = ''): ExpenseFormValues {
  return {
    building_id: buildingId,
    room_id: '',
    expense_category_id: '',
    title: '',
    amount: '',
    expense_date: todayString(),
    payment_method: '1',
    note: '',
    receipt_images: [],
    deleted_receipt_images: [],
  }
}

function getMonthRange(monthValue: string, edge: 'start' | 'end') {
  if (!/^\d{4}-\d{2}$/.test(monthValue)) return ''

  const [yearValue, monthValueText] = monthValue.split('-')
  const year = Number(yearValue)
  const month = Number(monthValueText)
  if (!year || month < 1 || month > 12) return ''

  if (edge === 'start') return `${yearValue}-${monthValueText}-01`

  const lastDay = new Date(year, month, 0).getDate()
  return `${yearValue}-${monthValueText}-${String(lastDay).padStart(2, '0')}`
}

function getResourceList<T>(result: { data?: T[] } | T[] | null | undefined): T[] {
  if (!result) return []
  if (Array.isArray(result)) return result
  return result.data || []
}

function normalizeExpenseResult(result: { data?: AdminExpenseResource[]; pagination?: AdminExpensePaginationMeta | null; meta?: AdminExpensePaginationMeta | null } | AdminExpenseResource[] | null | undefined) {
  if (!result) return { data: [], meta: null }
  if (Array.isArray(result)) return { data: result, meta: null }
  return { data: result.data || [], meta: result.pagination || result.meta || null }
}

function getExpenseBuildingName(expense: AdminExpenseResource) {
  return expense.building?.name || expense.building_name || `Tòa #${expense.building_id}`
}

function getExpenseRoomName(expense: AdminExpenseResource) {
  if (!expense.room_id) return 'Chi phí chung'
  return expense.room?.room_number || expense.room_number || `Phòng #${expense.room_id}`
}

function getExpenseCategoryName(expense: AdminExpenseResource) {
  return expense.category?.name || expense.category_name || 'Chưa phân loại'
}

export function ExpensesScreen() {
  const [expenses, setExpenses] = useState<AdminExpenseResource[]>([])
  const [buildings, setBuildings] = useState<AdminBuildingResource[]>([])
  const [rooms, setRooms] = useState<AdminRoomResource[]>([])
  const [categories, setCategories] = useState<AdminExpenseCategoryResource[]>([])
  const [keyword, setKeyword] = useState('')
  const [selectedBuildingId, setSelectedBuildingId] = useState('')
  const [selectedRoomId, setSelectedRoomId] = useState('')
  const [selectedCategoryId, setSelectedCategoryId] = useState('')
  const [selectedStatus, setSelectedStatus] = useState('')
  const [selectedPaymentMethod, setSelectedPaymentMethod] = useState('')
  const [expenseDateExact, setExpenseDateExact] = useState('')
  const [expenseMonthFrom, setExpenseMonthFrom] = useState('')
  const [expenseMonthTo, setExpenseMonthTo] = useState('')
  const [currentPage, setCurrentPage] = useState(1)
  const [perPage, setPerPage] = useState(10)
  const [paginationMeta, setPaginationMeta] = useState<AdminExpensePaginationMeta | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [editingExpense, setEditingExpense] = useState<AdminExpenseResource | null>(null)
  const [form, setForm] = useState<ExpenseFormValues>(() => defaultForm())
  const [formErrors, setFormErrors] = useState<Record<string, string>>({})
  const [detailExpense, setDetailExpense] = useState<AdminExpenseResource | null>(null)
  const [isDetailOpen, setIsDetailOpen] = useState(false)
  const [isDetailLoading, setIsDetailLoading] = useState(false)
  const [detailErrorMessage, setDetailErrorMessage] = useState<string | null>(null)
  const [cancellingId, setCancellingId] = useState<number | null>(null)

  const buildingOptions = useMemo<AdminSelectOption[]>(() => [
    { value: '', label: 'Tất cả tòa nhà', tone: 'default' },
    ...buildings.map((building) => ({ value: building.id, label: building.name, description: building.address || undefined, tone: 'default' as const })),
  ], [buildings])

  const formBuildingOptions = useMemo<AdminSelectOption[]>(() => buildings.map((building) => ({ value: building.id, label: building.name, description: building.address || undefined, tone: 'default' as const })), [buildings])

  const visibleRooms = useMemo(() => rooms.filter((room) => !selectedBuildingId || Number(room.building_id) === Number(selectedBuildingId)), [rooms, selectedBuildingId])
  const formRooms = useMemo(() => rooms.filter((room) => !form.building_id || Number(room.building_id) === Number(form.building_id)), [form.building_id, rooms])

  const roomOptions = useMemo<AdminSelectOption[]>(() => [
    { value: '', label: 'Tất cả phòng', tone: 'default' },
    ...visibleRooms.map((room) => ({ value: room.id, label: `Phòng ${room.room_number}`, description: room.building_name || room.building?.name, tone: 'default' as const })),
  ], [visibleRooms])

  const formRoomOptions = useMemo<AdminSelectOption[]>(() => [
    { value: '', label: 'Chi phí chung của tòa', tone: 'warning' },
    ...formRooms.map((room) => ({ value: room.id, label: `Phòng ${room.room_number}`, description: room.building_name || room.building?.name, tone: 'default' as const })),
  ], [formRooms])

  const categoryOptions = useMemo<AdminSelectOption[]>(() => [
    { value: '', label: 'Tất cả danh mục', tone: 'default' },
    ...categories.map((category) => ({ value: category.id, label: category.name, description: category.description || undefined, tone: category.is_active ? 'success' as const : 'danger' as const })),
  ], [categories])

  const formCategoryOptions = useMemo<AdminSelectOption[]>(() => [
    { value: '', label: 'Chưa phân loại', tone: 'default' },
    ...categories.filter((category) => category.is_active).map((category) => ({ value: category.id, label: category.name, description: category.description || undefined, tone: 'success' as const })),
  ], [categories])

  const recordedExpenses = useMemo(() => expenses.filter((expense) => Number(expense.status) === 1), [expenses])
  const cancelledExpenses = useMemo(() => expenses.filter((expense) => Number(expense.status) === 2).length, [expenses])
  const recordedAmount = useMemo(() => recordedExpenses.reduce((total, expense) => total + Number(expense.amount || 0), 0), [recordedExpenses])
  const receiptCount = useMemo(() => expenses.reduce((total, expense) => total + (expense.receipt_images?.length || 0), 0), [expenses])

  const safeCurrentPage = Math.max(1, Math.min(currentPage, paginationMeta?.last_page ?? currentPage))
  const totalPages = Math.max(1, paginationMeta?.last_page ?? (expenses.length >= perPage ? currentPage + 1 : currentPage))
  const paginationStart = paginationMeta?.from ?? (expenses.length === 0 ? 0 : (safeCurrentPage - 1) * perPage + 1)
  const paginationEnd = paginationMeta?.to ?? (expenses.length === 0 ? 0 : (safeCurrentPage - 1) * perPage + expenses.length)
  const totalExpenses = paginationMeta?.total ?? (safeCurrentPage - 1) * perPage + expenses.length
  const visiblePages = useMemo(() => {
    const pages = new Set<number>([1, totalPages, safeCurrentPage - 1, safeCurrentPage, safeCurrentPage + 1])
    return Array.from(pages)
      .filter((page) => page >= 1 && page <= totalPages)
      .sort((a, b) => a - b)
  }, [safeCurrentPage, totalPages])
  const hasActiveFilters = Boolean(keyword || selectedBuildingId || selectedRoomId || selectedCategoryId || selectedStatus || selectedPaymentMethod || expenseDateExact || expenseMonthFrom || expenseMonthTo)
  const defaultBuildingId = buildings.length === 1 ? String(buildings[0].id) : ''

  const loadLookups = useCallback(async () => {
    try {
      const [buildingResponse, roomResponse, categoryResponse] = await Promise.all([
        fetchAdminBuildings({ per_page: 100 }),
        fetchAdminRooms({ per_page: 100 }),
        fetchAdminExpenseCategories({ is_active: true, per_page: 100 }),
      ])

      setBuildings(getResourceList<AdminBuildingResource>(buildingResponse.result))
      setRooms(getResourceList<AdminRoomResource>(roomResponse.result))
      setCategories(getResourceList<AdminExpenseCategoryResource>(categoryResponse.result))
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Không thể tải dữ liệu bộ lọc phiếu chi.')
    }
  }, [])

  const loadExpenses = useCallback(async () => {
    setIsLoading(true)
    setErrorMessage(null)

    try {
      const response = await fetchAdminExpenses({
        keyword: keyword.trim() || undefined,
        building_id: selectedBuildingId ? Number(selectedBuildingId) : undefined,
        room_id: selectedRoomId ? Number(selectedRoomId) : undefined,
        expense_category_id: selectedCategoryId ? Number(selectedCategoryId) : undefined,
        payment_method: selectedPaymentMethod ? Number(selectedPaymentMethod) : undefined,
        status: selectedStatus ? Number(selectedStatus) : undefined,
        expense_date_from: expenseDateExact || getMonthRange(expenseMonthFrom, 'start') || undefined,
        expense_date_to: expenseDateExact || getMonthRange(expenseMonthTo, 'end') || undefined,
        page: currentPage,
        per_page: perPage,
      })
      const normalized = normalizeExpenseResult(response.result)

      setExpenses(normalized.data)
      setPaginationMeta(normalized.meta)

      if (normalized.meta?.last_page && currentPage > normalized.meta.last_page) {
        setCurrentPage(normalized.meta.last_page)
      }
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Không thể tải danh sách phiếu chi.')
    } finally {
      setIsLoading(false)
    }
  }, [currentPage, expenseDateExact, expenseMonthFrom, expenseMonthTo, keyword, perPage, selectedBuildingId, selectedCategoryId, selectedPaymentMethod, selectedRoomId, selectedStatus])

  useEffect(() => {
    void loadLookups()
  }, [loadLookups])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadExpenses()
    }, 250)

    return () => window.clearTimeout(timer)
  }, [loadExpenses])

  useEffect(() => {
    if (selectedRoomId && selectedBuildingId) {
      const roomStillVisible = visibleRooms.some((room) => Number(room.id) === Number(selectedRoomId))
      if (!roomStillVisible) setSelectedRoomId('')
    }
  }, [selectedBuildingId, selectedRoomId, visibleRooms])

  const updateForm = (key: keyof ExpenseFormValues, value: string | File[] | string[]) => {
    setForm((current) => {
      const next = { ...current, [key]: value }
      if (key === 'building_id') next.room_id = ''
      return next
    })
    setFormErrors((current) => ({ ...current, [key]: '' }))
    setSuccessMessage(null)
  }

  const openCreateForm = () => {
    setEditingExpense(null)
    setForm(defaultForm(selectedBuildingId || defaultBuildingId))
    setFormErrors({})
    setErrorMessage(null)
    setSuccessMessage(null)
    setIsFormOpen(true)
  }

  const editExpense = (expense: AdminExpenseResource) => {
    setEditingExpense(expense)
    setForm({
      building_id: String(expense.building_id || ''),
      room_id: expense.room_id ? String(expense.room_id) : '',
      expense_category_id: expense.expense_category_id ? String(expense.expense_category_id) : '',
      title: expense.title || '',
      amount: expense.amount || '',
      expense_date: expense.expense_date || todayString(),
      payment_method: String(expense.payment_method || 1),
      note: expense.note || '',
      receipt_images: [],
      deleted_receipt_images: [],
    })
    setFormErrors({})
    setErrorMessage(null)
    setSuccessMessage(null)
    setIsFormOpen(true)
  }

  const viewExpense = async (expense: AdminExpenseResource) => {
    setDetailExpense(expense)
    setIsDetailOpen(true)
    setIsDetailLoading(true)
    setDetailErrorMessage(null)

    try {
      const response = await fetchAdminExpenseDetail(expense.id)
      setDetailExpense(response.result)
    } catch (error) {
      setDetailErrorMessage(error instanceof Error ? error.message : 'Không thể tải chi tiết phiếu chi.')
    } finally {
      setIsDetailLoading(false)
    }
  }

  const closeForm = () => {
    if (isSaving) return
    setIsFormOpen(false)
    setEditingExpense(null)
    setForm(defaultForm(defaultBuildingId))
    setFormErrors({})
  }

  const clearFilters = () => {
    setKeyword('')
    setSelectedBuildingId('')
    setSelectedRoomId('')
    setSelectedCategoryId('')
    setSelectedStatus('')
    setSelectedPaymentMethod('')
    setExpenseDateExact('')
    setExpenseMonthFrom('')
    setExpenseMonthTo('')
    setCurrentPage(1)
  }

  const validateForm = () => {
    const errors: Record<string, string> = {}
    const amount = Number(form.amount)

    if (!form.building_id) errors.building_id = 'Vui lòng chọn tòa nhà.'
    if (!form.title.trim()) errors.title = 'Vui lòng nhập tiêu đề phiếu chi.'
    if (!form.amount.trim() || Number.isNaN(amount) || amount <= 0) errors.amount = 'Số tiền VNĐ phải lớn hơn 0.'
    if (!/^\d+(\.\d{1,2})?$/.test(form.amount.trim())) errors.amount = 'Số tiền dùng decimal, tối đa 2 chữ số thập phân.'
    if (!form.expense_date) errors.expense_date = 'Vui lòng chọn ngày chi.'
    if (form.receipt_images.length + ((editingExpense?.receipt_images || []).length - form.deleted_receipt_images.length) > 10) errors.receipt_images = 'Tối đa 10 ảnh chứng từ cho một phiếu chi.'

    return errors
  }

  const submitForm = async () => {
    if (isSaving) return

    const errors = validateForm()
    setFormErrors(errors)
    if (Object.keys(errors).length > 0) return

    const payload: AdminExpensePayload = {
      building_id: Number(form.building_id),
      room_id: form.room_id ? Number(form.room_id) : null,
      expense_category_id: form.expense_category_id ? Number(form.expense_category_id) : null,
      title: form.title.trim(),
      amount: form.amount.trim(),
      expense_date: form.expense_date,
      payment_method: Number(form.payment_method || 1),
      note: form.note.trim() || null,
      receipt_images: form.receipt_images,
      deleted_receipt_images: form.deleted_receipt_images,
    }

    try {
      setIsSaving(true)
      setErrorMessage(null)
      setSuccessMessage(null)

      if (editingExpense) {
        await updateAdminExpense(editingExpense.id, payload)
        setSuccessMessage('Cập nhật phiếu chi thành công.')
      } else {
        await createAdminExpense(payload)
        setSuccessMessage('Tạo phiếu chi thành công.')
      }

      setIsFormOpen(false)
      setEditingExpense(null)
      setForm(defaultForm(defaultBuildingId))
      await loadExpenses()
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Không thể lưu phiếu chi.')
    } finally {
      setIsSaving(false)
    }
  }

  const cancelExpense = async (expense: AdminExpenseResource) => {
    if (Number(expense.status) === 2) return
    if (!window.confirm(`Bạn có chắc muốn hủy phiếu chi ${expense.expense_code}? Dữ liệu sẽ được giữ lại để đối soát.`)) return

    try {
      setCancellingId(expense.id)
      setErrorMessage(null)
      setSuccessMessage(null)
      await cancelAdminExpense(expense.id)
      setSuccessMessage('Hủy phiếu chi thành công.')
      await loadExpenses()
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Không thể hủy phiếu chi.')
    } finally {
      setCancellingId(null)
    }
  }

  const changePage = (nextPage: number) => {
    setCurrentPage(Math.max(1, Math.min(nextPage, totalPages)))
  }

  const changePerPage = (nextValue: string | number) => {
    setPerPage(Number(nextValue))
    setCurrentPage(1)
  }

  const activeExistingImages = (editingExpense?.receipt_images || []).filter((path) => !form.deleted_receipt_images.includes(path))

  return (
    <>
      <section className="space-y-5 text-[#24170d] sm:space-y-6">
        <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
          <div className="relative p-4 text-[#fff4df] sm:p-6 xl:p-7">
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_16%_12%,rgba(243,197,107,0.30),transparent_31%),radial-gradient(circle_at_78%_10%,rgba(15,118,110,0.30),transparent_34%),linear-gradient(135deg,#24170d_0%,#4a2b14_48%,#0f3f3b_100%)]" />
            <div className="pointer-events-none absolute inset-x-6 bottom-0 h-px bg-gradient-to-r from-transparent via-[#f3c56b]/45 to-transparent" />

            <div className="relative flex min-w-0 flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
              <div className="min-w-0">
                <Link to="/admin/dashboard" className="mb-2 inline-flex items-center gap-2 text-xs font-black text-[#f3c56b] transition hover:text-[#ffd56f]">
                  <ArrowLeft className="h-3.5 w-3.5" /> Về dashboard
                </Link>
                <h1 className="mt-3 max-w-3xl text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl lg:text-[2.65rem]">Phiếu chi</h1>
              </div>
              <button type="button" onClick={openCreateForm} className="inline-flex h-11 shrink-0 items-center justify-center gap-2 self-start whitespace-nowrap rounded-xl bg-[#f3c56b] px-5 text-sm font-black text-[#24170d] shadow-xl shadow-[#a65f16]/20 transition-all hover:bg-[#ffd56f] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/35 active:scale-[0.98] lg:self-center">
                <Plus className="h-4 w-4 stroke-[2.8]" /> Lập phiếu chi
              </button>
            </div>

            <div className="relative mt-6 grid grid-cols-[repeat(auto-fit,minmax(14rem,1fr))] gap-3">
              <MetricCard icon={<ReceiptText className="h-4 w-4" />} label="Phiếu hiển thị" value={totalExpenses} />
              <MetricCard icon={<Banknote className="h-4 w-4" />} label="Đã ghi nhận" value={formatCurrency(recordedAmount)} tone="emerald" />
              <MetricCard icon={<Trash2 className="h-4 w-4" />} label="Đã hủy" value={cancelledExpenses} tone="rose" />
              <MetricCard icon={<ImageIcon className="h-4 w-4" />} label="Ảnh chứng từ" value={receiptCount} tone="amber" />
            </div>
          </div>
        </section>

        {(errorMessage || successMessage) && (
          <div className={cn('rounded-3xl border px-4 py-3 text-sm font-black shadow-sm', errorMessage ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700')}>
            {errorMessage || successMessage}
          </div>
        )}

        <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
          <div className="border-b border-[#3d2a18]/10 bg-[#fff8eb]/85 p-4 sm:p-5">
            <div className="grid gap-3 lg:grid-cols-[minmax(12rem,1.15fr)_repeat(4,minmax(100px,0.75fr))]">
              <div className="relative min-w-0">
                <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#a65f16]" />
                <input type="text" value={keyword} onChange={(event) => { setKeyword(event.target.value); setCurrentPage(1) }} placeholder="Tìm mã, tiêu đề, ghi chú, tòa/phòng..." className={`${inputClass} pl-11 pr-28`} />
                <button type="button" onClick={clearFilters} disabled={!hasActiveFilters} className="absolute right-2 top-1/2 inline-flex h-9 -translate-y-1/2 items-center justify-center gap-1.5 rounded-xl px-3 text-xs font-black text-[#8b5e34] transition hover:bg-[#f3c56b]/16 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20 disabled:cursor-not-allowed disabled:opacity-45">
                  <X className="h-3.5 w-3.5" /> Xóa lọc
                </button>
              </div>
              <AdminSelect value={selectedBuildingId} options={buildingOptions} onChange={(value) => { setSelectedBuildingId(String(value)); setCurrentPage(1) }} />
              <AdminSelect value={selectedRoomId} options={roomOptions} onChange={(value) => { setSelectedRoomId(String(value)); setCurrentPage(1) }} />
              <AdminSelect value={selectedCategoryId} options={categoryOptions} onChange={(value) => { setSelectedCategoryId(String(value)); setCurrentPage(1) }} />
              <AdminSelect value={selectedStatus} options={expenseStatusOptions} onChange={(value) => { setSelectedStatus(String(value)); setCurrentPage(1) }} />
            </div>
            <div className="mt-3 grid gap-3 lg:grid-cols-[minmax(100px,14rem)_minmax(100px,14rem)_minmax(100px,14rem)_minmax(100px,14rem)_1fr]">
              <AdminSelect value={selectedPaymentMethod} options={paymentMethodOptions} onChange={(value) => { setSelectedPaymentMethod(String(value)); setCurrentPage(1) }} />
              <AdminDateInput value={expenseDateExact} onChange={(value) => { setExpenseDateExact(value); setCurrentPage(1) }} placeholder="Ngày cụ thể" className={inputClass} />
              <AdminDateInput mode="month" value={expenseMonthFrom} onChange={(value) => { setExpenseMonthFrom(value); setCurrentPage(1) }} placeholder="Từ tháng/năm" className={inputClass} />
              <AdminDateInput mode="month" value={expenseMonthTo} onChange={(value) => { setExpenseMonthTo(value); setCurrentPage(1) }} placeholder="Đến tháng/năm" className={inputClass} />
            </div>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full min-w-[1080px] text-left text-sm">
              <thead className="bg-[#24170d] text-xs font-black uppercase tracking-[0.14em] text-[#fff4df]">
                <tr>
                  <th className="px-4 py-3">Mã phiếu</th>
                  <th className="px-4 py-3">Khoản chi</th>
                  <th className="px-4 py-3">Tòa / phòng</th>
                  <th className="px-4 py-3">Danh mục</th>
                  <th className="px-4 py-3 text-right">Số tiền</th>
                  <th className="px-4 py-3">Ngày chi</th>
                  <th className="px-4 py-3">Trạng thái</th>
                  <th className="px-4 py-3 w-[120px]"><div className="flex justify-end"><div className="w-[120px] text-center">Thao tác</div></div></th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[#3d2a18]/8 bg-white/45">
                {isLoading && <tr><td colSpan={8} className="px-4 py-10 text-center text-sm font-black text-[#8b5e34]">Đang tải danh sách phiếu chi...</td></tr>}
                {!isLoading && expenses.length === 0 && <tr><td colSpan={8} className="px-4 py-10 text-center text-sm font-black text-[#8b5e34]">Chưa có phiếu chi phù hợp.</td></tr>}
                {!isLoading && expenses.map((expense) => (
                  <tr key={expense.id} className="align-top transition hover:bg-[#fff8eb]">
                    <td className="px-4 py-4"><p className="font-black text-[#24170d]">{expense.expense_code}</p><p className="mt-1 text-xs font-bold text-[#8b5e34]">{expense.payment_method_label || '—'}</p></td>
                    <td className="px-4 py-4"><p className="font-black text-[#24170d]">{expense.title}</p><p className="mt-1 line-clamp-1 text-xs font-bold text-[#8b5e34]/75">{expense.note || 'Không có ghi chú'}</p></td>
                    <td className="px-4 py-4"><p className="font-black text-[#24170d]">{getExpenseBuildingName(expense)}</p><p className="mt-1 text-xs font-bold text-[#8b5e34]">{getExpenseRoomName(expense)}</p></td>
                    <td className="px-4 py-4 text-xs font-black text-[#8b5e34]">{getExpenseCategoryName(expense)}</td>
                    <td className="px-4 py-4 text-right font-black tabular-nums text-[#0f5f59]">{expense.amount_formatted || formatCurrency(expense.amount)}</td>
                    <td className="px-4 py-4 text-xs font-black text-[#6f6254]">{formatDate(expense.expense_date)}</td>
                    <td className="px-4 py-4"><StatusBadge status={expense.status} label={expense.status_label} /></td>
                    <td className="px-4 py-4">
                      <div className="flex justify-end gap-2">
                        <IconButton title="Xem chi tiết" onClick={() => void viewExpense(expense)}><Eye className="h-4 w-4" /></IconButton>
                        <IconButton title="Sửa phiếu" disabled={Number(expense.status) === 2} onClick={() => editExpense(expense)}><Edit3 className="h-4 w-4" /></IconButton>
                        <IconButton title="Hủy phiếu" danger disabled={Number(expense.status) === 2 || cancellingId === expense.id} onClick={() => void cancelExpense(expense)}><Trash2 className="h-4 w-4" /></IconButton>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="flex flex-col gap-3 border-t border-[#3d2a18]/10 bg-[#fff8eb]/85 px-4 py-4 lg:flex-row lg:items-center lg:justify-between">
            <p className="text-xs font-black text-[#6f6254]">Hiển thị <span className="tabular-nums text-[#24170d]">{paginationStart}</span>-<span className="tabular-nums text-[#24170d]">{paginationEnd}</span> / <span className="tabular-nums text-[#24170d]">{totalExpenses}</span> phiếu chi</p>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
              <div className="w-full sm:w-36">
                <AdminSelect value={perPage} options={perPageOptions} onChange={changePerPage} menuPlacement="top" />
              </div>
              <div className="flex items-center justify-end gap-1.5">
                <button type="button" disabled={safeCurrentPage <= 1} onClick={() => changePage(currentPage - 1)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:cursor-not-allowed disabled:opacity-45" aria-label="Trang trước">
                  <ChevronLeft className="h-4 w-4" />
                </button>
                {visiblePages.map((page, index) => {
                  const previousPage = visiblePages[index - 1]
                  const hasGap = previousPage && page - previousPage > 1

                  return (
                    <Fragment key={page}>
                      {hasGap && <span className="px-1 text-xs font-black text-[#8b5e34]/60">...</span>}
                      <button type="button" onClick={() => changePage(page)} className={cn('inline-flex h-9 min-w-9 items-center justify-center rounded-xl border px-3 text-xs font-black transition', page === safeCurrentPage ? 'border-[#24170d] bg-[#24170d] text-[#fff4df] shadow-sm' : 'border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] hover:bg-[#f3c56b]/15')}>
                        {page}
                      </button>
                    </Fragment>
                  )
                })}
                <button type="button" disabled={safeCurrentPage >= totalPages} onClick={() => changePage(currentPage + 1)} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 disabled:cursor-not-allowed disabled:opacity-45" aria-label="Trang sau">
                  <ChevronRight className="h-4 w-4" />
                </button>
              </div>
            </div>
          </div>
        </section>
      </section>

      {isFormOpen && (
        <ModalFrame title={editingExpense ? 'Cập nhật phiếu chi' : 'Lập phiếu chi mới'} onClose={closeForm} wide>
          <div className="grid gap-4 lg:grid-cols-[1fr_0.78fr]">
            <div className="space-y-4">
              <div className="grid gap-3 sm:grid-cols-2">
                <FormField label="Tòa nhà" error={formErrors.building_id}><AdminSelect value={form.building_id} options={formBuildingOptions} onChange={(value) => updateForm('building_id', String(value))} invalid={!!formErrors.building_id} /></FormField>
                <FormField label="Phòng"><AdminSelect value={form.room_id} options={formRoomOptions} onChange={(value) => updateForm('room_id', String(value))} /></FormField>
              </div>
              <FormField label="Tiêu đề" error={formErrors.title}><input value={form.title} onChange={(event) => updateForm('title', event.target.value)} className={inputClass} placeholder="VD: Sửa máy lạnh phòng A101" /></FormField>
              <div className="grid gap-3 sm:grid-cols-2">
                <FormField label="Số tiền VNĐ" error={formErrors.amount}><input value={form.amount} onChange={(event) => updateForm('amount', event.target.value)} inputMode="decimal" className={inputClass} placeholder="650000.00" /></FormField>
                <FormField label="Ngày chi" error={formErrors.expense_date}><AdminDateInput value={form.expense_date} onChange={(value) => updateForm('expense_date', value)} className={inputClass} /></FormField>
              </div>
              <div className="grid gap-3 sm:grid-cols-2">
                <FormField label="Danh mục"><AdminSelect value={form.expense_category_id} options={formCategoryOptions} onChange={(value) => updateForm('expense_category_id', String(value))} /></FormField>
                <FormField label="Phương thức"><AdminSelect value={form.payment_method} options={formPaymentMethodOptions} onChange={(value) => updateForm('payment_method', String(value))} /></FormField>
              </div>
              <FormField label="Ghi chú"><textarea value={form.note} onChange={(event) => updateForm('note', event.target.value)} className={`${inputClass} min-h-28 resize-y`} placeholder="Ghi chú nội bộ, nhà cung cấp, lý do phát sinh..." /></FormField>
            </div>

            <div className="space-y-4 rounded-[1.5rem] border border-[#3d2a18]/10 bg-white/55 p-4">
              <div>
                <p className={labelClass}>Chứng từ</p>
                <label className="flex cursor-pointer flex-col items-center justify-center rounded-[1.35rem] border border-dashed border-[#a65f16]/35 bg-[#fffaf1] p-5 text-center transition hover:bg-[#fff8eb]">
                  <UploadCloud className="mb-2 h-7 w-7 text-[#a65f16]" />
                  <span className="text-sm font-black text-[#24170d]">Tải ảnh chứng từ</span>
                  <span className="mt-1 text-xs font-bold text-[#8b5e34]">JPG, PNG, WEBP · tối đa 10 ảnh</span>
                  <input type="file" multiple accept="image/jpeg,image/png,image/webp" className="hidden" onChange={(event) => updateForm('receipt_images', Array.from(event.currentTarget.files || []))} />
                </label>
                <FieldError message={formErrors.receipt_images} />
              </div>

              {activeExistingImages.length > 0 && <ImageList title="Ảnh hiện có" paths={activeExistingImages} urls={editingExpense?.receipt_image_urls || []} onDelete={(path) => updateForm('deleted_receipt_images', [...form.deleted_receipt_images, path])} />}
              {form.receipt_images.length > 0 && <FileList files={form.receipt_images} onDelete={(index) => updateForm('receipt_images', form.receipt_images.filter((_, fileIndex) => fileIndex !== index))} />}

              <div className="rounded-2xl bg-[#24170d] p-4 text-[#fff4df]">
                <p className="text-xs font-black uppercase tracking-[0.18em] text-[#f3c56b]">Tạm tính</p>
                <p className="mt-2 text-2xl font-black tabular-nums">{formatCurrency(form.amount || 0)}</p>
              </div>
            </div>
          </div>

          <div className="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-end">
            <button type="button" onClick={closeForm} disabled={isSaving} className="h-11 rounded-xl border border-[#3d2a18]/10 px-5 text-sm font-black text-[#6f6254] transition hover:bg-[#efe2cf] disabled:opacity-60">Hủy</button>
            <button type="button" onClick={() => void submitForm()} disabled={isSaving} className="h-11 rounded-xl bg-[#24170d] px-5 text-sm font-black text-[#fff4df] shadow-md transition hover:bg-[#3d2a18] disabled:opacity-60">{isSaving ? 'Đang lưu...' : 'Lưu phiếu chi'}</button>
          </div>
        </ModalFrame>
      )}

      {isDetailOpen && detailExpense && (
        <ModalFrame title={`Chi tiết ${detailExpense.expense_code}`} onClose={() => { setIsDetailOpen(false); setDetailExpense(null); setDetailErrorMessage(null) }} wide>
          {isDetailLoading && <p className="py-8 text-center text-sm font-black text-[#8b5e34]">Đang tải chi tiết...</p>}
          {detailErrorMessage && <div className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-black text-rose-700">{detailErrorMessage}</div>}
          {!isDetailLoading && !detailErrorMessage && (
            <div className="space-y-4">
              <div className="grid gap-3 md:grid-cols-4">
                <DetailTile icon={<Building2 className="h-4 w-4" />} label="Tòa nhà" value={getExpenseBuildingName(detailExpense)} />
                <DetailTile icon={<DoorOpen className="h-4 w-4" />} label="Phòng" value={getExpenseRoomName(detailExpense)} />
                <DetailTile icon={<CalendarDays className="h-4 w-4" />} label="Ngày chi" value={formatDate(detailExpense.expense_date)} />
                <DetailTile icon={<WalletCards className="h-4 w-4" />} label="Số tiền" value={detailExpense.amount_formatted || formatCurrency(detailExpense.amount)} />
              </div>
              <div className="rounded-3xl border border-[#3d2a18]/10 bg-white/65 p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                  <div>
                    <p className="text-xs font-black uppercase tracking-[0.18em] text-[#8b5e34]">{getExpenseCategoryName(detailExpense)}</p>
                    <h3 className="mt-1 text-xl font-black text-[#24170d]">{detailExpense.title}</h3>
                    <p className="mt-2 text-sm font-bold leading-6 text-[#6f6254]">{detailExpense.note || 'Không có ghi chú.'}</p>
                  </div>
                  <StatusBadge status={detailExpense.status} label={detailExpense.status_label} />
                </div>
              </div>
              <div>
                <p className={labelClass}>Ảnh chứng từ</p>
                {(detailExpense.receipt_image_urls || []).length === 0 && <p className="rounded-2xl bg-[#fff8eb] p-4 text-sm font-bold text-[#8b5e34]">Chưa có ảnh chứng từ.</p>}
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                  {(detailExpense.receipt_image_urls || []).map((url, index) => (
                    <a key={`${url}-${index}`} href={url} target="_blank" rel="noreferrer" className="group overflow-hidden rounded-2xl border border-[#3d2a18]/10 bg-white shadow-sm">
                      <img src={url} alt={`Chứng từ ${index + 1}`} className="h-36 w-full object-cover transition duration-300 group-hover:scale-105" />
                    </a>
                  ))}
                </div>
              </div>
            </div>
          )}
        </ModalFrame>
      )}
    </>
  )
}

function MetricCard({ icon, label, value, tone = 'neutral' }: { icon: ReactNode; label: string; value: ReactNode; tone?: 'neutral' | 'emerald' | 'rose' | 'amber' }) {
  const toneClasses = {
    neutral: 'bg-white/10 text-[#fff4df]',
    emerald: 'bg-emerald-300/12 text-emerald-100',
    rose: 'bg-rose-300/12 text-rose-100',
    amber: 'bg-[#f3c56b]/14 text-[#ffe2a3]',
  }

  return (
    <div className={cn('flex h-full min-h-[6.75rem] min-w-0 flex-col rounded-[1.45rem] border border-white/12 p-4 shadow-lg shadow-black/5 backdrop-blur-sm transition duration-200 hover:-translate-y-0.5 hover:bg-white/12', toneClasses[tone])}>
      <div className="flex min-w-0 items-center gap-3">
        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-white/12 text-[#f3c56b]">{icon}</div>
        <p className="min-w-0 whitespace-nowrap text-[10px] font-black uppercase leading-none tracking-[0.14em] opacity-75">{label}</p>
      </div>
      <p className="mt-5 min-w-0 whitespace-nowrap text-[clamp(1.35rem,1.5vw,1.6rem)] font-black leading-none tabular-nums tracking-[-0.04em]">{value}</p>
    </div>
  )
}

function StatusBadge({ status, label }: { status: number; label?: string | null }) {
  const isCancelled = Number(status) === 2
  return <span className={cn('inline-flex rounded-full px-3 py-1 text-xs font-black', isCancelled ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700')}>{label || (isCancelled ? 'Đã hủy' : 'Đã ghi nhận')}</span>
}

function IconButton({ children, onClick, title, danger, disabled }: { children: ReactNode; onClick: () => void; title: string; danger?: boolean; disabled?: boolean }) {
  return <button type="button" title={title} onClick={onClick} disabled={disabled} className={cn('inline-flex h-9 w-9 items-center justify-center rounded-xl border text-sm transition focus:outline-none focus:ring-4 disabled:cursor-not-allowed disabled:opacity-45', danger ? 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 focus:ring-rose-100' : 'border-[#3d2a18]/10 bg-[#fffaf1] text-[#6f6254] hover:bg-[#efe2cf] focus:ring-[#f3c56b]/20')}>{children}</button>
}

function FormField({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
  return <div><label className={labelClass}>{label}</label>{children}<FieldError message={error} /></div>
}

function FieldError({ message }: { message?: string }) {
  if (!message) return null
  return <p className="mt-1 text-xs font-black text-rose-600">{message}</p>
}

function ImageList({ title, paths, urls, onDelete }: { title: string; paths: string[]; urls: string[]; onDelete: (path: string) => void }) {
  return (
    <div>
      <p className={labelClass}>{title}</p>
      <div className="grid grid-cols-2 gap-2">
        {paths.map((path, index) => (
          <div key={path} className="group relative overflow-hidden rounded-2xl border border-[#3d2a18]/10 bg-white">
            <img src={urls[index] || path} alt={`Chứng từ ${index + 1}`} className="h-28 w-full object-cover" />
            <button type="button" onClick={() => onDelete(path)} className="absolute right-2 top-2 inline-flex h-8 w-8 items-center justify-center rounded-xl bg-rose-600 text-white opacity-0 shadow-lg transition group-hover:opacity-100"><X className="h-4 w-4" /></button>
          </div>
        ))}
      </div>
    </div>
  )
}

function FileList({ files, onDelete }: { files: File[]; onDelete: (index: number) => void }) {
  return (
    <div>
      <p className={labelClass}>Ảnh mới</p>
      <div className="space-y-2">
        {files.map((file, index) => (
          <div key={`${file.name}-${index}`} className="flex items-center justify-between gap-3 rounded-2xl bg-[#fff8eb] px-3 py-2 text-xs font-black text-[#6f6254]">
            <span className="line-clamp-1">{file.name}</span>
            <button type="button" onClick={() => onDelete(index)} className="text-rose-600"><X className="h-4 w-4" /></button>
          </div>
        ))}
      </div>
    </div>
  )
}

function DetailTile({ icon, label, value }: { icon: ReactNode; label: string; value: ReactNode }) {
  return <div className="rounded-2xl border border-[#3d2a18]/10 bg-[#fff8eb] p-4"><div className="mb-2 flex items-center gap-2 text-[#a65f16]">{icon}<span className="text-[11px] font-black uppercase tracking-[0.18em]">{label}</span></div><p className="text-sm font-black text-[#24170d]">{value}</p></div>
}

function ModalFrame({ title, onClose, children, wide }: { title: string; onClose: () => void; children: ReactNode; wide?: boolean }) {
  return (
    <div className="fixed inset-0 z-[75] flex items-center justify-center p-4">
      <button type="button" className="absolute inset-0 bg-stone-950/65 backdrop-blur-sm" onClick={onClose} aria-label="Đóng" />
      <div className={cn('relative z-10 max-h-[92vh] w-full overflow-y-auto rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] p-5 shadow-2xl', wide ? 'max-w-5xl' : 'max-w-xl')}>
        <div className="mb-4 flex items-center justify-between gap-3 border-b border-[#3d2a18]/10 pb-3">
          <h2 className="text-lg font-black text-[#24170d]">{title}</h2>
          <button type="button" onClick={onClose} className="rounded-xl p-1.5 text-[#8b5e34] transition hover:bg-rose-50 hover:text-rose-600"><X className="h-4 w-4" /></button>
        </div>
        {children}
      </div>
    </div>
  )
}
