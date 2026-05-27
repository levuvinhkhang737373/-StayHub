import { useEffect, useMemo, useState } from "react";
import { Link, Navigate, useNavigate, useParams } from "react-router-dom";
import { ArrowLeft, Building2, ImagePlus, Save, Star, Trash2, X } from "lucide-react";
import { isSuperAdminRole, useAdminSession } from "../../auth/hooks/use-admin-session";
import { createAdminBuilding, fetchAdminBuildingDetail, fetchAdminManagers, fetchAdminRegions, updateAdminBuilding } from "../services/facilities.service";
import type { AdminBuildingPayload, AdminManagerResource, AdminRegionResource } from "../types/facility-api.model";
import type { BuildingImage } from "../types/building.model";
import { AdminSelect } from "../../shared/components/AdminSelect";
import { validateBuildingForm, type BuildingFormErrors } from "../validations/building.validation";

function getResourceList<T>(result: { data?: T[] } | T[] | null | undefined): T[] {
    if (!result) return [];
    if (Array.isArray(result)) return result;
    return result.data || [];
}

const stayHubImage = "/images/stayhub.png";

const inputClass = "w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-900 outline-none transition focus:border-gray-900";
const inputErrorClass = "border-rose-300 bg-rose-50 focus:border-rose-400";
const labelClass = "mb-1.5 block px-1 text-[10px] font-black uppercase tracking-widest text-gray-400";

const buildingStatusOptions = [
    { value: 1, label: "Đang hoạt động", tone: "success" as const },
    { value: 2, label: "Ngừng hoạt động", tone: "danger" as const },
    { value: 3, label: "Bảo trì", tone: "warning" as const },
];

const genderPolicyOptions = [
    { value: 1, label: "Hỗn hợp", tone: "default" as const },
    { value: 2, label: "Nam", tone: "default" as const },
    { value: 3, label: "Nữ", tone: "default" as const },
];

function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <p className="mt-2 px-1 text-xs font-black text-rose-600" role="alert">{message}</p>;
}

