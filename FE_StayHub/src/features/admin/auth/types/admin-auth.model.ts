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
  status: boolean
  gender?: string | null
  address?: string | null
  created_faceid_at: string | null
  updated_faceid_at: string | null
  created_at: string | null
  updated_at: string | null
}

export interface AdminLoginPayload {
  username: string
  password: string
}

export interface AdminLoginResult {
  admin: AdminProfile
}
