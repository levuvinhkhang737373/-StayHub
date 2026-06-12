import { ArrowLeft, Building2, ImageIcon, Plus, Save, X } from 'lucide-react';
import { fetchAssets, fetchBuilding, fetchRoomType, createAdminRoom } from '../services/rooms.service';
import { useEffect, useState } from 'react';
import type { AssetResource, BuildingResource, RoomTypeResource } from '../types/rooms.model';
import { useNavigate } from 'react-router-dom';

interface LocalFormState {
  building_id: string;
  room_type_id: string;
  room_number: string;
  floor: string;
  area_m2: string;
  base_price: string;
  max_occupants: string;
  status: number;
  description: string;
}

// Định nghĩa cấu trúc Item Tài sản được chọn để quản lý trong State
interface SelectedAssetItem {
  template_id: number;
  name: string; // Lưu tên để hiển thị trên UI
  quantity: number;
  note: string;
}

export function Create() {
  const [buildings, setBuildings] = useState<BuildingResource[]>([]);
  const [roomTypes, setRoomTypes] = useState<RoomTypeResource[]>([]);
  const [assets, setAssets] = useState<AssetResource[]>([]);
  const navigate=useNavigate();
  // State quản lý Form cơ bản
  const [formData, setFormData] = useState<LocalFormState>({
    building_id: '',
    room_type_id: '',
    room_number: '',
    floor: '',
    area_m2: '',
    base_price: '',
    max_occupants: '',
    status: 1,
    description: '',
  });

  // --- THAY ĐỔI: State tài sản dạng Mảng Đối Tượng có đầy đủ số lượng + ghi chú ---
  const [selectedAssets, setSelectedAssets] = useState<SelectedAssetItem[]>([]);
  const [selectedImages, setSelectedImages] = useState<File[]>([]);

  const loadBuilding = async () => {
    try { const res = await fetchBuilding(); setBuildings(res.result); } catch (e) { console.error(e); }
  };
  const loadRoomType = async () => {
    try { const res = await fetchRoomType(); setRoomTypes(res.result); } catch (e) { console.error(e); }
  };
  const loadAssets = async () => {
    try { const res = await fetchAssets(); setAssets(res.result); } catch (e) { console.error(e); }
  };

  useEffect(() => {
    loadBuilding();
    loadRoomType();
    loadAssets();
  }, []);

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
    const { name, value } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]: name === 'status' ? Number(value) : value
    }));
  };

  // --- HÀM XỬ LÝ TICK/BỎ TICK CHỌN TÀI SẢN ---
  const handleToggleAsset = (asset: AssetResource) => {
    setSelectedAssets((prev) => {
      const exists = prev.find((item) => item.template_id === asset.id);
      if (exists) {
        // Nếu đã chọn rồi -> bỏ chọn (xóa khỏi mảng)
        return prev.filter((item) => item.template_id !== asset.id);
      } else {
        // Nếu chưa chọn -> thêm mới với số lượng mặc định là 1 và ghi chú trống
        return [...prev, { template_id: asset.id, name: asset.name, quantity: 1, note: '' }];
      }
    });
  };

  // --- HÀM THAY ĐỔI SỐ LƯỢNG HOẶC GHI CHÚ CỦA TÀI SẢN ---
  const handleAssetFieldChange = (templateId: number, field: 'quantity' | 'note', value: any) => {
    setSelectedAssets((prev) =>
      prev.map((item) =>
        item.template_id === templateId 
          ? { ...item, [field]: field === 'quantity' ? Number(value) : value } 
          : item
      )
    );
  };

  const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files.length > 0) {
      const files = Array.from(e.target.files);
      setSelectedImages((prev) => [...prev, ...files]);
    }
  };

  const handleRemoveImage = (indexToRemove: number) => {
    setSelectedImages((prev) => prev.filter((_, index) => index !== indexToRemove));
  };
 const handleLink=()=>{
     navigate('/admin/rooms');
 }
  // --- SUBMIT GỬI DATA CHUẨN HOÀN HẢO THEO REQUEST ARRAY ---
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    const data = new FormData();

    // 1. Gắn thông tin cơ bản
    data.append('building_id', formData.building_id);
    data.append('room_type_id', formData.room_type_id);
    data.append('room_number', formData.room_number);
    data.append('floor', formData.floor);
    data.append('area_m2', formData.area_m2);
    data.append('base_price', formData.base_price);
    data.append('max_occupants', formData.max_occupants);
    data.append('status', String(formData.status));
    data.append('description', formData.description);

    // 2. Gắn mảng assets dạng lồng nhau chuẩn Laravel Request Validation
    selectedAssets.forEach((item, index) => {
      data.append(`assets[${index}][template_id]`, String(item.template_id));
      data.append(`assets[${index}][quantity]`, String(item.quantity));
      data.append(`assets[${index}][note]`, item.note);
    });

    // 3. Gắn ảnh
    selectedImages.forEach((file) => {
      data.append('images[]', file);
    });

    try {
      const response = await createAdminRoom(data);
      alert('Tạo phòng mới thành công!');
      console.log(response);
      setFormData({
           building_id: '',
    room_type_id: '',
    room_number: '',
    floor: '',
    area_m2: '',
    base_price: '',
    max_occupants: '',
    status: 1,
    description: '',
      });
      setSelectedImages([]);
      setSelectedAssets([]);
    } catch (error) {
      console.error(error);
      alert('Đã xảy ra lỗi validate'+error);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="mx-auto max-w-5xl bg-amber-50 p-6">
      {/* Header */}
      <div className="mb-7 flex items-center gap-3">
        <button type="button" className="flex h-9 w-9 items-center justify-center rounded-full border border-amber-200 bg-white text-amber-700" onClick={handleLink}>
          <ArrowLeft size={18} />
        </button>
        <div>
          <h1 className="text-2xl font-bold text-amber-900">Thêm phòng mới</h1>
          <p className="text-sm text-amber-600">Điền đầy đủ thông tin để tạo phòng</p>
        </div>
      </div>

      {/* Thông tin cơ bản */}
      <div className="mb-4 overflow-hidden rounded-2xl border border-amber-100 bg-white">
        <div className="flex items-center gap-2 border-b bg-amber-50 px-5 py-3">
          <Building2 size={16} />
          <span className="text-xs font-semibold uppercase tracking-wider text-amber-800">Thông tin cơ bản</span>
        </div>

        <div className="p-5">
          <div className="grid grid-cols-1 gap-x-5 gap-y-4 md:grid-cols-2">
            <div>
              <label className="mb-1 block text-sm text-stone-600">Tòa nhà *</label>
              <select 
                name="building_id" value={formData.building_id} onChange={handleInputChange} required
                className="w-full rounded-xl border border-amber-200 px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none"
              >
                <option value="">Chọn tòa nhà</option>          
                {buildings.map((building) => (
                  <option key={building.id} value={building.id}>{building.name}</option>
                ))}
              </select>
            </div>

            <div>
              <label className="mb-1 block text-sm text-stone-600">Loại phòng *</label>
              <select 
                name="room_type_id" value={formData.room_type_id} onChange={handleInputChange} required
                className="w-full rounded-xl border border-amber-200 px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none"
              >
                <option value="">Chọn loại phòng</option>
                {roomTypes.map((roomType)=>(
                  <option key={roomType.id} value={roomType.id}>{roomType.name}</option>
                ))}        
              </select>
            </div>

            <div>
              <label className="mb-1 block text-sm text-stone-600">Số / Tên phòng *</label>
              <input 
                name="room_number" value={formData.room_number} onChange={handleInputChange} required placeholder="VD: A101" 
                className="w-full rounded-xl border border-amber-200 px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none" 
              />
            </div>

            <div>
              <label className="mb-1 block text-sm text-stone-600">Tầng *</label>
              <input 
                type="number" name="floor" value={formData.floor} onChange={handleInputChange} required
                className="w-full rounded-xl border border-amber-200 px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none" 
              />
            </div>

            <div>
              <label className="mb-1 block text-sm text-stone-600">Diện tích (m²)</label>
              <input 
                type="number" step="0.01" name="area_m2" value={formData.area_m2} onChange={handleInputChange} required
                className="w-full rounded-xl border border-amber-200 px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none" 
              />
            </div>

            <div>
              <label className="mb-1 block text-sm text-stone-600">Số người tối đa</label>
              <input 
                type="number" name="max_occupants" value={formData.max_occupants} onChange={handleInputChange} required
                className="w-full rounded-xl border border-amber-200 px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none" 
              />
            </div>

            <div>
              <label className="mb-1 block text-sm text-stone-600">Giá phòng cơ bản</label>
              <div className="relative">
                <input 
                  type="number" name="base_price" value={formData.base_price} onChange={handleInputChange} required
                  className="w-full rounded-xl border border-amber-200 px-3 py-2 pr-14 focus:ring-2 focus:ring-amber-400 focus:outline-none" 
                  placeholder="3500000" 
                />
                <span className="absolute top-1/2 right-3 -translate-y-1/2 text-xs text-amber-600">VNĐ</span>
              </div>
            </div>

            <div>
              <label className="mb-1 block text-sm text-stone-600">Trạng thái</label>
              <select 
                name="status" value={formData.status} onChange={handleInputChange}
                className="w-full rounded-xl border border-amber-200 px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none"
              >
                <option value={1}>Hoạt động</option>
                <option value={3}>Bảo trì</option>
                <option value={2}>Đang ở</option>
              </select>
            </div>

            <div className="md:col-span-2">
              <label className="mb-1 block text-sm text-stone-600">Mô tả</label>
              <textarea 
                name="description" value={formData.description} onChange={handleInputChange} rows={3} 
                className="w-full rounded-xl border border-amber-200 px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none" 
              />
            </div>
          </div>
        </div>
      </div>

      {/* Upload Hình ảnh */}
      <div className="mb-4 overflow-hidden rounded-2xl border border-amber-100 bg-white">
        <div className="flex items-center gap-2 border-b bg-amber-50 px-5 py-3">
          <ImageIcon size={16} className="text-amber-700" />
          <span className="text-xs font-semibold uppercase tracking-wider text-amber-800">Hình ảnh phòng</span>
        </div>

        <div className="p-5">
          <label className="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-amber-200 bg-white py-6 px-4 text-center cursor-pointer transition hover:bg-amber-50/50 hover:border-amber-400 group">
            <input type="file" multiple accept="image/*" className="hidden" onChange={handleImageChange} />
            <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-amber-100 text-amber-700 transition group-hover:scale-110 group-hover:bg-amber-200">
              <Plus size={20} />
            </div>
            <p className="text-sm text-stone-700">
              <span className="font-semibold text-amber-700">Nhấn để tải ảnh lên</span> hoặc kéo thả tập tin
            </p>
            <p className="mt-1 text-xs text-stone-400">PNG, JPG, WEBP</p>
          </label>

          {selectedImages.length > 0 && (
            <div className="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
              {selectedImages.map((file, index) => {
                const imageUrl = URL.createObjectURL(file);
                return (
                  <div key={index} className="group relative aspect-square overflow-hidden rounded-xl border border-amber-100 bg-stone-50">
                    <img src={imageUrl} alt={`preview-${index}`} className="h-full w-full object-cover transition duration-300 group-hover:scale-105" />
                    <div className="absolute inset-0 bg-black/10 opacity-0 transition group-hover:opacity-100" />
                    <button
                      type="button"
                      onClick={() => handleRemoveImage(index)}
                      className="absolute top-2 right-2 flex h-6 w-6 items-center justify-center rounded-full bg-stone-900/80 text-white transition hover:bg-red-600"
                    >
                      <X size={14} />
                    </button>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      </div>

      {/* --- PHẦN GIAO DIỆN TÀI SẢN ĐÃ ĐƯỢC LÀM LẠI KHỚP SCHEMA --- */}
      <div className="mb-4 overflow-hidden rounded-2xl border border-amber-100 bg-white">
        <div className="border-b bg-amber-50 px-5 py-3">
          <span className="text-xs font-semibold uppercase tracking-wider text-amber-800">Tài sản bàn giao</span>
        </div>

        <div className="p-5">
          {/* Bước 1: Danh sách các tài sản để tick chọn nhanh */}
          <div className="mb-5 flex flex-wrap gap-2">
            {assets.map((asset) => {
              const isChecked = selectedAssets.some((item) => item.template_id === asset.id);
              return (
                <button
                  type="button"
                  key={asset.id}
                  onClick={() => handleToggleAsset(asset)}
                  className={`rounded-xl border px-3 py-1.5 text-sm transition ${
                    isChecked 
                      ? 'border-amber-600 bg-amber-100 font-medium text-amber-900' 
                      : 'border-stone-200 bg-white text-stone-700 hover:bg-stone-50'
                  }`}
                >
                  {isChecked ? '✓ ' : '+ '} {asset.name}
                </button>
              );
            })}
          </div>

          {/* Bước 2: Hiển thị danh sách chi tiết (Số lượng & Ghi chú) của những tài sản đã chọn */}
          {selectedAssets.length > 0 ? (
            <div className="rounded-xl border border-stone-100 bg-stone-50/50 p-4">
              <p className="mb-3 text-xs font-medium text-stone-500 uppercase tracking-wider">Cấu hình số lượng & ghi chú</p>
              <div className="flex flex-col gap-3">
                {selectedAssets.map((item) => (
                  <div key={item.template_id} className="grid grid-cols-1 items-center gap-3 rounded-xl border border-amber-100 bg-white p-3 sm:grid-cols-12">
                    {/* Tên tài sản */}
                    <div className="sm:col-span-3">
                      <span className="text-sm font-semibold text-stone-800">{item.name}</span>
                    </div>
                    
                    {/* Ô nhập Số lượng (quantity) */}
                    <div className="flex items-center gap-2 sm:col-span-3">
                      <label className="text-xs text-stone-500 whitespace-nowrap">SL:</label>
                      <input
                        type="number"
                        min={1}
                        value={item.quantity}
                        required
                        onChange={(e) => handleAssetFieldChange(item.template_id, 'quantity', e.target.value)}
                        className="w-full rounded-lg border border-stone-200 px-2 py-1 text-sm focus:border-amber-400 focus:outline-none"
                      />
                    </div>

                    {/* Ô nhập Ghi chú (note) */}
                    <div className="flex items-center gap-2 sm:col-span-5">
                      <label className="text-xs text-stone-500 whitespace-nowrap">Ghi chú:</label>
                      <input
                        type="text"
                        placeholder="Nhập ghi chú tình trạng..."
                        value={item.note}
                        maxLength={500}
                        onChange={(e) => handleAssetFieldChange(item.template_id, 'note', e.target.value)}
                        className="w-full rounded-lg border border-stone-200 px-2 py-1 text-sm focus:border-amber-400 focus:outline-none"
                      />
                    </div>

                    {/* Nút Xóa nhanh tài sản */}
                    <div className="text-right sm:col-span-1">
                      <button
                        type="button"
                        onClick={() => setSelectedAssets((prev) => prev.filter((i) => i.template_id !== item.template_id))}
                        className="text-stone-400 hover:text-red-500"
                      >
                        <X size={16} />
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          ) : (
            <p className="text-center text-sm text-stone-400 py-4">Chưa chọn tài sản nào cho phòng này.</p>
          )}
        </div>
      </div>

      {/* Buttons Hành động */}
      <div className="flex justify-end gap-3">
        <button type="button" className="rounded-xl border border-amber-200 bg-white px-5 py-2.5 text-stone-600 hover:bg-amber-50">Huỷ</button>
        <button type="submit" className="flex items-center gap-2 rounded-xl bg-amber-700 px-5 py-2.5 font-medium text-white hover:bg-amber-800">
          <Save size={18} />
          Lưu phòng
        </button>
      </div>
    </form>
  );
}