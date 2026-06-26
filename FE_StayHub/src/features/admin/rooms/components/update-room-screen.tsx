import { ArrowLeft, Building2, ImageIcon, Plus, Save, X, PackageOpen } from 'lucide-react';
import { fetchAssets, fetchBuilding, fetchRoomType, fetchAdminRoomDetail, updateAdminRoom } from '../services/rooms.service';
import { useEffect, useState } from 'react';
import type { AssetResource, BuildingResource, RoomTypeResource, AdminRoomResource } from '../types/rooms.model';
import { useNavigate, useParams } from 'react-router-dom';
import { AdminSelect } from '../../shared/components/AdminSelect';
import { formatMoneyInput, parseMoneyInput } from '../../../../shared/lib/utils/format';

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

const inputClass = 'w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20 disabled:opacity-50';
const labelClass = 'mb-1.5 block px-1 text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/65';

export function UpdateRoomScreen() {
  const { id } = useParams<{ id: string }>(); 
  const navigate = useNavigate();
  
  const [buildings, setBuildings] = useState<BuildingResource[]>([]);
  const [roomTypes, setRoomTypes] = useState<RoomTypeResource[]>([]);
  const [assets, setAssets] = useState<AssetResource[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [isSaving, setIsSaving] = useState<boolean>(false);
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
  const [existingImages, setExistingImages] = useState<ExistingImage[]>([]);
  const [selectedImages, setSelectedImages] = useState<File[]>([]);
  const [deletedImageIds, setDeletedImageIds] = useState<number[]>([]);

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

  const loadRoomDetail = async () => {
    if (!id) return;
    try {
      setIsLoading(true);
      const res = await fetchAdminRoomDetail(Number(id));
      const roomData: AdminRoomResource = res.result;

      setFormData({
        building_id: String(roomData.building?.id ?? ''),
        room_type_id: String(roomData.room_type?.id ?? ''),
        room_number: roomData.room_number,
        floor: String(roomData.floor),
        area_m2: String(roomData.area_m2),
        base_price: formatMoneyInput(String(roomData.base_price)),
        max_occupants: String(roomData.max_occupants),
        status: Number(roomData.status),
        description: roomData.description ?? '',
      });

      if (roomData.images) {
        setExistingImages(roomData.images);
      }

      if (roomData.assets && Array.isArray(roomData.assets)) {
        const mappedAssets = roomData.assets.map((ast: any) => ({
          template_id: ast.asset_template ? Number(ast.asset_template.id) : Number(ast.asset_template_id),
          name: ast.asset_template?.name || 'Tài sản không tên',
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
      [name]: name === 'status' 
        ? Number(value) 
        : (name === 'base_price' ? formatMoneyInput(value) : value)
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

  const handleRemoveNewImage = (indexToRemove: number) => {
    setSelectedImages((prev) => prev.filter((_, index) => index !== indexToRemove));
  };

  const handleRemoveExistingImage = (imgId: number) => {
    setExistingImages((prev) => prev.filter((img) => img.id !== imgId));
    setDeletedImageIds((prev) => [...prev, imgId]);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!id) return;
    setIsSaving(true);
    setErrorMessage(null);

    const data = new FormData();
    data.append('building_id', formData.building_id);
    data.append('room_type_id', formData.room_type_id);
    data.append('room_number', formData.room_number);
    data.append('floor', formData.floor);
    data.append('area_m2', formData.area_m2);
    data.append('base_price', parseMoneyInput(formData.base_price));
    data.append('max_occupants', formData.max_occupants);
    data.append('status', String(formData.status));
    data.append('description', formData.description);
    data.append('_method', 'PUT'); 

    selectedAssets.forEach((item, index) => {
      data.append(`assets[${index}][template_id]`, String(item.template_id));
      data.append(`assets[${index}][quantity]`, String(item.quantity));
      data.append(`assets[${index}][note]`, item.note);
    });

    selectedImages.forEach((file) => {
      data.append('images[]', file);
    });

    deletedImageIds.forEach((imgId) => {
      data.append('deleted_image_ids[]', String(imgId));
    });

    try {
      await updateAdminRoom(Number(id), data);
      alert('Cập nhật thông tin phòng thành công!');
      navigate('/admin/rooms');
    } catch (error: any) {
      console.error(error);
      const msg = error?.response?.data?.message || error?.message || 'Đã xảy ra lỗi khi cập nhật phòng.';
      setErrorMessage(msg);
    } finally {
      setIsSaving(false);
    }
  };

  if (isLoading) {
    return (
      <div className="flex h-64 items-center justify-center text-[#8b5e34]">
        <div className="h-8 w-8 animate-spin rounded-full border-4 border-[#8b5e34] border-t-transparent"></div>
        <span className="ml-2 font-black text-sm">Đang tải dữ liệu phòng...</span>
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-6 text-[#24170d]">
      {/* Premium Header */}
      <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
        <div className="relative p-5 text-[#fff4df] lg:p-6">
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_14%,rgba(243,197,107,0.28),transparent_32%),radial-gradient(circle_at_82%_8%,rgba(15,118,110,0.26),transparent_34%),linear-gradient(135deg,#24170d_0%,#3d2a18_52%,#0f3f3b_100%)]" />
          <div className="relative flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <button
                type="button"
                onClick={() => navigate('/admin/rooms')}
                className="inline-flex items-center gap-2 text-xs font-black text-[#f3c56b] transition hover:text-[#ffd56f]"
              >
                <ArrowLeft className="h-3.5 w-3.5" /> Quay lại danh sách
              </button>
              <h1 className="mt-4 flex items-center gap-3 text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl">
                <Building2 className="h-9 w-9 text-[#f3c56b]" /> Chỉnh sửa thông tin phòng
              </h1>
              <p className="mt-2 max-w-3xl text-sm font-semibold text-[#f8e8c8]/75">
                Cập nhật thông tin phòng số {formData.room_number}.
              </p>
            </div>
            <div className="flex gap-2">
              <button
                type="button"
                onClick={() => navigate('/admin/rooms')}
                className="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/10 px-4 text-sm font-black text-[#fff4df] transition hover:bg-white/20"
              >
                Hủy
              </button>
              <button
                type="submit"
                disabled={isSaving}
                className="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-[#f3c56b] px-4 text-sm font-black text-[#24170d] transition hover:bg-[#ffd56f] disabled:opacity-60"
              >
                <Save className="h-4 w-4" /> {isSaving ? 'Đang lưu...' : 'Cập nhật'}
              </button>
            </div>
          </div>
        </div>
      </section>

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
                <AdminSelect
                  value={formData.building_id}
                  options={buildings.map((b) => ({ value: b.id, label: b.name }))}
                  onChange={(val) => setFormData((prev) => ({ ...prev, building_id: String(val) }))}
                  placeholder="Chọn tòa nhà"
                />
              </div>

              <div>
                <label className={labelClass}>Loại phòng *</label>
                <AdminSelect
                  value={formData.room_type_id}
                  options={roomTypes.map((rt) => ({ value: rt.id, label: rt.name }))}
                  onChange={(val) => setFormData((prev) => ({ ...prev, room_type_id: String(val) }))}
                  placeholder="Chọn loại phòng"
                />
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
                    type="text" name="base_price" value={formData.base_price} onChange={handleInputChange} required
                    className={inputClass} 
                  />
                  <span className="absolute top-1/2 right-4 -translate-y-1/2 text-xs font-black text-[#8b5e34]">VNĐ</span>
                </div>
              </div>

              <div>
                <label className={labelClass}>Trạng thái</label>
                <AdminSelect
                  value={formData.status}
                  options={[
                    { value: 1, label: 'Hoạt động', tone: 'success' },
                    { value: 2, label: 'Đang bảo trì', tone: 'warning' },
                    { value: 3, label: 'Ngưng sử dụng', tone: 'danger' }
                  ]}
                  onChange={(val) => setFormData((prev) => ({ ...prev, status: Number(val) }))}
                  placeholder="Chọn trạng thái"
                />
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
                  <span className="text-[#8b5e34]">Tải thêm hình ảnh mới</span> hoặc kéo thả
                </p>
                <p className="mt-1 text-[10px] text-stone-400">PNG, JPG, WEBP</p>
              </label>

              {(existingImages.length > 0 || selectedImages.length > 0) && (
                <div className="mt-5 grid grid-cols-2 gap-3">
                  {/* Nhóm ảnh cũ trên server */}
                  {existingImages.map((img) => {
                    const apiBaseUrl = import.meta.env.VITE_BASE_URL_BACKEND || 'http://localhost:8000';
                    const cleanPath = img.image_path.startsWith('/') ? img.image_path.slice(1) : img.image_path;
                    return (
                      <div key={`exist-${img.id}`} className="group relative aspect-square overflow-hidden rounded-xl border border-[#3d2a18]/10 bg-stone-50">
                        <img src={`${apiBaseUrl}/${cleanPath}`} alt="existing-room-media" className="h-full w-full object-cover opacity-80" />
                        <span className="absolute bottom-2 left-2 rounded bg-[#24170d]/80 px-1.5 py-0.5 text-[9px] font-black text-white uppercase tracking-wider">Ảnh cũ</span>
                        <button
                          type="button"
                          onClick={() => handleRemoveExistingImage(img.id)}
                          className="absolute top-2 right-2 flex h-6 w-6 items-center justify-center rounded-full bg-stone-900/80 text-white transition hover:bg-red-600"
                          title="Xóa hình này"
                        >
                          <X size={14} />
                        </button>
                      </div>
                    );
                  })}

                  {/* Nhóm ảnh mới vừa upload */}
                  {selectedImages.map((file, index) => {
                    const imageUrl = URL.createObjectURL(file);
                    return (
                      <div key={`new-${index}`} className="group relative aspect-square overflow-hidden rounded-xl border border-[#3d2a18]/10 bg-stone-50">
                        <img src={imageUrl} alt="new-preview" className="h-full w-full object-cover" />
                        <span className="absolute bottom-2 left-2 rounded bg-green-600/80 px-1.5 py-0.5 text-[9px] font-black text-white uppercase tracking-wider">Mới</span>
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
          </section>
        </div>
      </div>
    </form>
  );
}
