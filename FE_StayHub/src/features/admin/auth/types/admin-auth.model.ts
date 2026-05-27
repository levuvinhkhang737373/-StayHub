export interface AdminManagedBuildingProfile {
  id: number
  name: string
  slug?: string | null
  address?: string | null
  status?: string | number | boolean | null
}

export interface AdminProfile {
  id: number
  username: string
  full_name: string
  email: string | null
  phone: string | null
  avatar: string | null
  avatar_url?: string | null
  image_path_faceid: string | null
  has_faceid: boolean
  role: string | number
  status: string | number | boolean
  gender?: string | null
  address?: string | null
  managed_buildings_count?: number
  managed_buildings?: AdminManagedBuildingProfile[]
  managed_building_names?: string[]
  created_faceid_at: string | null
  updated_faceid_at: string | null
  created_at: string | null
  updated_at: string | null
}

export interface AdminLoginPayload {
  username: string
  password: string
}

export interface AdminChangePasswordPayload {
  current_password: string
  new_password: string
  new_password_confirmation: string
}

export interface AdminLoginResult {
  admin: AdminProfile
}