export function CreateBuildingScreen({ buildingId }: { buildingId?: number }) {
    const navigate = useNavigate();
    const { buildingId: routeBuildingId } = useParams();
    const numericRouteBuildingId = routeBuildingId ? Number(routeBuildingId) : undefined;
    const resolvedBuildingId = typeof buildingId === "number" ? buildingId : Number.isFinite(numericRouteBuildingId) ? numericRouteBuildingId : undefined;
    const { session } = useAdminSession();
    const isSuperAdmin = isSuperAdminRole(session?.admin.role);
    const [regions, setRegions] = useState<AdminRegionResource[]>([]);
    const [managers, setManagers] = useState<AdminManagerResource[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [isSaving, setIsSaving] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [errors, setErrors] = useState<BuildingFormErrors>({});
    const [imageFiles, setImageFiles] = useState<File[]>([]);
    const [existingImages, setExistingImages] = useState<BuildingImage[]>([]);
    const [deleteImageIds, setDeleteImageIds] = useState<number[]>([]);
    const [primaryImageId, setPrimaryImageId] = useState<number | null>(null);
    const [primaryNewImageIndex, setPrimaryNewImageIndex] = useState<number | null>(null);
    const [form, setForm] = useState({
        name: "",
        address: "",
        region_id: "",
        manager_admin_id: "",
        status: 1,
        gender_policy: 1,
        description: "",
        total_floors: 1,
    });

    const isEditMode = typeof resolvedBuildingId === "number";
    const activeRegions = useMemo(() => regions.filter((region) => region.status), [regions]);
    const regionOptions = useMemo(() => [
        { value: "", label: "Chọn khu vực", tone: "default" as const },
        ...activeRegions.map((region) => ({ value: region.id, label: region.name, tone: "default" as const })),
    ], [activeRegions]);
    const managerOptions = useMemo(() => [
        { value: "", label: "Chưa phân công", tone: "warning" as const },
        ...managers.map((manager) => ({ value: manager.id, label: manager.full_name, description: manager.phone || manager.username, tone: "default" as const })),
    ], [managers]);
    const visibleExistingImages = useMemo(() => existingImages.filter((image) => !deleteImageIds.includes(image.id)), [deleteImageIds, existingImages]);

    useEffect(() => {
        if (!isSuperAdmin) return;

        Promise.all([
            fetchAdminRegions({ per_page: 100 }),
            fetchAdminManagers(),
            isEditMode && resolvedBuildingId ? fetchAdminBuildingDetail(resolvedBuildingId) : Promise.resolve(null),
        ])
            .then(([regionsResponse, managersResponse, buildingResponse]) => {
                const nextRegions = getResourceList(regionsResponse.result);
                const nextManagers = getResourceList(managersResponse.result);
                setRegions(nextRegions);
                setManagers(nextManagers);

                const building = buildingResponse?.result;
                const nextImages = building?.images || [];
                setExistingImages(nextImages);
                setPrimaryImageId(building?.primary_image?.id || nextImages.find((image) => image.is_primary)?.id || null);

                setForm((current) => ({
                    ...current,
                    name: building?.name || current.name,
                    address: building?.address || current.address,
                    region_id: String(building?.region_id || nextRegions.find((region) => region.status)?.id || ""),
                    manager_admin_id: building?.manager_admin_id ? String(building.manager_admin_id) : "",
                    status: Number(building?.status || current.status),
                    gender_policy: Number(building?.gender_policy || current.gender_policy),
                    description: building?.description || current.description,
                    total_floors: building?.total_floors || current.total_floors,
                }));
            })
            .catch((error) => setErrorMessage(error instanceof Error ? error.message : "Không thể tải dữ liệu tòa nhà."))
            .finally(() => setIsLoading(false));
    }, [isEditMode, isSuperAdmin, resolvedBuildingId]);

    const updateForm = (key: keyof typeof form, value: string | number) => {
        setForm((current) => ({ ...current, [key]: value }));
        setErrors((current) => ({ ...current, [key]: undefined }));
    };

    const imagePreviewUrls = useMemo(() => imageFiles.map((file) => URL.createObjectURL(file)), [imageFiles]);

    useEffect(() => {
        return () => {
            imagePreviewUrls.forEach((url) => URL.revokeObjectURL(url));
        };
    }, [imagePreviewUrls]);

    const updateImages = (files: FileList | null) => {
        if (!files) return;
        setImageFiles((current) => [...current, ...Array.from(files)].slice(0, 20));
        setErrors((current) => ({ ...current, images: undefined }));
    };

    const removeNewImage = (index: number) => {
        setImageFiles((current) => current.filter((_, itemIndex) => itemIndex !== index));
        setPrimaryNewImageIndex((current) => {
            if (current === index) return null;
            if (current !== null && current > index) return current - 1;
            return current;
        });
    };

    const removeExistingImage = (image: BuildingImage) => {
        setDeleteImageIds((current) => (current.includes(image.id) ? current : [...current, image.id]));
        if (primaryImageId === image.id) {
            const nextPrimary = visibleExistingImages.find((item) => item.id !== image.id)?.id || null;
            setPrimaryImageId(nextPrimary);
            setPrimaryNewImageIndex(nextPrimary ? null : 0);
        }
    };

    const chooseExistingPrimary = (imageId: number) => {
        setPrimaryImageId(imageId);
        setPrimaryNewImageIndex(null);
    };

    const chooseNewPrimary = (index: number) => {
        if (visibleExistingImages.length > 0) return;
        setPrimaryImageId(null);
        setPrimaryNewImageIndex(index);
    };

    const submit = async () => {
        if (!isSuperAdmin || isSaving) return;

        const nextErrors = validateBuildingForm(form, regions, managers, imageFiles);
        setErrors(nextErrors);

        if (Object.keys(nextErrors).length > 0) {
            setErrorMessage("Vui lòng kiểm tra lại thông tin tòa nhà.");
            return;
        }

        try {
            setIsSaving(true);
            setErrorMessage(null);

            const fallbackNewPrimary = imageFiles.length > 0 && visibleExistingImages.length === 0 && primaryNewImageIndex === null ? 0 : primaryNewImageIndex;
            const payload: AdminBuildingPayload = {
                region_id: Number(form.region_id),
                manager_admin_id: form.manager_admin_id ? Number(form.manager_admin_id) : undefined,
                name: form.name.trim(),
                address: form.address.trim() || undefined,
                description: form.description.trim() || undefined,
                total_floors: form.total_floors,
                gender_policy: Number(form.gender_policy),
                status: Number(form.status),
                images: imageFiles,
                image_metadata: imageFiles.map((_, index) => ({
                    is_primary: fallbackNewPrimary === index,
                    sort_order: visibleExistingImages.length + index,
                    status: 1,
                })),
                delete_image_ids: deleteImageIds,
                primary_image_id: primaryImageId || undefined,
            };

            if (isEditMode && resolvedBuildingId) {
                await updateAdminBuilding(resolvedBuildingId, payload);
            } else {
                await createAdminBuilding(payload);
            }

            navigate("/admin/facilities");
        } catch (error) {
            setErrorMessage(error instanceof Error ? error.message : "Không thể lưu tòa nhà.");
        } finally {
            setIsSaving(false);
        }
    };

    if (!isSuperAdmin) {
        return <Navigate to="/admin/dashboard" replace />;
    }

    return (
        <div className="space-y-6">
            <div>
                <Link to="/admin/facilities" className="mb-3 inline-flex items-center gap-2 text-sm font-bold text-gray-500 hover:text-gray-900">
                    <ArrowLeft className="h-4 w-4" /> Quay lại danh sách
                </Link>
                <h1 className="text-2xl font-black tracking-tight text-gray-900">{isEditMode ? "Chỉnh sửa Tòa nhà" : "Thêm Tòa nhà"}</h1>
                <p className="mt-1 text-sm text-gray-500">{isEditMode ? "Cập nhật thông tin cơ bản và ảnh tòa nhà." : "Khai báo thông tin cơ bản và ảnh ban đầu cho tòa nhà."}</p>
            </div>

            {errorMessage && <div className="rounded-2xl border border-rose-100 bg-rose-50 p-4 text-sm font-bold text-rose-600">{errorMessage}</div>}

            <div className="rounded-[32px] border border-gray-100 bg-white p-6 shadow-sm">
                <div className="mb-6 flex items-center gap-3 border-b border-gray-100 pb-4">
                    <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-900 text-white"><Building2 className="h-5 w-5" /></div>
                    <div>
                        <h2 className="font-black text-gray-900">Thông tin tòa nhà</h2>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
                    <div>
                        <label className={labelClass}>Tên tòa nhà</label>
                        <input className={`${inputClass} ${errors.name ? inputErrorClass : ""}`} value={form.name} aria-invalid={!!errors.name} onChange={(event) => updateForm("name", event.target.value)} />
                        <FieldError message={errors.name} />
                    </div>
                    <div className="lg:col-span-2">
                        <label className={labelClass}>Địa chỉ</label>
                        <textarea className={`${inputClass} min-h-24 ${errors.address ? inputErrorClass : ""}`} value={form.address} aria-invalid={!!errors.address} onChange={(event) => updateForm("address", event.target.value)} />
                        <FieldError message={errors.address} />
                    </div>
                    <div>
                        <label className={labelClass}>Khu vực</label>
                        <AdminSelect value={form.region_id} options={regionOptions} invalid={!!errors.region_id} onChange={(nextValue) => updateForm("region_id", String(nextValue))} />
                        <FieldError message={errors.region_id} />
                    </div>
                    <div>
                        <label className={labelClass}>Người quản lý</label>
                        <AdminSelect value={form.manager_admin_id} options={managerOptions} invalid={!!errors.manager_admin_id} onChange={(nextValue) => updateForm("manager_admin_id", String(nextValue))} />
                        <FieldError message={errors.manager_admin_id} />
                    </div>
                    <div>
                        <label className={labelClass}>Tổng số tầng</label>
                        <input type="number" min="1" max="1000" className={`${inputClass} ${errors.total_floors ? inputErrorClass : ""}`} value={form.total_floors} aria-invalid={!!errors.total_floors} onChange={(event) => updateForm("total_floors", Number(event.target.value))} />
                        <FieldError message={errors.total_floors} />
                    </div>
                    <div>
                        <label className={labelClass}>Trạng thái</label>
                        <AdminSelect value={form.status} options={buildingStatusOptions} invalid={!!errors.status} onChange={(nextValue) => updateForm("status", Number(nextValue))} />
                        <FieldError message={errors.status} />
                    </div>
                    <div>
                        <label className={labelClass}>Giới tính</label>
                        <AdminSelect value={form.gender_policy} options={genderPolicyOptions} invalid={!!errors.gender_policy} onChange={(nextValue) => updateForm("gender_policy", Number(nextValue))} />
                        <FieldError message={errors.gender_policy} />
                    </div>
                    <div className="lg:col-span-2">
                        <label className={labelClass}>Mô tả</label>
                        <textarea className={`${inputClass} min-h-28 ${errors.description ? inputErrorClass : ""}`} value={form.description} aria-invalid={!!errors.description} onChange={(event) => updateForm("description", event.target.value)} />
                        <FieldError message={errors.description} />
                    </div>
                </div>

                <div className="mt-6 rounded-3xl border border-dashed border-gray-200 bg-gray-50 p-5">
                    <div className="mb-4 flex items-center justify-between gap-4">
                        <div>
                            <label className={labelClass}>Ảnh tòa nhà</label>
                            <p className="text-xs font-semibold text-gray-400">Tải tối đa 20 ảnh, định dạng JPG/PNG/WEBP, mỗi ảnh tối đa 10MB.</p>
                        </div>
                        <label className="inline-flex cursor-pointer items-center gap-2 rounded-2xl bg-gray-900 px-4 py-3 text-xs font-black uppercase tracking-widest text-white transition hover:bg-gray-800">
                            <ImagePlus className="h-4 w-4 text-white" />
                            Chọn ảnh
                            <input type="file" accept="image/jpeg,image/png,image/webp" multiple className="hidden" onChange={(event) => updateImages(event.target.files)} />
                        </label>
                    </div>
                    <FieldError message={errors.images} />
                    {visibleExistingImages.length > 0 && imagePreviewUrls.length > 0 && <p className="mt-2 px-1 text-xs font-bold text-gray-400">Ảnh mới sẽ được thêm sau ảnh hiện tại. Nếu muốn đặt ảnh mới làm ảnh chính, hãy xóa ảnh chính hiện tại trước.</p>}

                    {(visibleExistingImages.length > 0 || imagePreviewUrls.length > 0) && (
                        <div className="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                            {visibleExistingImages.map((image) => (
                                <div key={image.id} className="relative overflow-hidden rounded-2xl border border-gray-200 bg-white">
                                    <img src={image.image_url || stayHubImage} alt="Ảnh hiện tại" onError={(event) => { event.currentTarget.src = stayHubImage }} className="h-32 w-full object-cover" />
                                    <div className="absolute left-2 top-2 flex gap-1">
                                        <button type="button" onClick={() => chooseExistingPrimary(image.id)} className={`rounded-full px-2 py-1 text-[10px] font-black text-white ${primaryImageId === image.id ? "bg-amber-500" : "bg-black/60"}`}>
                                            <Star className="mr-1 inline h-3 w-3" /> Chính
                                        </button>
                                    </div>
                                    <button type="button" onClick={() => removeExistingImage(image)} className="absolute right-2 top-2 rounded-full bg-black/60 p-1.5 text-white transition hover:bg-rose-600">
                                        <Trash2 className="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            ))}
                            {imagePreviewUrls.map((url, index) => (
                                <div key={url} className="relative overflow-hidden rounded-2xl border border-gray-200 bg-white">
                                    <img src={url} alt={`Ảnh mới ${index + 1}`} className="h-32 w-full object-cover" />
                                    <button type="button" onClick={() => chooseNewPrimary(index)} disabled={visibleExistingImages.length > 0} className={`absolute left-2 top-2 rounded-full px-2 py-1 text-[10px] font-black text-white disabled:cursor-not-allowed disabled:opacity-70 ${primaryNewImageIndex === index ? "bg-amber-500" : "bg-blue-600"}`}>
                                        <Star className="mr-1 inline h-3 w-3" /> {primaryNewImageIndex === index ? "Chính" : "Mới"}
                                    </button>
                                    <button type="button" onClick={() => removeNewImage(index)} className="absolute right-2 top-2 rounded-full bg-black/60 p-1.5 text-white transition hover:bg-rose-600">
                                        <X className="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                <div className="mt-8 flex flex-col gap-3 border-t border-gray-100 pt-6 sm:flex-row sm:items-center sm:justify-end">
                    <Link to="/admin/facilities" className="inline-flex items-center justify-center rounded-2xl border border-gray-200 px-6 py-3 text-sm font-black uppercase tracking-widest text-gray-500 transition hover:bg-gray-50 hover:text-gray-900">
                        Hủy
                    </Link>
                    <button onClick={submit} disabled={isSaving || isLoading} className="inline-flex items-center justify-center gap-2 rounded-2xl bg-[#333333] px-6 py-3 text-sm font-black uppercase tracking-widest text-white shadow-xl shadow-black/10 transition hover:bg-gray-800 disabled:opacity-50">
                        <Save className="h-4 w-4 text-white stroke-[2.8]" />
                        <span className="font-black text-white">{isSaving ? "Đang lưu..." : isEditMode ? "Cập nhật tòa nhà" : "Lưu tòa nhà"}</span>
                    </button>
                </div>
            </div>
        </div>
    );
}
