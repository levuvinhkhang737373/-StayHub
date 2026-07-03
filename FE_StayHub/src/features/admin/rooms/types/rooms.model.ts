export interface AdminRoomResource {
  id: number
  building_id: number
  building_name: string
  room_type_id: number
  room_type_name: string
  room_number: string 
  slug: string
  base_price: number
  max_occupants: number
  current_occupants: number
  status: number 
  created_at: string
  updated_at: string
  floor: number
  building: {               
    id: number
    name: string
    slug: string
    gender_policy?: number | null
  }
  room_type: {              
    id: number
    name: string
    slug: string
  }
  area_m2: number
  description: string
  images: [
    {
      id: number,
      image_path: string
    }
  ]
  
  // BỔ SUNG THÊM TRƯỜNG NÀY ĐỂ HẾT LỖI TYPESCRIPT
  assets?: {
    id: number;
    room_id: number;
    asset_template_id: number;
    quantity: number;
    note: string | null;
    created_at?: string;
    updated_at?: string;
    // Nếu bạn nạp lồng (Eager Loading) thì thêm object này
    asset_template?: {
      id: number;
      name: string;
      slug: string;
    }
  }[];
}
export interface RoomFormDataPayload {
  building_id: number;
  room_type_id: number;
  room_number: string;
  floor: number;
  area_m2: number;
  base_price: number;
  max_occupants: number;
  status: number;
  description: string;
  images?: File[];
  assets?: {
    template_id: number;
    quantity: number;
    note?: string;
  }[];
}
export interface BuildingResource {
  id: number
  name: string
  gender_policy?: number | null
  total_floors?: number | null
}

export interface RoomTypeResource{
  id:number,
  name:string
}
export interface AssetResource{
  id:number,
  name:string
}
