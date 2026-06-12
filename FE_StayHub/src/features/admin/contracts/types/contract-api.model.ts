export interface AdminPaginationMeta {
  current_page?: number
  from?: number | null
  last_page?: number
  path?: string
  per_page?: number
  to?: number | null
  total?: number
}

export interface AdminPaginator<T> {
  data: T[]
  links?: unknown
  meta?: AdminPaginationMeta | null
}

export interface AdminVehicleOptionResource {
  id: number
  tenant_id: number
  tenant_name?: string | null
  vehicle_type?: number | null
  vehicle_type_label?: string | null
  license_plate?: string | null
  brand?: string | null
  color?: string | null
  is_active?: boolean
  is_active_label?: string | null
}

export interface AdminContractTenantResource {
  id?: number
  contract_id?: number
  tenant_id: number
  tenant?: {
    id: number
    full_name?: string | null
    phone?: string | null
    email?: string | null
    identity_number?: string | null
    status?: number | null
  } | null
  join_date?: string | null
  leave_date?: string | null
  billing_start_date?: string | null
  billing_end_date?: string | null
  is_staying?: boolean
  is_staying_label?: string | null
  created_by?: number | null
  created_at?: string | null
  updated_at?: string | null
}

export interface AdminContractVehicleResource {
  id?: number
  contract_id?: number
  vehicle_id: number
  vehicle?: {
    id: number
    tenant_id?: number | null
    tenant_name?: string | null
    vehicle_type?: number | null
    vehicle_type_label?: string | null
    license_plate?: string | null
    brand?: string | null
    color?: string | null
    is_active?: boolean | null
  } | null
  started_at?: string | null
  ended_at?: string | null
  billing_start_date?: string | null
  billing_end_date?: string | null
  monthly_fee?: string | null
  charge_policy?: number | null
  charge_policy_label?: string | null
  is_active?: boolean
  is_active_label?: string | null
  created_at?: string | null
  updated_at?: string | null
}

export interface AdminContractDepositTransactionResource {
  id?: number
  contract_id?: number
  transaction_type: number
  transaction_type_label?: string | null
  amount: string
  transaction_date: string
  payment_method: number
  payment_method_label?: string | null
  transaction_reference?: string | null
  note?: string | null
  created_by?: number | null
  creator_name?: string | null
  created_at?: string | null
}

export interface AdminContractFileResource {
  path: string
  name?: string | null
  url?: string | null
}

export interface AdminContractRoomResource {
  id: number
  building_id?: number | null
  building_name?: string | null
  room_type_id?: number | null
  room_type_name?: string | null
  room_code?: string | null
  room_number?: string | null
  slug?: string | null
  floor?: number | null
  area_m2?: string | number | null
  base_price?: string | null
  max_occupants?: number | null
  current_occupants?: number | null
  status?: number | null
  description?: string | null
}

export interface AdminContractTenantSummaryResource {
  id: number
  full_name?: string | null
  phone?: string | null
  email?: string | null
  identity_number?: string | null
  status?: number | null
}

export interface AdminContractResource {
  id: number
  contract_code: string
  room_id: number
  room_code?: string | null
  room_number?: string | null
  building_id?: number | null
  building_name?: string | null
  start_date?: string | null
  end_date?: string | null
  actual_end_date?: string | null
  billing_cycle_day?: number | null
  room_price?: string | null
  deposit_amount?: string | null
  status: number
  status_label?: string | null
  payment_status?: number
  payment_status_label?: string | null
  is_deposit_paid?: boolean
  deposit_balance?: string | null
  deposit_qr_url?: string | null
  note?: string | null
  contract_files?: AdminContractFileResource[]
  room?: AdminContractRoomResource | null
  contract_tenants?: AdminContractTenantResource[]
  contract_vehicles?: AdminContractVehicleResource[]
  deposit_transactions?: AdminContractDepositTransactionResource[]
  contract_tenants_count?: number
  tenants_count?: number
  vehicles_count?: number
  contract_vehicles_count?: number
  deposit_transactions_count?: number
  room_movements_count?: number
  parent_contract_id?: number | null
  renew_from_contract_id?: number | null
  created_by?: number | null
  creator_name?: string | null
  created_at?: string | null
  updated_at?: string | null
  deleted_at?: string | null
}

export interface AdminContractFilters {
  keyword?: string
  status?: number
  building_id?: number
  room_id?: number
  start_date_from?: string
  start_date_to?: string
  end_date_from?: string
  end_date_to?: string
  page?: number
  per_page?: number
}

export interface AdminContractTenantPayload {
  tenant_id: number
  join_date: string
  leave_date?: string | null
  billing_start_date?: string | null
  billing_end_date?: string | null
  is_staying?: boolean
}

export interface AdminContractVehiclePayload {
  vehicle_id: number
  started_at: string
  ended_at?: string | null
  billing_start_date?: string | null
  billing_end_date?: string | null
  monthly_fee?: string | null
  charge_policy: number
  is_active?: boolean
}

export interface AdminContractDepositTransactionPayload {
  transaction_type: number
  amount: string
  transaction_date: string
  payment_method: number
  note?: string | null
}

export interface AdminContractPayload {
  contract_code?: string
  room_id?: number
  start_date?: string
  end_date?: string
  actual_end_date?: string | null
  billing_cycle_day?: number
  room_price?: string
  deposit_amount?: string
  status?: number
  contract_files?: File[]
  delete_contract_files?: string[]
  note?: string | null
  parent_contract_id?: number | null
  renew_from_contract_id?: number | null
  tenants?: AdminContractTenantPayload[]
  vehicles?: AdminContractVehiclePayload[]
  deposit_transactions?: AdminContractDepositTransactionPayload[]
  is_deposit_paid?: boolean
  deposit_payment_method?: number | null
  payment_status?: number
}

export interface AdminContractStatusPayload {
  status: number
  actual_end_date?: string | null
  note?: string | null
}

export interface ContractTenantFormRow {
  tenant_id: string
  join_date: string
  leave_date: string
  billing_start_date: string
  billing_end_date: string
  is_staying: boolean
}

export interface ContractVehicleFormRow {
  vehicle_id: string
  started_at: string
  ended_at: string
  billing_start_date: string
  billing_end_date: string
  monthly_fee: string
  charge_policy: number
  is_active: boolean
}

export interface ContractDepositFormRow {
  transaction_type: number
  amount: string
  transaction_date: string
  payment_method: number
  note: string
}

export interface ContractFormValues {
  contract_code: string
  building_id: string
  room_id: string
  start_date: string
  end_date: string
  actual_end_date: string
  billing_cycle_day: string
  room_price: string
  deposit_amount: string
  status: number
  contract_files: File[]
  delete_contract_files: string[]
  note: string
  parent_contract_id: string
  renew_from_contract_id: string
  tenants: ContractTenantFormRow[]
  vehicles: ContractVehicleFormRow[]
  deposit_transactions: ContractDepositFormRow[]
  is_deposit_paid: boolean
  deposit_payment_method: string
}

export type ContractFormErrors = Partial<Record<keyof ContractFormValues | `tenants.${number}` | `vehicles.${number}` | `deposit_transactions.${number}`, string>>
