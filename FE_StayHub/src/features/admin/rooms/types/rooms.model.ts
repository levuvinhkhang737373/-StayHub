export interface AdminRoomResource {
  id: number
  building_id: number
  building_name: string
  room_type_id: number
  room_type_name: string
  room_number: string // Ví dụ: "Phòng 101"
  slug: string
  base_price: number
  max_occupants: number
  current_occupants: number
  status: number // 1: Trống, 2: Đang ở, 3: Đang sửa chữa...
  created_at: string
  updated_at: string
  floor:number
   building: {               
    id: number
    name: string
    slug: string
  }
   room_type: {              
    id: number
    name: string
    slug: string
  }
  area_m2:number
  description:string
}
export interface AdminRoomPayload {
  building_id: number
  room_type_id: number
  room_number: string
  base_price: number
  max_occupants: number
  status?: number
}
export interface BuildingResource {
  id: number
  name: string

}

