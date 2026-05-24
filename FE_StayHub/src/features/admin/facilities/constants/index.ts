import { Bike, Camera, Car, Dumbbell, ShieldCheck, Trees, Wifi, WashingMachine } from 'lucide-react'
import type { BuildingAmenity } from '../types/building.model'

export const BUILDING_AMENITIES: BuildingAmenity[] = [
  { label: 'Wifi', icon: Wifi },
  { label: 'Camera', icon: Camera },
  { label: 'Bảo vệ', icon: ShieldCheck },
  { label: 'Giặt sấy', icon: WashingMachine },
  { label: 'Bãi xe', icon: Car },
  { label: 'Phòng gym', icon: Dumbbell },
  { label: 'Sân vườn', icon: Trees },
  { label: 'Xe đạp', icon: Bike },
]
