import { ArrowLeft, Building2, ImageIcon, Plus, Save, Trash2 } from 'lucide-react'
import { fetchBuilding } from '../services/rooms.service';
import { useEffect, useState } from 'react';
import type { BuildingResource } from '../types/rooms.model';

export function Create() {
    const loadBuilding=async()=>{
        try {
            const res=await fetchBuilding();
            console.log(res.result);
            setBuildings(res.result);
        } catch (error) {
            
        }
    }
    const [buildings,setBuildings]=useState<BuildingResource[]>([]);
useEffect(()=>{
    loadBuilding();
},[]);
  return (
    <div className="mx-auto max-w-5xl bg-amber-50 p-6">
      {/* Header */}
      <div className="mb-7 flex items-center gap-3">
        <button className="flex h-9 w-9 items-center justify-center rounded-full border border-amber-200 bg-white text-amber-700">
          <ArrowLeft size={18} />
        </button>

        <div>
          <h1 className="text-2xl font-bold text-amber-900">
            Thêm phòng mới
          </h1>
          <p className="text-sm text-amber-600">
            Điền đầy đủ thông tin để tạo phòng
          </p>
        </div>
      </div>

      {/* Basic Information */}
      <div className="mb-4 overflow-hidden rounded-2xl border border-amber-100 bg-white">
        <div className="flex items-center gap-2 border-b bg-amber-50 px-5 py-3">
          <Building2 size={16} />
          <span className="text-xs font-semibold uppercase tracking-wider text-amber-800">
            Thông tin cơ bản
          </span>
        </div>

        <div className="p-5">
          <div className="grid grid-cols-1 gap-x-5 gap-y-4 md:grid-cols-2">
            <div>
              <label className="mb-1 block text-sm text-stone-600">
                Tòa nhà *
              </label>
              <select className="w-full rounded-xl border border-amber-200 px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
                    <option value="">Chọn tòa nhà</option>           
                     {buildings.map((building) => (
                        <option key={building.id} value={building.id}>
                        {building.name}
                        </option>
                    ))}
              </select>
            </div>

            <div>
              <label className="mb-1 block text-sm text-stone-600">
                Loại phòng *
              </label>
              <select className="w-full rounded-xl border border-amber-200 px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
                <option>Phòng tiêu chuẩn</option>
              </select>
            </div>

            <div>
              <label className="mb-1 block text-sm text-stone-600">
                Số / Tên phòng *
              </label>
              <input
                placeholder="VD: A101"
                className="w-full rounded-xl border border-amber-200 px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none"
              />
            </div>

            <div>
              <label className="mb-1 block text-sm text-stone-600">
                Tầng *
              </label>
              <input
                type="number"
                className="w-full rounded-xl border border-amber-200 px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none"
              />
            </div>

            <div>
              <label className="mb-1 block text-sm text-stone-600">
                Diện tích (m²)
              </label>
              <input
                type="number"
                className="w-full rounded-xl border border-amber-200 px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none"
              />
            </div>

            <div>
              <label className="mb-1 block text-sm text-stone-600">
                Số người tối đa
              </label>
              <input
                type="number"
                className="w-full rounded-xl border border-amber-200 px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none"
              />
            </div>

            <div>
              <label className="mb-1 block text-sm text-stone-600">
                Giá phòng cơ bản
              </label>

              <div className="relative">
                <input
                  className="w-full rounded-xl border border-amber-200 px-3 py-2 pr-14 focus:ring-2 focus:ring-amber-400 focus:outline-none"
                  placeholder="3,500,000"
                />
                <span className="absolute top-1/2 right-3 -translate-y-1/2 text-xs text-amber-600">
                  VNĐ
                </span>
              </div>
            </div>

            <div>
              <label className="mb-1 block text-sm text-stone-600">
                Trạng thái
              </label>

              <select className="w-full rounded-xl border border-amber-200 px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
                <option>Hoạt động</option>
                <option>Bảo trì</option>
              </select>
            </div>

            <div className="md:col-span-2">
              <label className="mb-1 block text-sm text-stone-600">
                Mô tả
              </label>

              <textarea
                rows={3}
                className="w-full rounded-xl border border-amber-200 px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none"
              />
            </div>
          </div>
        </div>
      </div>

      {/* Images */}
      <div className="mb-4 overflow-hidden rounded-2xl border border-amber-100 bg-white">
        <div className="flex items-center gap-2 border-b bg-amber-50 px-5 py-3">
          <ImageIcon size={16} />
          <span className="text-xs font-semibold uppercase tracking-wider text-amber-800">
            Hình ảnh phòng
          </span>
        </div>

        <div className="p-5">
          <div className="cursor-pointer rounded-xl border-2 border-dashed border-amber-200 p-8 text-center transition hover:bg-amber-50">
            <div className="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-amber-100">
              ↑
            </div>

            <p className="text-sm">
              <span className="font-semibold text-amber-700">
                Nhấn để chọn ảnh
              </span>
              {' '}hoặc kéo thả vào đây
            </p>

            <p className="mt-1 text-xs text-stone-500">
              PNG, JPG, WEBP — tối đa 8 ảnh
            </p>
          </div>

          <div className="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
            {[1, 2, 3].map((item) => (
              <div
                key={item}
                className="aspect-square rounded-xl border border-amber-100 bg-amber-50"
              />
            ))}
          </div>
        </div>
      </div>

      {/* Assets */}
      <div className="mb-4 overflow-hidden rounded-2xl border border-amber-100 bg-white">
        <div className="border-b bg-amber-50 px-5 py-3">
          <span className="text-xs font-semibold uppercase tracking-wider text-amber-800">
            Tài sản bàn giao
          </span>
        </div>

        <div className="p-5">
          <div className="mb-3 grid grid-cols-12 gap-2 rounded-xl border border-amber-100 bg-amber-50 p-3">
            <div className="col-span-4">
              <select className="w-full rounded-lg border border-amber-200 px-3 py-2">
                <option>Máy lạnh</option>
              </select>
            </div>

            <div className="col-span-2">
              <input
                type="number"
                defaultValue={1}
                className="w-full rounded-lg border border-amber-200 px-3 py-2"
              />
            </div>

            <div className="col-span-3">
              <input
                placeholder="Giá trị"
                className="w-full rounded-lg border border-amber-200 px-3 py-2"
              />
            </div>

            <div className="col-span-2">
              <input
                placeholder="Ghi chú"
                className="w-full rounded-lg border border-amber-200 px-3 py-2"
              />
            </div>

            <button className="flex items-center justify-center text-red-500">
              <Trash2 size={18} />
            </button>
          </div>

          <button className="flex w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-amber-200 py-3 font-medium text-amber-700 hover:bg-amber-50">
            <Plus size={18} />
            Thêm tài sản
          </button>
        </div>
      </div>

      {/* Actions */}
      <div className="flex justify-end gap-3">
        <button className="rounded-xl border border-amber-200 bg-white px-5 py-2.5 text-stone-600 hover:bg-amber-50">
          Huỷ
        </button>

        <button className="flex items-center gap-2 rounded-xl bg-amber-700 px-5 py-2.5 font-medium text-white hover:bg-amber-800">
          <Save size={18} />
          Lưu phòng
        </button>
      </div>
    </div>
  )
}