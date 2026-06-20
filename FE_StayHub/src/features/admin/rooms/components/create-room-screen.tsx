import { ArrowLeft, Building2, ImageIcon, Plus, Save, X, PackageOpen } from 'lucide-react';
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

interface SelectedAssetItem {
  template_id: number;
  name: string; 
  quantity: number;
  note: string;
}

const inputClass = 'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20 disabled:opacity-50';
const labelClass = 'mb-1.5 block px-1 text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/65';

export function CreateRoomScreen() {
  const [buildings, setBuildings] = useState<BuildingResource[]>([]);
  const [roomTypes, setRoomTypes] = useState<RoomTypeResource[]>([]);
  const [assets, setAssets] = useState<AssetResource[]>([]);
  const navigate = useNavigate();
  const [isSaving, setIsSaving] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

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

  const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files.length > 0) {
      const files = Array.from(e.target.files);
      setSelectedImages((prev) => [...prev, ...files]);
    }
  };

  const handleRemoveImage = (indexToRemove: number) => {
    setSelectedImages((prev) => prev.filter((_, index) => index !== indexToRemove));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSaving(true);
    setErrorMessage(null);

    const data = new FormData();
    data.append('building_id', formData.building_id);
    data.append('room_type_id', formData.room_type_id);
    data.append('room_number', formData.room_number);
    data.append('floor', formData.floor);
    data.append('area_m2', formData.area_m2);
    data.append('base_price', formData.base_price);
    data.append('max_occupants', formData.max_occupants);
    data.append('status', String(formData.status));
    data.append('description', formData.description);

    selectedAssets.forEach((item, index) => {
      data.append(`assets[${index}][template_id]`, String(item.template_id));
      data.append(`assets[${index}][quantity]`, String(item.quantity));
      data.append(`assets[${index}][note]`, item.note);
    });

    selectedImages.forEach((file) => {
      data.append('images[]', file);
    });

    try {
      await createAdminRoom(data);
      alert('Tạo phòng mới thành công!');
      navigate('/admin/rooms');
    } catch (error: any) {
      console.error(error);
      const msg = error?.response?.data?.message || error?.message || 'Đã xảy ra lỗi khi tạo phòng.';
      setErrorMessage(msg);
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-6 text-[#24170d]">
      {/* Header */}
      <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <button type="button" className="mb-3 inline-flex items-center gap-2 text-sm font-bold text-[#8b5e34] hover:text-[#24170d] transition" onClick={() => navigate('/admin/rooms')}>
            <ArrowLeft size={16} /> Quay lại danh sách
          </button>
          <h1 className="text-3xl font-black tracking-tight text-[#24170d]">Thêm phòng mới</h1>
          <p className="text-sm text-[#8b5e34]/70">Điền đầy đủ thông tin để tạo phòng.</p>
        </div>
        <div className="flex gap-2">
          <button type="button" className="inline-flex items-center justify-center rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-5 py-3 text-xs font-black uppercase tracking-widest text-[#6f6254] transition hover:bg-[#efe2cf]" onClick={() => navigate('/admin/rooms')}>
            Hủy
          </button>
          <button type="submit" disabled={isSaving} className="inline-flex items-center justify-center gap-2 rounded-2xl bg-[#24170d] px-5 py-3 text-xs font-black uppercase tracking-widest text-[#fff4df] shadow-xl shadow-[#24170d]/10 transition hover:bg-[#3d2a18] disabled:opacity-50">
            <Save size={16} className="text-[#f3c56b] stroke-[2.8]" />
            {isSaving ? 'Đang lưu...' : 'Lưu phòng'}
          </button>
        </div>
      </div>

      {errorMessage && (
        <div className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-700">
          Lỗi: {errorMessage}
        </div>
      )}

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* Left column - Basic Information */}
        <div className="lg:col-span-2 space-y-6">
          <section className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 p-6 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
            <div className="mb-6 flex items-center gap-3 border-b border-[#3d2a18]/10 pb-4">
              <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#24170d] text-[#fff4df]">
                <Building2 className="h-5 w-5 text-[#f3c56b]" />
              </div>
              <div>
                <h2 className="font-black text-[#24170d]">Thông tin cơ bản</h2>
                <p className="mt-0.5 text-xs font-semibold text-[#8b5e34]/70">Cấu hình thông tin vị trí, số phòng, diện tích và đơn giá.</p>
              </div>
            </div>

            <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
              <div>
                <label className={labelClass}>Tòa nhà *</label>
                <select 
                  name="building_id" value={formData.building_id} onChange={handleInputChange} required
                  className={inputClass}
                >
                  <option value="">Chọn tòa nhà</option>          
                  {buildings.map((building) => (
                    <option key={building.id} value={building.id}>{building.name}</option>
                  ))}
                </select>
              </div>

              <div>
                <label className={labelClass}>Loại phòng *</label>
                <select 
                  name="room_type_id" value={formData.room_type_id} onChange={handleInputChange} required
                  className={inputClass}
                >
                  <option value="">Chọn loại phòng</option>
                  {roomTypes.map((roomType)=>(
                    <option key={roomType.id} value={roomType.id}>{roomType.name}</option>
                  ))}        
                </select>
              </div>

              <div>
                <label className={labelClass}>Số / Tên phòng *</label>
                <input 
                  name="room_number" value={formData.room_number} onChange={handleInputChange} required placeholder="VD: A101" 
                  className={inputClass} 
                />
              </div>

              <div>
                <label className={labelClass}>Tầng *</label>
                <input 
                  type="number" name="floor" value={formData.floor} onChange={handleInputChange} required
                  className={inputClass} 
                />
              </div>

              <div>
                <label className={labelClass}>Diện tích (m²)</label>
                <input 
                  type="number" step="0.01" name="area_m2" value={formData.area_m2} onChange={handleInputChange} required
                  className={inputClass} 
                />
              </div>

              <div>
                <label className={labelClass}>Số người tối đa</label>
                <input 
                  type="number" name="max_occupants" value={formData.max_occupants} onChange={handleInputChange} required
                  className={inputClass} 
                />
              </div>

              <div>
                <label className={labelClass}>Giá phòng cơ bản</label>
                <div className="relative font-bold text-[#3d2a18]">
                  <input 
                    type="number" name="base_price" value={formData.base_price} onChange={handleInputChange} required
                    className={inputClass} 
                    placeholder="3500000" 
                  />
                  <span className="absolute top-1/2 right-4 -translate-y-1/2 text-xs font-black text-[#8b5e34]">VNĐ</span>
                </div>
              </div>

              <div>
                <label className={labelClass}>Trạng thái</label>
                <select 
                  name="status" value={formData.status} onChange={handleInputChange}
                  className={inputClass}
                >
                  <option value={1}>Hoạt động</option>
                  <option value={3}>Bảo trì</option>
                  <option value={2}>Đang ở</option>
                </select>
              </div>

              <div className="md:col-span-2">
                <label className={labelClass}>Mô tả</label>
                <textarea 
                  name="description" value={formData.description} onChange={handleInputChange} rows={3} 
                  className={inputClass} 
                />
              </div>
            </div>
          </section>

          {/* Tài sản bàn giao */}
          <section className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 p-6 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
            <div className="mb-6 flex items-center gap-3 border-b border-[#3d2a18]/10 pb-4">
              <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#24170d] text-[#fff4df]">
                <PackageOpen className="h-5 w-5 text-[#f3c56b]" />
              </div>
              <div>
                <h2 className="font-black text-[#24170d]">Tài sản bàn giao</h2>
                <p className="mt-0.5 text-xs font-semibold text-[#8b5e34]/70">Chọn các trang thiết bị bàn giao kèm theo phòng này.</p>
              </div>
            </div>

            <div>
              {/* Danh sách các tài sản để click chọn */}
              <div className="mb-5 flex flex-wrap gap-2">
                {assets.map((asset) => {
                  const isChecked = selectedAssets.some((item) => item.template_id === asset.id);
                  return (
                    <button
                      type="button"
                      key={asset.id}
                      onClick={() => handleToggleAsset(asset)}
                      className={`rounded-xl border px-3 py-1.5 text-xs font-bold transition ${
                        isChecked 
                          ? 'border-[#f3c56b] bg-[#f3c56b] text-[#24170d]' 
                          : 'border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] hover:bg-[#efe2cf]'
                      }`}
                    >
                      {isChecked ? '✓ ' : '+ '} {asset.name}
                    </button>
                  );
                })}
              </div>

              {/* Danh sách cấu hình chi tiết tài sản đã chọn */}
              {selectedAssets.length > 0 ? (
                <div className="rounded-2xl border border-[#3d2a18]/10 bg-[#efe2cf]/25 p-4 space-y-3">
                  <p className="text-[10px] font-black text-[#8b5e34] uppercase tracking-wider">Cấu hình số lượng & ghi chú</p>
                  <div className="flex flex-col gap-3">
                    {selectedAssets.map((item) => (
                      <div key={item.template_id} className="grid grid-cols-1 items-center gap-3 rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] p-3 sm:grid-cols-12">
                        {/* Tên tài sản */}
                        <div className="sm:col-span-3">
                          <span className="text-xs font-black text-[#24170d]">{item.name}</span>
                        </div>
                        
                        {/* Nhập Số lượng */}
                        <div className="flex items-center gap-2 sm:col-span-3">
                          <label className="text-[10px] font-bold text-[#8b5e34] whitespace-nowrap">SL:</label>
                          <input
                            type="number"
                            min={1}
                            value={item.quantity}
                            required
                            onChange={(e) => handleAssetFieldChange(item.template_id, 'quantity', e.target.value)}
                            className="w-full rounded-lg border border-[#3d2a18]/10 bg-[#fffaf1] px-2 py-1 text-xs font-bold focus:border-[#f3c56b] focus:outline-none"
                          />
                        </div>

                        {/* Nhập Ghi chú */}
                        <div className="flex items-center gap-2 sm:col-span-5">
                          <label className="text-[10px] font-bold text-[#8b5e34] whitespace-nowrap">Ghi chú:</label>
                          <input
                            type="text"
                            placeholder="Tình trạng, nhãn hiệu..."
                            value={item.note}
                            maxLength={500}
                            onChange={(e) => handleAssetFieldChange(item.template_id, 'note', e.target.value)}
                            className="w-full rounded-lg border border-[#3d2a18]/10 bg-[#fffaf1] px-2 py-1 text-xs font-bold focus:border-[#f3c56b] focus:outline-none"
                          />
                        </div>

                        {/* Nút Xóa nhanh */}
                        <div className="text-right sm:col-span-1">
                          <button
                            type="button"
                            onClick={() => setSelectedAssets((prev) => prev.filter((i) => i.template_id !== item.template_id))}
                            className="text-[#8b5e34] hover:text-red-600 transition"
                          >
                            <X size={16} />
                          </button>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              ) : (
                <p className="text-center text-xs font-bold text-[#8b5e34]/70 py-4 italic">Chưa chọn tài sản nào cho phòng này.</p>
              )}
            </div>
          </section>
        </div>

        {/* Right column - Upload Image */}
        <div className="space-y-6">
          <section className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/92 p-6 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
            <div className="mb-6 flex items-center gap-3 border-b border-[#3d2a18]/10 pb-4">
              <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#24170d] text-[#fff4df]">
                <ImageIcon className="h-5 w-5 text-[#f3c56b]" />
              </div>
              <div>
                <h2 className="font-black text-[#24170d]">Hình ảnh phòng</h2>
                <p className="mt-0.5 text-xs font-semibold text-[#8b5e34]/70">Tải lên hình ảnh phòng thực tế.</p>
              </div>
            </div>

            <div>
              <label className="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-[#3d2a18]/15 bg-[#efe2cf]/25 py-6 px-4 text-center cursor-pointer transition hover:bg-[#efe2cf]/45 hover:border-[#f3c56b] group">
                <input type="file" multiple accept="image/*" className="hidden" onChange={handleImageChange} />
                <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-2xl bg-[#24170d] text-[#fff4df] transition group-hover:scale-110">
                  <Plus size={20} className="text-[#f3c56b]" />
                </div>
                <p className="text-xs font-bold text-[#3d2a18]">
                  <span className="text-[#8b5e34]">Nhấn để tải ảnh lên</span> hoặc kéo thả
                </p>
                <p className="mt-1 text-[10px] text-stone-400">PNG, JPG, WEBP</p>
              </label>

              {selectedImages.length > 0 && (
                <div className="mt-5 grid grid-cols-2 gap-3">
                  {selectedImages.map((file, index) => {
                    const imageUrl = URL.createObjectURL(file);
                    return (
                      <div key={index} className="group relative aspect-square overflow-hidden rounded-xl border border-[#3d2a18]/10 bg-stone-50">
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
          </section>
        </div>
      </div>
    </form>
  );
}
