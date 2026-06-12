import { ArrowLeft, Building2, ImageIcon, Plus, Save, X } from 'lucide-react';
import { fetchAssets, fetchBuilding, fetchRoomType, fetchAdminRoomDetail, updateAdminRoom } from '../services/rooms.service';
import { useEffect, useState } from 'react';
import type { AssetResource, BuildingResource, RoomTypeResource, AdminRoomResource } from '../types/rooms.model';
import { useNavigate, useParams } from 'react-router-dom';

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

interface SelectedAssetItem {
  template_id: number;
  name: string; 
  quantity: number;
  note: string;
}

interface ExistingImage {
  id: number;
  image_path: string;
}

export function Update() {
  const { id } = useParams<{ id: string }>(); // Lấy ID phòng cần sửa từ URL
  const navigate = useNavigate();
  
  const [buildings, setBuildings] = useState<BuildingResource[]>([]);
  const [roomTypes, setRoomTypes] = useState<RoomTypeResource[]>([]);
  const [assets, setAssets] = useState<AssetResource[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(true);

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

  const [selectedAssets, setSelectedAssets] = useState<SelectedAssetItem[]>([]);
  
  // Quản lý ảnh cũ và ảnh mới
  const [existingImages, setExistingImages] = useState<ExistingImage[]>([]);
  const [selectedImages, setSelectedImages] = useState<File[]>([]);
  const [deletedImageIds, setDeletedImageIds] = useState<number[]>([]);

  // --- TẢI DANH MỤC OPTIONS GỐC ---
  const loadOptions = async () => {
    try {
      const [resBuildings, resRoomTypes, resAssets] = await Promise.all([
        fetchBuilding(),
        fetchRoomType(),
        fetchAssets()
      ]);
      setBuildings(resBuildings.result);
      setRoomTypes(resRoomTypes.result);
      setAssets(resAssets.result);
    } catch (e) {
      console.error("Lỗi tải danh mục cấu hình:", e);
    }
  };

  // --- TẢI CHI TIẾT PHÒNG CẦN SỬA ---
  const loadRoomDetail = async () => {
    if (!id) return;
    try {
      setIsLoading(true);
      const res = await fetchAdminRoomDetail(Number(id));
      const roomData: AdminRoomResource = res.result;

      // Map thông tin cơ bản vào Form State
      setFormData({
        building_id: String(roomData.building?.id ?? ''),
        room_type_id: String(roomData.room_type?.id ?? ''),
        room_number: roomData.room_number,
        floor: String(roomData.floor),
        area_m2: String(roomData.area_m2),
        base_price: String(roomData.base_price),
        max_occupants: String(roomData.max_occupants),
        status: Number(roomData.status),
        description: roomData.description ?? '',
      });

      // Map danh sách hình ảnh cũ đang có sẵn
      if (roomData.images) {
        setExistingImages(roomData.images);
      }

      // MAP TÀI SẢN THEO QUAN HỆ HASMANY + NESTED RELATION (assets.assetTemplate)
      if (roomData.assets && Array.isArray(roomData.assets)) {
        const mappedAssets = roomData.assets.map((ast: any) => ({
          // Ưu tiên lấy ID từ đối tượng asset_template nạp lồng, nếu không có thì fallback về asset_template_id
          template_id: ast.asset_template ? Number(ast.asset_template.id) : Number(ast.asset_template_id),
          
          // Lấy trực tiếp tên hiển thị từ model liên kết AssetTemplate
          name: ast.asset_template?.name || 'Tài sản không tên',
          
          // Số lượng và ghi chú nằm trực tiếp tại object room_asset
          quantity: ast.quantity ? Number(ast.quantity) : 1,
          note: ast.note ?? '',
        }));
        setSelectedAssets(mappedAssets);
      }

    } catch (error) {
      console.error("Lỗi tải chi tiết phòng:", error);
      alert("Không tìm thấy dữ liệu phòng cần chỉnh sửa.");
      navigate('/admin/rooms');
    } finally {
      setIsLoading(false);
    }
  };

  // Khởi chạy tuần tự: Nạp danh mục trước, nạp chi tiết phòng sau để đồng bộ UI
  useEffect(() => {
    void (async () => {
      await loadOptions();
      await loadRoomDetail();
    })();
  }, [id]);

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
    const { name, value } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]: name === 'status' ? Number(value) : value
    }));
  };

  // --- TÀI SẢN EVENT HANDLERS ---
  const handleToggleAsset = (asset: AssetResource) => {
    setSelectedAssets((prev) => {
      const exists = prev.find((item) => item.template_id === asset.id);
      if (exists) {
        return prev.filter((item) => item.template_id !== asset.id);
      } else {
        return [...prev, { template_id: asset.id, name: asset.name, quantity: 1, note: '' }];
      }
    });
  };

  const handleAssetFieldChange = (templateId: number, field: 'quantity' | 'note', value: any) => {
    setSelectedAssets((prev) =>
      prev.map((item) =>
        item.template_id === templateId 
          ? { ...item, [field]: field === 'quantity' ? Number(value) : value } 
          : item
      )
    );
  };

  // --- HÌNH ẢNH EVENT HANDLERS ---
  const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files.length > 0) {
      const files = Array.from(e.target.files);
      setSelectedImages((prev) => [...prev, ...files]);
    }
  };

  const handleRemoveNewImage = (indexToRemove: number) => {
    setSelectedImages((prev) => prev.filter((_, index) => index !== indexToRemove));
  };

  const handleRemoveExistingImage = (imgId: number) => {
    setExistingImages((prev) => prev.filter((img) => img.id !== imgId));
    setDeletedImageIds((prev) => [...prev, imgId]);
  };

  // --- SUBMIT UPDATE DATA ---
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!id) return;

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

    // Trick Laravel hỗ trợ Method PUT giả lập qua FormData chứa tập tin nhị phân
    data.append('_method', 'PUT'); 

    // 2. Gắn mảng assets cập nhật mới
    selectedAssets.forEach((item, index) => {
      data.append(`assets[${index}][template_id]`, String(item.template_id));
      data.append(`assets[${index}][quantity]`, String(item.quantity));
      data.append(`assets[${index}][note]`, item.note);
    });

    // 3. Gắn các file ảnh mới upload thêm
    selectedImages.forEach((file) => {
      data.append('images[]', file);
    });

    // 4. Gắn mảng danh sách ID ảnh cần xóa bỏ
    deletedImageIds.forEach((imgId) => {
      data.append('deleted_image_ids[]', String(imgId));
    });

    try {
      await updateAdminRoom(Number(id), data);
      alert('Cập nhật thông tin phòng thành công!');
      navigate('/admin/rooms');
    } catch (error) {
      console.error(error);
      alert('Đã xảy ra lỗi cập nhật: ' + error);
    }
  };

  if (isLoading) {
    return (
      <div className="flex h-64 items-center justify-center text-amber-800">
        <div className="h-8 w-8 animate-spin rounded-full border-4 border-amber-600 border-t-transparent"></div>
        <span className="ml-2 font-medium">Đang tải dữ liệu phòng...</span>
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="mx-auto max-w-5xl bg-amber-50 p-6">
      {/* Header */}
      <div className="mb-7 flex items-center gap-3">
        <button type="button" className="flex h-9 w-9 items-center justify-center rounded-full border border-amber-200 bg-white text-amber-700" onClick={() => navigate('/admin/rooms')}>
          <ArrowLeft size={18} />
        </button>
        <div>
          <h1 className="text-2xl font-bold text-amber-900">Cập nhật thông tin phòng</h1>
          <p className="text-sm text-amber-600">Thay đổi thông tin phòng số {formData.room_number}</p>
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
                <option value={2}>Đang bảo trì</option>
                <option value={3}>Ngưng sử dụng</option>
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

      {/* Quản lý Hình ảnh */}
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
              <span className="font-semibold text-amber-700">Tải thêm hình ảnh mới</span> hoặc kéo thả tập tin vào đây
            </p>
            <p className="mt-1 text-xs text-stone-400">PNG, JPG, WEBP</p>
          </label>

          {(existingImages.length > 0 || selectedImages.length > 0) && (
            <div className="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
              {/* 1. Nhóm ảnh cũ trên server */}
              {existingImages.map((img) => {
                const apiBaseUrl = import.meta.env.VITE_BASE_URL_BACKEND || 'http://localhost:8000';
                const cleanPath = img.image_path.startsWith('/') ? img.image_path.slice(1) : img.image_path;
                return (
                  <div key={`exist-${img.id}`} className="group relative aspect-square overflow-hidden rounded-xl border border-amber-100 bg-stone-50">
                    <img src={`${apiBaseUrl}/${cleanPath}`} alt="existing-room-media" className="h-full w-full object-cover opacity-80" />
                    <span className="absolute bottom-2 left-2 rounded bg-amber-700/80 px-1.5 py-0.5 text-[10px] text-white">Ảnh cũ</span>
                    <button
                      type="button"
                      onClick={() => handleRemoveExistingImage(img.id)}
                      className="absolute top-2 right-2 flex h-6 w-6 items-center justify-center rounded-full bg-stone-900/80 text-white transition hover:bg-red-600"
                      title="Xóa vĩnh viễn hình này"
                    >
                      <X size={14} />
                    </button>
                  </div>
                );
              })}

              {/* 2. Nhóm ảnh mới vừa upload */}
              {selectedImages.map((file, index) => {
                const imageUrl = URL.createObjectURL(file);
                return (
                  <div key={`new-${index}`} className="group relative aspect-square overflow-hidden rounded-xl border border-amber-200 bg-stone-50">
                    <img src={imageUrl} alt="new-preview" className="h-full w-full object-cover font-medium transition duration-300 group-hover:scale-105" />
                    <span className="absolute bottom-2 left-2 rounded bg-green-600/80 px-1.5 py-0.5 text-[10px] text-white">Mới thêm</span>
                    <button
                      type="button"
                      onClick={() => handleRemoveNewImage(index)}
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

      {/* Tài sản bàn giao */}
      <div className="mb-4 overflow-hidden rounded-2xl border border-amber-100 bg-white">
        <div className="border-b bg-amber-50 px-5 py-3">
          <span className="text-xs font-semibold uppercase tracking-wider text-amber-800">Tài sản bàn giao</span>
        </div>

        <div className="p-5">
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

          {selectedAssets.length > 0 ? (
            <div className="rounded-xl border border-stone-100 bg-stone-50/50 p-4">
              <p className="mb-3 text-xs font-medium text-stone-500 uppercase tracking-wider">Cấu hình số lượng & ghi chú</p>
              <div className="flex flex-col gap-3">
                {selectedAssets.map((item) => (
                  <div key={item.template_id} className="grid grid-cols-1 items-center gap-3 rounded-xl border border-amber-100 bg-white p-3 sm:grid-cols-12">
                    <div className="sm:col-span-3">
                      {/* Hiển thị chính xác item.name từ template đã gán */}
                      <span className="text-sm font-semibold text-stone-800">{item.name}</span>
                    </div>
                    
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

      {/* Điều hướng Buttons */}
      <div className="flex justify-end gap-3">
        <button type="button" className="rounded-xl border border-amber-200 bg-white px-5 py-2.5 text-stone-600 hover:bg-amber-50" onClick={() => navigate('/admin/rooms')}>Huỷ</button>
        <button type="submit" className="flex items-center gap-2 rounded-xl bg-amber-700 px-5 py-2.5 font-medium text-white hover:bg-amber-800">
          <Save size={18} />
          Cập nhật phòng
        </button>
      </div>
    </form>
  );
}