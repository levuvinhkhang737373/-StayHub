import { useEffect, useMemo, useState } from "react";
import type { ElementType, ReactNode } from "react";
import { Navigate, useNavigate, useParams } from "react-router-dom";
import { ArrowLeft, Building2, ChevronRight, ImagePlus, MapPin, Plus, Save, Search, Settings, Star, Trash2, X, Zap } from "lucide-react";
import { isSuperAdminRole, useAdminSession } from "../../auth/hooks/use-admin-session";
import { RegionModal } from "./region-modal";
import { createAdminService, fetchAdminServices } from "../../services/services/services.service";
import type { AdminServiceResource } from "../../services/types/service-api.model";
import { createAdminSetting, fetchAdminSettings } from "../../settings/services/settings.service";
import type { AdminSettingResource } from "../../settings/types/setting-api.model";
import { AdminSelect } from "../../shared/components/AdminSelect";
import { getVisibleErrorMessage } from "../../shared/utils/error-message";
import { createAdminBuilding, fetchAdminBuildingDetail, fetchAdminManagers, fetchAdminRegions, updateAdminBuilding } from "../services/facilities.service";
import { createAdminAccount } from "../../system-users/services/admin-accounts.service";
import type { AdminManagerResource, AdminRegionResource } from "../types/facility-api.model";
import type { BuildingImage } from "../types/building.model";
import { buildBuildingPayload, createDefaultBuildingForm, getTodayIsoDate, mapBuildingDetailToForm } from "../utils/building-form.utils";
import {
    validateBuildingForm,
    type BuildingFormErrors,
    type BuildingServicePriceFormRow,
    type BuildingSettingFormRow,
} from "../validations/building.validation";
import { ImageViewerModal } from "../../../../shared/components/ImageViewerModal";
import { formatMoneyInput } from "../../../../shared/lib/utils/format";

function getResourceList<T>(result: { data?: T[] } | T[] | null | undefined): T[] {
    if (!result) return [];
    if (Array.isArray(result)) return result;
    return result.data || [];
}

const stayHubImage = "/images/stayhub.png";
const inputClass = "w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-900 outline-none transition focus:border-gray-900";
const inputErrorClass = "border-rose-300 bg-rose-50 focus:border-rose-400";
const labelClass = "mb-1.5 block px-1 text-[10px] font-black uppercase tracking-widest text-gray-400";
const cardClass = "rounded-[32px] border border-gray-100 bg-white p-6 shadow-sm";

type ConfigKey = "service_prices" | "settings";

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


const servicePriceStatusOptions = [
    { value: 1, label: "Còn hiệu lực", tone: "success" as const },
    { value: 2, label: "Hết hiệu lực", tone: "danger" as const },
];



const chargeMethodOptions = [
    { value: 1, label: "Theo chỉ số", tone: "default" as const },
    { value: 2, label: "Theo người", tone: "default" as const },
    { value: 3, label: "Theo phòng", tone: "default" as const },
    { value: 4, label: "Theo xe", tone: "default" as const },
    { value: 5, label: "Cố định", tone: "default" as const },
];

function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <p className="mt-2 px-1 text-xs font-black text-rose-600" role="alert">{message}</p>;
}

export function CreateBuildingScreen({ buildingId }: { buildingId?: number }) {
    const navigate = useNavigate();

    const isRequiredService = (service?: AdminServiceResource | null) => {
        if (!service) return false;
        const slug = String(service.slug || "").toLowerCase().trim();
        return slug.includes("dien") || slug.includes("nuoc");
    };
    const { buildingId: routeBuildingId } = useParams();
    const numericRouteBuildingId = routeBuildingId ? Number(routeBuildingId) : undefined;
    const resolvedBuildingId = typeof buildingId === "number" ? buildingId : Number.isFinite(numericRouteBuildingId) ? numericRouteBuildingId : undefined;
    const { session } = useAdminSession();
    const isSuperAdmin = isSuperAdminRole(session?.admin.role);
    const [regions, setRegions] = useState<AdminRegionResource[]>([]);
    const [managers, setManagers] = useState<AdminManagerResource[]>([]);
    const [services, setServices] = useState<AdminServiceResource[]>([]);
    const [settingCatalog, setSettingCatalog] = useState<AdminSettingResource[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [isSaving, setIsSaving] = useState(false);
    const [isCreatingService, setIsCreatingService] = useState(false);
    const [isCreatingSetting, setIsCreatingSetting] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [errors, setErrors] = useState<BuildingFormErrors>({});
    const [imageFiles, setImageFiles] = useState<File[]>([]);
    const [existingImages, setExistingImages] = useState<BuildingImage[]>([]);
    const [deleteImageIds, setDeleteImageIds] = useState<number[]>([]);
    const [deleteServicePriceIds, setDeleteServicePriceIds] = useState<number[]>([]);
    const [deleteSettingIds, setDeleteSettingIds] = useState<number[]>([]);
    const [primaryImageId, setPrimaryImageId] = useState<number | null>(null);
    const [primaryNewImageIndex, setPrimaryNewImageIndex] = useState<number | null>(null);
    const [openCreateForms, setOpenCreateForms] = useState<Record<ConfigKey, boolean>>({ service_prices: false, settings: false });
    const [openConfigCards, setOpenConfigCards] = useState<Record<ConfigKey, boolean>>({ service_prices: true, settings: false });
    const [isRegionPickerOpen, setIsRegionPickerOpen] = useState(false);
    const [regionKeyword, setRegionKeyword] = useState("");
    const [expandedRegionIds, setExpandedRegionIds] = useState<number[]>([]);
    const [quickService, setQuickService] = useState({ name: "", charge_method: 5, unit_name: "", is_required: false, is_active: true });
    const [quickSetting, setQuickSetting] = useState({ setting_label: "", setting_value: "", description: "", is_public: true });
    const [form, setForm] = useState(createDefaultBuildingForm);
    const [viewingImageSrc, setViewingImageSrc] = useState<string | null>(null);
    const [isCreateManagerModalOpen, setIsCreateManagerModalOpen] = useState(false);
    const [isRegionModalOpen, setIsRegionModalOpen] = useState(false);
    const [editingRegionId, setEditingRegionId] = useState<number | null>(null);
    const [isRegionFormLoading, _setIsRegionFormLoading] = useState(false);
    const [regionForm, setRegionForm] = useState({
        parent_id: "",
        code: "",
        name: "",
        description: "",
        is_active: true,
    });

    const reloadRegions = async () => {
        try {
            const res = await fetchAdminRegions({ per_page: 100 });
            setRegions(getResourceList(res.result));
        } catch (e) {
            console.error("Lỗi tải lại danh sách khu vực:", e);
        }
    };

    const openCreateRegionModal = () => {
        setEditingRegionId(null);
        setRegionForm({
            parent_id: "",
            code: "",
            name: "",
            description: "",
            is_active: true,
        });
        setIsRegionModalOpen(true);
    };

    const handleCancelRegionModal = () => {
        setIsRegionModalOpen(false);
        setEditingRegionId(null);
        setRegionForm({
            parent_id: "",
            code: "",
            name: "",
            description: "",
            is_active: true,
        });
    };

    const handleRegionSubmitSuccess = async () => {
        setIsRegionModalOpen(false);
        setEditingRegionId(null);
        setRegionForm({
            parent_id: "",
            code: "",
            name: "",
            description: "",
            is_active: true,
        });
        await reloadRegions();
    };

    const isEditMode = typeof resolvedBuildingId === "number";
    const activeRegions = useMemo(() => regions.filter((region) => region.status), [regions]);
    const selectedRegion = useMemo(() => activeRegions.find((region) => region.id === Number(form.region_id)), [activeRegions, form.region_id]);
    const normalizedRegionKeyword = regionKeyword.trim().toLowerCase();
    const filteredRegionIds = useMemo(() => {
        if (!normalizedRegionKeyword) return new Set(activeRegions.map((region) => region.id));

        const matchedIds = new Set<number>();
        const includeAncestors = (region: AdminRegionResource) => {
            matchedIds.add(region.id);
            if (!region.parent_id) return;
            const parent = activeRegions.find((item) => item.id === region.parent_id);
            if (parent) includeAncestors(parent);
        };

        activeRegions.forEach((region) => {
            const haystack = [region.name, region.code, region.path, region.parent_name].filter(Boolean).join(" ").toLowerCase();
            if (haystack.includes(normalizedRegionKeyword)) includeAncestors(region);
        });

        return matchedIds;
    }, [activeRegions, normalizedRegionKeyword]);
    const rootRegions = useMemo(() => {
        const activeRegionIds = new Set(activeRegions.map((region) => region.id));
        return activeRegions
            .filter((region) => (!region.parent_id || !activeRegionIds.has(region.parent_id)) && filteredRegionIds.has(region.id))
            .sort((first, second) => first.sort_order - second.sort_order || first.name.localeCompare(second.name));
    }, [activeRegions, filteredRegionIds]);
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
            fetchAdminServices({ per_page: 100, is_active: true, created_by_role: 2 }),

            fetchAdminSettings({ per_page: 100, only_global: true }),
            isEditMode && resolvedBuildingId ? fetchAdminBuildingDetail(resolvedBuildingId) : Promise.resolve(null),
        ])
            .then(([regionsResponse, managersResponse, servicesResponse, settingsResponse, buildingResponse]) => {
                const nextRegions = getResourceList(regionsResponse.result);
                const nextManagers = getResourceList(managersResponse.result);
                const nextServices = getResourceList(servicesResponse.result);
                setRegions(nextRegions);
                setManagers(nextManagers);
                setServices(nextServices);
                setSettingCatalog(getResourceList(settingsResponse.result));

                const building = buildingResponse?.result;
                const nextImages = building?.images || [];
                setExistingImages(nextImages as BuildingImage[]);
                setPrimaryImageId(building?.primary_image?.id || nextImages.find((image) => image.is_primary)?.id || null);

                setForm((current) => {
                    const mappedForm = mapBuildingDetailToForm(building, nextRegions, current);
                    // Ensure dien/nuoc are in service_prices
                    const existingServiceIds = new Set(mappedForm.service_prices.map(p => Number(p.service_id)));
                    const missingRequiredServices = nextServices.filter(s => isRequiredService(s) && !existingServiceIds.has(s.id));
                    
                    if (missingRequiredServices.length > 0) {
                        const newPrices = missingRequiredServices.map(s => ({
                            service_id: String(s.id),
                            service_name: s.name,
                            price: "0",
                            effective_from: getTodayIsoDate(),
                            effective_to: "",
                            status: 1
                        }));
                        mappedForm.service_prices = [...mappedForm.service_prices, ...newPrices];
                    }
                    return mappedForm;
                });
            })
            .catch((error) => setErrorMessage(getVisibleErrorMessage(error, "Không thể tải dữ liệu tòa nhà.")))
            .finally(() => setIsLoading(false));
    }, [isEditMode, isSuperAdmin, resolvedBuildingId]);

    useEffect(() => {
        if (!services.length) return;
        
        const existingServiceIds = new Set(form.service_prices.map(p => Number(p.service_id)));
        const missingRequiredServices = services.filter(s => isRequiredService(s) && !existingServiceIds.has(s.id));
        
        if (missingRequiredServices.length > 0) {
            const newPrices = missingRequiredServices.map(s => ({
                service_id: String(s.id),
                service_name: s.name,
                price: "0",
                effective_from: getTodayIsoDate(),
                effective_to: "",
                status: 1
            }));
            
            setForm(current => ({
                ...current,
                service_prices: [...current.service_prices, ...newPrices]
            }));
        }
    }, [services, form.service_prices]);

    const updateForm = (key: keyof typeof form, value: string | number) => {
        setForm((current) => ({ ...current, [key]: value }));
        setErrors((current) => ({ ...current, [key]: undefined }));
    };

    const selectRegion = (region: AdminRegionResource) => {
        updateForm("region_id", String(region.id));
        setIsRegionPickerOpen(false);
        setRegionKeyword("");
    };

    const toggleRegionExpansion = (regionId: number) => {
        setExpandedRegionIds((current) => (current.includes(regionId) ? current.filter((id) => id !== regionId) : [...current, regionId]));
    };

    const imagePreviewUrls = useMemo(() => imageFiles.map((file) => URL.createObjectURL(file)), [imageFiles]);

    useEffect(() => {
        return () => imagePreviewUrls.forEach((url) => URL.revokeObjectURL(url));
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

    const addRow = (key: ConfigKey, row?: BuildingServicePriceFormRow | BuildingSettingFormRow) => {
        const nextRow = row || (key === "service_prices" ? { service_id: "", price: "0", effective_from: getTodayIsoDate(), effective_to: "", status: 1 }
            : { setting_label: "", setting_value: "", description: "", is_public: true });

        setForm((current) => ({ ...current, [key]: [...(current[key] as unknown[]), nextRow] } as typeof current));
        setErrors((current) => ({ ...current, [key]: undefined }));
    };

    const removeRow = (key: ConfigKey, index: number) => {
        const row = form[key][index] as { id?: number; rooms_count?: number; room_assets_count?: number };
        if (row.id) {
            if (key === "service_prices") setDeleteServicePriceIds((current) => (current.includes(row.id!) ? current : [...current, row.id!]));
            if (key === "settings") setDeleteSettingIds((current) => (current.includes(row.id!) ? current : [...current, row.id!]));
        }
        setForm((current) => ({ ...current, [key]: current[key].filter((_, itemIndex) => itemIndex !== index) } as typeof current));
    };

    const updateRow = (key: ConfigKey, index: number, field: string, value: string | number | boolean) => {
        setForm((current) => {
            const next = [...(current[key] as Array<Record<string, unknown>>)];
            next[index] = { ...next[index], [field]: value };
            return { ...current, [key]: next } as typeof current;
        });
        setErrors((current) => ({ ...current, [key]: undefined }));
    };

    const serviceOptions = useMemo(() => mergeServiceOptions(services, form.service_prices), [form.service_prices, services]);
    const settingOptions = useMemo(() => mergeSettingOptions(settingCatalog, form.settings), [form.settings, settingCatalog]);

    const findServicePriceIndex = (service: AdminServiceResource) => form.service_prices.findIndex((row) => Number(row.service_id) === service.id);
    const findSettingIndex = (item: AdminSettingResource) => form.settings.findIndex((row) => row.source_id === item.id || row.id === item.id || row.setting_label.trim().toLowerCase() === item.setting_label.trim().toLowerCase());



    const toggleService = (service: AdminServiceResource) => {
        if (isRequiredService(service)) return;
        const index = findServicePriceIndex(service);
        if (index >= 0) {
            removeRow("service_prices", index);
            return;
        }
        addRow("service_prices", { service_id: String(service.id), service_name: service.name, price: "0", effective_from: getTodayIsoDate(), effective_to: "", status: 1 });
    };

    const toggleSetting = (item: AdminSettingResource) => {
        const index = findSettingIndex(item);
        if (index >= 0) {
            removeRow("settings", index);
            return;
        }
        addRow("settings", { source_id: item.id, setting_label: item.setting_label, setting_value: item.setting_value || "", description: item.description || "", is_public: Boolean(item.is_public) });
    };



    const createQuickService = async () => {
        if (!quickService.name.trim() || isCreatingService) return;

        try {
            setIsCreatingService(true);
            const response = await createAdminService({
                name: quickService.name.trim(),
                charge_method: Number(quickService.charge_method),
                unit_name: quickService.unit_name.trim() || undefined,
                is_required: quickService.is_required,
                is_active: quickService.is_active,
            });
            const service = response.result;
            setServices((current) => [service, ...current.filter((item) => item.id !== service.id)]);
            addRow("service_prices", { service_id: String(service.id), price: "0", effective_from: getTodayIsoDate(), effective_to: "", status: 1 });
            setQuickService({ name: "", charge_method: 5, unit_name: "", is_required: false, is_active: true });
            setOpenCreateForms((current) => ({ ...current, service_prices: false }));
        } catch (error) {
            setErrorMessage(getVisibleErrorMessage(error, "Không thể tạo nhanh dịch vụ."));
        } finally {
            setIsCreatingService(false);
        }
    };

    const createQuickSetting = async () => {
        if (!quickSetting.setting_label.trim() || isCreatingSetting) return;

        try {
            setIsCreatingSetting(true);
            const response = await createAdminSetting({
                setting_label: quickSetting.setting_label.trim(),
                setting_value: quickSetting.setting_value.trim() || undefined,
                description: quickSetting.description.trim() || undefined,
                is_public: quickSetting.is_public,
            });
            const setting = response.result;
            setSettingCatalog((current) => [setting, ...current.filter((item) => item.id !== setting.id)]);
            addRow("settings", { source_id: setting.id, setting_label: setting.setting_label, setting_value: setting.setting_value || "", description: setting.description || "", is_public: Boolean(setting.is_public) });
            setQuickSetting({ setting_label: "", setting_value: "", description: "", is_public: true });
            setOpenCreateForms((current) => ({ ...current, settings: false }));
        } catch (error) {
            setErrorMessage(getVisibleErrorMessage(error, "Không thể tạo nhanh cài đặt."));
        } finally {
            setIsCreatingSetting(false);
        }
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

            const payload = buildBuildingPayload({
                form,
                imageFiles,
                visibleExistingImages,
                deleteImageIds,
                primaryImageId,
                primaryNewImageIndex,
                deleteServicePriceIds,
                deleteSettingIds,
            });

            if (isEditMode && resolvedBuildingId) await updateAdminBuilding(resolvedBuildingId, payload);
            else await createAdminBuilding(payload);

            navigate("/admin/facilities", {
                state: {
                    successMessage: isEditMode ? "Cập nhật tòa nhà thành công." : "Tạo tòa nhà thành công."
                }
            });
        } catch (error) {
            setErrorMessage(getVisibleErrorMessage(error, "Không thể lưu tòa nhà."));
        } finally {
            setIsSaving(false);
        }
    };

    if (!session?.admin) return <Navigate to="/admin/login" replace />;
    if (!isSuperAdmin) return <Navigate to="/admin/dashboard" replace />;

    return (
        <div className="space-y-6">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <button onClick={() => navigate(-1)} className="mb-3 inline-flex items-center gap-2 text-sm font-bold text-gray-500 hover:text-gray-900">
                        <ArrowLeft className="h-4 w-4" /> Quay lại danh sách
                    </button>
                    <h1 className="text-2xl font-black tracking-tight text-gray-900">{isEditMode ? "Chỉnh sửa Tòa nhà" : "Thêm Tòa nhà"}</h1>
                </div>
                <div className="flex gap-2">
                    <button onClick={() => navigate(-1)} className="inline-flex items-center justify-center rounded-2xl border border-gray-200 px-5 py-3 text-xs font-black uppercase tracking-widest text-gray-500 transition hover:bg-gray-50 hover:text-gray-900">Hủy</button>
                    <button onClick={submit} disabled={isSaving || isLoading} className="inline-flex items-center justify-center gap-2 rounded-2xl bg-[#333333] px-5 py-3 text-xs font-black uppercase tracking-widest text-white shadow-xl shadow-black/10 transition hover:bg-gray-800 disabled:opacity-50">
                        <Save className="h-4 w-4 text-white stroke-[2.8]" /> {isSaving ? "Đang lưu..." : isEditMode ? "Cập nhật" : "Lưu tòa nhà"}
                    </button>
                </div>
            </div>

            {errorMessage && <div className="rounded-2xl border border-rose-100 bg-rose-50 p-4 text-sm font-bold text-rose-600">{errorMessage}</div>}

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_420px]">
                <div className="space-y-6">
                    <section className={cardClass}>
                        <CardHeader icon={Building2} title="Thông tin tòa nhà" description="Thông tin nhận diện, khu vực, quản lý và trạng thái vận hành." />
                        <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
                            <TextField label="Tên tòa nhà" value={form.name} error={errors.name} onChange={(value) => updateForm("name", value)} />
                            <RegionTreePicker
                                regions={activeRegions}
                                rootRegions={rootRegions}
                                selectedRegion={selectedRegion}
                                selectedRegionId={form.region_id}
                                expandedRegionIds={expandedRegionIds}
                                filteredRegionIds={filteredRegionIds}
                                keyword={regionKeyword}
                                isOpen={isRegionPickerOpen}
                                error={errors.region_id}
                                onKeywordChange={setRegionKeyword}
                                onToggleOpen={() => setIsRegionPickerOpen((current) => !current)}
                                onToggleRegion={toggleRegionExpansion}
                                onSelect={selectRegion}
                                onCreateRegion={openCreateRegionModal}
                            />
                            <div className="lg:col-span-2">
                                <label className={labelClass}>Địa chỉ</label>
                                <textarea className={`${inputClass} min-h-24 ${errors.address ? inputErrorClass : ""}`} value={form.address} aria-invalid={!!errors.address} onChange={(event) => updateForm("address", event.target.value)} />
                                <FieldError message={errors.address} />
                            </div>
                            <div>
                                <label className={labelClass}>Người quản lý</label>
                                <div className="flex gap-2">
                                    <div className="flex-1">
                                        <AdminSelect value={form.manager_admin_id} options={managerOptions} invalid={!!errors.manager_admin_id} onChange={(nextValue) => updateForm("manager_admin_id", String(nextValue))} />
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => setIsCreateManagerModalOpen(true)}
                                        className="inline-flex h-[45px] w-[45px] shrink-0 items-center justify-center rounded-2xl border border-gray-250 bg-white text-gray-700 transition hover:bg-gray-50 focus:outline-none"
                                        title="Thêm nhanh quản lý mới"
                                    >
                                        <Plus className="h-5 w-5" />
                                    </button>
                                </div>
                                <FieldError message={errors.manager_admin_id} />
                            </div>
                            <TextField label="Tổng số tầng" type="number" value={form.total_floors} error={errors.total_floors} onChange={(value) => updateForm("total_floors", Number(value))} />
                            <div><label className={labelClass}>Trạng thái</label><AdminSelect value={form.status} options={buildingStatusOptions} invalid={!!errors.status} onChange={(nextValue) => updateForm("status", Number(nextValue))} /><FieldError message={errors.status} /></div>
                            <div><label className={labelClass}>Giới tính</label><AdminSelect value={form.gender_policy} options={genderPolicyOptions} invalid={!!errors.gender_policy} onChange={(nextValue) => updateForm("gender_policy", Number(nextValue))} /><FieldError message={errors.gender_policy} /></div>
                            <div className="lg:col-span-2"><label className={labelClass}>Mô tả</label><textarea className={`${inputClass} min-h-28 ${errors.description ? inputErrorClass : ""}`} value={form.description} aria-invalid={!!errors.description} onChange={(event) => updateForm("description", event.target.value)} /><FieldError message={errors.description} /></div>
                        </div>
                    </section>

                    <section className={cardClass}>
                        <CardHeader icon={ImagePlus} title="Hình ảnh tòa nhà" description="Tải tối đa 20 ảnh, chọn ảnh chính để hiển thị ngoài danh sách." action={<label className="inline-flex cursor-pointer items-center gap-2 rounded-2xl bg-gray-900 px-4 py-3 text-xs font-black uppercase tracking-widest text-white transition hover:bg-gray-800"><ImagePlus className="h-4 w-4 text-white" /> Chọn ảnh<input type="file" accept="image/jpeg,image/png,image/webp" multiple className="hidden" onChange={(event) => updateImages(event.target.files)} /></label>} />
                        <FieldError message={errors.images} />
                        {visibleExistingImages.length > 0 && imagePreviewUrls.length > 0 && <p className="mt-2 px-1 text-xs font-bold text-gray-400">Ảnh mới sẽ được thêm sau ảnh hiện tại. Nếu muốn đặt ảnh mới làm ảnh chính, hãy xóa ảnh chính hiện tại trước.</p>}
                        <div className="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                            {visibleExistingImages.map((image) => <ImageCard key={image.id} src={image.image_url || stayHubImage} isPrimary={primaryImageId === image.id} onPrimary={() => { setPrimaryImageId(image.id); setPrimaryNewImageIndex(null); }} onRemove={() => removeExistingImage(image)} onView={() => setViewingImageSrc(image.image_url || stayHubImage)} />)}
                            {imagePreviewUrls.map((url, index) => <ImageCard key={url} src={url} isPrimary={primaryNewImageIndex === index} disabledPrimary={visibleExistingImages.length > 0} primaryLabel={primaryNewImageIndex === index ? "Chính" : "Mới"} onPrimary={() => { if (visibleExistingImages.length === 0) { setPrimaryImageId(null); setPrimaryNewImageIndex(index); } }} onRemove={() => removeNewImage(index)} removeIcon="x" onView={() => setViewingImageSrc(url)} />)}
                            {visibleExistingImages.length === 0 && imagePreviewUrls.length === 0 && <div className="col-span-full rounded-3xl border border-dashed border-gray-200 bg-gray-50 p-10 text-center text-sm font-bold text-gray-400">Chưa có ảnh tòa nhà.</div>}
                        </div>
                    </section>
                </div>

                <aside className="space-y-6">
                    <ConfigCard icon={Zap} title="Bảng giá dịch vụ" count={form.service_prices.length} isOpen={openConfigCards.service_prices} error={errors.service_prices} onToggle={() => setOpenConfigCards((current) => ({ ...current, service_prices: !current.service_prices }))} onAdd={() => { setOpenConfigCards((current) => ({ ...current, service_prices: true })); setOpenCreateForms((current) => ({ ...current, service_prices: !current.service_prices })); }} addLabel={openCreateForms.service_prices ? "Đóng" : "Tạo mới"}>
                        <SelectionBlock title="Chọn dịch vụ có sẵn" emptyText="Chưa có dịch vụ đang hoạt động.">
                            {serviceOptions.map((service) => {
                                const isRequired = isRequiredService(service);
                                return <CheckboxOption key={service.id} checked={findServicePriceIndex(service) >= 0} title={service.name} disabled={isRequired} onChange={() => toggleService(service)} />;
                            })}
                        </SelectionBlock>
                        {openCreateForms.service_prices && <QuickPanel actionLabel={isCreatingService ? "Đang tạo" : "Tạo dịch vụ"} disabled={isCreatingService || !quickService.name.trim()} onAction={createQuickService}><div className="grid grid-cols-2 gap-3"><TextField label="Tên dịch vụ" value={quickService.name} onChange={(value) => setQuickService((current) => ({ ...current, name: value }))} /><div><label className={labelClass}>Tính phí</label><AdminSelect value={quickService.charge_method} options={chargeMethodOptions} onChange={(value) => setQuickService((current) => ({ ...current, charge_method: Number(value) }))} /></div></div></QuickPanel>}
                        {form.service_prices.map((item, index) => {
                            const service = services.find((s) => s.id === Number(item.service_id));
                            const isRequired = isRequiredService(service);
                            return <RowShell key={item.id || `service-${item.service_id}-${index}`} title={service?.name || item.service_name || `Bảng giá ${index + 1}`} disabledRemove={isRequired} onRemove={() => removeRow("service_prices", index)}><div className="grid grid-cols-2 gap-3"><TextField label="Giá" value={item.price} onChange={(value) => updateRow("service_prices", index, "price", formatMoneyInput(value))} /><div><label className={labelClass}>Trạng thái</label><AdminSelect value={item.status} options={servicePriceStatusOptions} onChange={(value) => updateRow("service_prices", index, "status", Number(value))} /></div></div></RowShell>;
                        })}
                    </ConfigCard>

                    <ConfigCard icon={Settings} title="Cài đặt" count={form.settings.length} isOpen={openConfigCards.settings} error={errors.settings} onToggle={() => setOpenConfigCards((current) => ({ ...current, settings: !current.settings }))} onAdd={() => { setOpenConfigCards((current) => ({ ...current, settings: true })); setOpenCreateForms((current) => ({ ...current, settings: !current.settings })); }} addLabel={openCreateForms.settings ? "Đóng" : "Tạo mới"}>
                        <SelectionBlock title="Chọn cài đặt có sẵn" emptyText="Chưa có cài đặt dùng chung.">
                            {settingOptions.map((item) => <CheckboxOption key={item.id} checked={findSettingIndex(item) >= 0} title={item.setting_label} onChange={() => toggleSetting(item)} />)}
                        </SelectionBlock>
                        {openCreateForms.settings && <QuickPanel actionLabel={isCreatingSetting ? "Đang tạo" : "Tạo cài đặt"} disabled={isCreatingSetting || !quickSetting.setting_label.trim()} onAction={createQuickSetting}><TextField label="Tên hiển thị" value={quickSetting.setting_label} onChange={(value) => setQuickSetting((current) => ({ ...current, setting_label: value }))} /><TextField label="Giá trị" value={quickSetting.setting_value} onChange={(value) => setQuickSetting((current) => ({ ...current, setting_value: value }))} /><label className="flex items-center gap-2 rounded-2xl border border-gray-100 bg-gray-50 px-4 py-3 text-xs font-black text-gray-600"><input type="checkbox" checked={quickSetting.is_public} onChange={(event) => setQuickSetting((current) => ({ ...current, is_public: event.target.checked }))} /> Công khai</label></QuickPanel>}
                        {form.settings.map((item, index) => item.source_id && !item.id ? <SelectedTemplateRow key={`source-setting-${item.source_id}`} title={item.setting_label} onRemove={() => removeRow("settings", index)} /> : <RowShell key={item.id || `setting-${index}`} title={item.setting_label || `Cài đặt ${index + 1}`} onRemove={() => removeRow("settings", index)}><TextField label="Tên hiển thị" value={item.setting_label} onChange={(value) => updateRow("settings", index, "setting_label", value)} /><TextField label="Giá trị" value={item.setting_value} onChange={(value) => updateRow("settings", index, "setting_value", value)} /><label className="flex items-center gap-2 rounded-2xl border border-gray-100 bg-gray-50 px-4 py-3 text-xs font-black text-gray-600"><input type="checkbox" checked={item.is_public} onChange={(event) => updateRow("settings", index, "is_public", event.target.checked)} /> Công khai</label></RowShell>)}
                    </ConfigCard>
                </aside>
            </div>

            <ImageViewerModal
                isOpen={!!viewingImageSrc}
                src={viewingImageSrc}
                onClose={() => setViewingImageSrc(null)}
            />

            {isCreateManagerModalOpen && (
                <QuickCreateManagerModal
                    onClose={() => setIsCreateManagerModalOpen(false)}
                    onCreated={(newManager) => {
                        setManagers((current) => [newManager, ...current]);
                        updateForm("manager_admin_id", String(newManager.id));
                        setIsCreateManagerModalOpen(false);
                    }}
                />
            )}

            <RegionModal
                isOpen={isRegionModalOpen}
                onClose={handleCancelRegionModal}
                regions={regions}
                editingRegionId={editingRegionId}
                form={regionForm}
                setForm={setRegionForm}
                onCancel={handleCancelRegionModal}
                onSubmitSuccess={handleRegionSubmitSuccess}
                isFormLoading={isRegionFormLoading}
            />
        </div>
    );
}

function RegionTreePicker({
    regions,
    rootRegions,
    selectedRegion,
    selectedRegionId,
    expandedRegionIds,
    filteredRegionIds,
    keyword,
    isOpen,
    error,
    onKeywordChange,
    onToggleOpen,
    onToggleRegion,
    onSelect,
    onCreateRegion,
}: {
    regions: AdminRegionResource[];
    rootRegions: AdminRegionResource[];
    selectedRegion?: AdminRegionResource;
    selectedRegionId: string;
    expandedRegionIds: number[];
    filteredRegionIds: Set<number>;
    keyword: string;
    isOpen: boolean;
    error?: string;
    onKeywordChange: (value: string) => void;
    onToggleOpen: () => void;
    onToggleRegion: (regionId: number) => void;
    onSelect: (region: AdminRegionResource) => void;
    onCreateRegion?: () => void;
}) {
    const isSearching = keyword.trim() !== "";
    const renderRegionNode = (region: AdminRegionResource, depth = 0): ReactNode => {
        const children = regions
            .filter((item) => item.parent_id === region.id && filteredRegionIds.has(item.id))
            .sort((first, second) => first.sort_order - second.sort_order || first.name.localeCompare(second.name));
        const isExpanded = isSearching || expandedRegionIds.includes(region.id);
        const isSelected = Number(selectedRegionId) === region.id;
        const isSelectable = true;

        return (
            <div key={region.id} className="space-y-1">
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => onToggleRegion(region.id)}
                        disabled={children.length === 0}
                        aria-label={`${isExpanded ? "Thu gọn" : "Mở rộng"} khu vực ${region.name}`}
                        aria-expanded={children.length > 0 ? isExpanded : undefined}
                        className={`inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1]/80 text-[#8b5e34] transition focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20 ${children.length > 0 ? "hover:border-[#f3c56b]/45 hover:bg-[#f3c56b]/15 hover:text-[#24170d]" : "cursor-default opacity-35"}`}
                    >
                        <ChevronRight className={`h-3.5 w-3.5 transition-transform ${isExpanded && children.length > 0 ? "rotate-90" : ""}`} />
                    </button>
                    <button
                        type="button"
                        onClick={() => {
                            if (isSelectable) {
                                onSelect(region);
                            } else if (children.length > 0) {
                                onToggleRegion(region.id);
                            }
                        }}
                        className={`flex min-w-0 flex-1 items-center justify-between gap-2 rounded-2xl border px-3 py-2.5 text-left text-sm transition focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20 ${
                            isSelected 
                                ? "border-[#f3c56b]/35 bg-[#24170d] text-[#fff4df] shadow-lg shadow-[#24170d]/12" 
                                : isSelectable
                                    ? "border-[#3d2a18]/10 bg-[#fffaf1]/80 text-[#6f6254] hover:border-[#f3c56b]/45 hover:bg-[#f3c56b]/15 hover:text-[#24170d]" 
                                    : "border-[#3d2a18]/10 bg-[#fffaf1]/30 text-gray-400 hover:bg-[#efe2cf]/30 cursor-pointer"
                        }`}
                        style={{ paddingLeft: 12 + depth * 16 }}
                        aria-pressed={isSelected}
                    >
                        <span className="flex min-w-0 flex-1 items-center gap-2">
                            <MapPin className={`h-4 w-4 shrink-0 ${isSelected ? "text-[#f3c56b]" : isSelectable ? "text-[#a65f16]" : "text-gray-300"}`} />
                            <span className="min-w-0 flex-1 truncate font-black tracking-tight">{region.name}</span>
                        </span>
                        <span className={`shrink-0 rounded-full px-2 py-0.5 text-[10px] font-black uppercase ${isSelected ? "bg-white/15 text-[#fff4df]" : isSelectable ? "bg-[#efe2cf]/80 text-[#8b5e34]" : "bg-gray-100 text-gray-400"}`}>{region.code}</span>
                    </button>
                </div>
                {isExpanded && children.length > 0 && <div className="space-y-1 border-l border-dashed border-[#f3c56b]/55 pl-2">{children.map((child) => renderRegionNode(child, depth + 1))}</div>}
            </div>
        );
    };

    return (
        <div className="relative">
            <label className={labelClass}>Khu vực</label>
            <button
                type="button"
                onClick={onToggleOpen}
                className={`${inputClass} flex min-h-12.5 items-center justify-between gap-3 text-left ${error ? inputErrorClass : ""}`}
                aria-expanded={isOpen}
                aria-invalid={!!error}
            >
                <span className="flex min-w-0 flex-1 items-center gap-2">
                    <MapPin className="h-4 w-4 shrink-0 text-[#a65f16]" />
                    <span className={`truncate ${selectedRegion ? "text-gray-900" : "text-gray-400"}`}>{selectedRegion ? selectedRegion.name : "Chọn khu vực"}</span>
                </span>
                <ChevronRight className={`h-4 w-4 shrink-0 text-gray-400 transition-transform ${isOpen ? "rotate-90" : ""}`} />
            </button>
            {isOpen && (
                <div className="absolute left-0 right-0 top-[calc(100%+0.5rem)] z-30 overflow-hidden rounded-3xl border border-[#3d2a18]/10 bg-[#fffaf1] p-3 shadow-2xl shadow-[#6b3f1d]/18">
                    <div className="relative mb-3">
                        <Search className="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#a65f16]" />
                        <input
                            type="text"
                            value={keyword}
                            onChange={(event) => onKeywordChange(event.target.value)}
                            placeholder="Tìm mã, tên, đường dẫn khu vực..."
                            className="h-11 w-full rounded-2xl border border-[#3d2a18]/10 bg-white pl-10 pr-3 text-sm font-bold text-[#3d2a18] shadow-sm outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20"
                            autoFocus
                        />
                    </div>
                    <div className="max-h-80 space-y-1 overflow-y-auto pr-1">
                        {rootRegions.length > 0 ? rootRegions.map((region) => renderRegionNode(region)) : <p className="rounded-2xl border border-dashed border-[#3d2a18]/10 bg-white/70 p-4 text-center text-xs font-bold text-[#8b5e34]/65">Không tìm thấy khu vực phù hợp.</p>}
                    </div>
                    {onCreateRegion && (
                        <div className="mt-3 pt-3 border-t border-[#3d2a18]/10 flex justify-end">
                            <button
                                type="button"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    onCreateRegion();
                                }}
                                className="inline-flex items-center gap-1.5 rounded-xl bg-[#24170d] px-4 py-2 text-xs font-black uppercase tracking-widest text-[#fff4df] hover:bg-stone-900 transition cursor-pointer"
                            >
                                <Plus className="h-3.5 w-3.5 text-[#f3c56b]" />
                                Tạo khu vực
                            </button>
                        </div>
                    )}
                </div>
            )}
            <FieldError message={error} />
        </div>
    );
}

function CardHeader({ icon: Icon, title, description, action }: { icon: ElementType; title: string; description: string; action?: ReactNode }) {
    return <div className="mb-6 flex items-start justify-between gap-4 border-b border-gray-100 pb-4"><div className="flex items-center gap-3"><div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-900 text-white"><Icon className="h-5 w-5" /></div><div><h2 className="font-black text-gray-900">{title}</h2><p className="mt-1 text-xs font-semibold text-gray-400">{description}</p></div></div>{action}</div>;
}

function ConfigCard({ icon: Icon, title, children, onAdd, addLabel, error, count, isOpen, onToggle }: { icon: ElementType; title: string; children: ReactNode; onAdd: () => void; addLabel: string; error?: string; count: number; isOpen: boolean; onToggle: () => void }) {
    return <section className="rounded-4xl border border-gray-100 bg-white p-5 shadow-sm"><div className="flex items-center justify-between gap-3"><button type="button" onClick={onToggle} className="flex min-w-0 flex-1 items-center gap-3 text-left"><div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-gray-100 text-gray-900"><Icon className="h-4 w-4" /></div><span className="min-w-0"><span className="block truncate font-black text-gray-900">{title}</span><span className="mt-0.5 block text-[11px] font-bold text-gray-400">Đã chọn {count} mục</span></span></button><div className="flex shrink-0 items-center gap-2"><button type="button" onClick={onAdd} className="inline-flex items-center gap-1 rounded-2xl bg-gray-900 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-white"><Plus className="h-3 w-3" /> {addLabel}</button><button type="button" onClick={onToggle} className="inline-flex h-9 w-9 items-center justify-center rounded-2xl border border-gray-100 bg-gray-50 text-gray-500 transition hover:border-gray-300 hover:bg-white hover:text-gray-900" aria-label={`${isOpen ? "Thu gọn" : "Mở rộng"} ${title}`} aria-expanded={isOpen}><ChevronRight className={`h-4 w-4 transition-transform ${isOpen ? "rotate-90" : ""}`} /></button></div></div><FieldError message={error} />{isOpen && <div className="mt-4 space-y-3">{children}</div>}</section>;
}

function mergeServiceOptions(catalog: AdminServiceResource[], selectedRows: BuildingServicePriceFormRow[]) {
    const selectedOptions = selectedRows
        .filter((row) => row.service_id && row.service_name)
        .map((row) => ({
            id: Number(row.service_id),
            name: row.service_name || "",
            charge_method: 5,
            is_required: false,
            is_active: true,
        } as AdminServiceResource));

    return mergeOptionsById(catalog, selectedOptions);
}

function mergeSettingOptions(catalog: AdminSettingResource[], selectedRows: BuildingSettingFormRow[]) {
    const selectedOptions = selectedRows
        .filter((row) => row.id && row.source_id)
        .map((row) => ({
            id: row.source_id!,
            setting_label: row.setting_label,
            setting_value: row.setting_value,
            description: row.description,
            is_public: row.is_public,
        } as AdminSettingResource));

    return mergeOptionsById(catalog, selectedOptions);
}

function mergeOptionsById<T extends { id: number }>(catalog: T[], selectedOptions: T[]) {
    const selectedIds = new Set(selectedOptions.map((item) => item.id));
    return [...selectedOptions, ...catalog.filter((item) => !selectedIds.has(item.id))];
}

function SelectionBlock({ title, emptyText, children }: { title: string; emptyText: string; children: ReactNode }) {
    const hasChildren = Array.isArray(children) ? children.length > 0 : Boolean(children);

    return <div className="rounded-3xl border border-dashed border-gray-200 bg-gray-50/70 p-4"><p className="mb-3 text-[10px] font-black uppercase tracking-widest text-gray-400">{title}</p><div className="max-h-60 space-y-2 overflow-y-auto py-1 pr-1">{hasChildren ? children : <p className="text-xs font-bold text-gray-400">{emptyText}</p>}</div></div>;
}

function CheckboxOption({ checked, title, description, disabled, onChange }: { checked: boolean; title: string; description?: string; disabled?: boolean; onChange: () => void }) {
    return <label className={`flex cursor-pointer items-start gap-3 rounded-2xl border px-3 py-3 transition ${checked ? "border-gray-900 bg-white shadow-sm" : "border-gray-100 bg-white/70 hover:border-gray-300"} ${disabled ? "opacity-50 cursor-not-allowed" : ""}`}><input type="checkbox" checked={checked} disabled={disabled} onChange={onChange} className="mt-1 h-4 w-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900 disabled:opacity-50 disabled:cursor-not-allowed" /><span className="min-w-0 flex-1"><span className="block truncate text-xs font-black text-gray-900">{title}</span>{description && <span className="mt-0.5 block truncate text-[11px] font-semibold text-gray-400">{description}</span>}</span></label>;
}

function QuickPanel({ children, actionLabel, disabled, onAction }: { children: ReactNode; actionLabel: string; disabled: boolean; onAction: () => void }) {
    return <div className="rounded-3xl border border-blue-100 bg-blue-50/50 p-4"><div className="space-y-3">{children}</div><button type="button" onClick={onAction} disabled={disabled} className="mt-3 w-full rounded-2xl bg-blue-600 px-4 py-3 text-xs font-black uppercase tracking-widest text-white transition hover:bg-blue-700 disabled:opacity-50">{actionLabel}</button></div>;
}

function SelectedTemplateRow({ title, description, onRemove }: { title: string; description?: string; onRemove: () => void }) {
    return <div className="flex items-center justify-between gap-3 rounded-3xl border border-emerald-100 bg-emerald-50/60 p-4"><div className="min-w-0"><p className="truncate text-xs font-black text-gray-900">{title}</p>{description && <p className="mt-0.5 truncate text-[11px] font-semibold text-emerald-700">{description}</p>}</div><button type="button" onClick={onRemove} className="rounded-full p-2 text-gray-400 transition hover:bg-rose-50 hover:text-rose-600"><Trash2 className="h-4 w-4" /></button></div>;
}

function RowShell({ title, children, onRemove, disabledRemove = false }: { title: string; children: ReactNode; onRemove: () => void; disabledRemove?: boolean }) {
    return <div className="rounded-3xl border border-gray-100 bg-gray-50/70 p-4"><div className="mb-3 flex items-center justify-between gap-3"><p className="text-xs font-black text-gray-900">{title}</p><button type="button" disabled={disabledRemove} onClick={onRemove} className="rounded-full p-2 text-gray-400 transition hover:bg-rose-50 hover:text-rose-600 disabled:cursor-not-allowed disabled:opacity-40"><Trash2 className="h-4 w-4" /></button></div><div className="space-y-3">{children}</div></div>;
}

function TextField({ label, value, onChange, error, type = "text", placeholder }: { label: string; value: string | number; onChange: (value: string) => void; error?: string; type?: string; placeholder?: string }) {
    return <div><label className={labelClass}>{label}</label><input type={type} placeholder={placeholder} className={`${inputClass} ${error ? inputErrorClass : ""}`} value={value} aria-invalid={!!error} onChange={(event) => onChange(event.target.value)} /><FieldError message={error} /></div>;
}

function ImageCard({ src, isPrimary, disabledPrimary, primaryLabel = "Chính", onPrimary, onRemove, onView, removeIcon = "trash" }: { src: string; isPrimary: boolean; disabledPrimary?: boolean; primaryLabel?: string; onPrimary: () => void; onRemove: () => void; onView?: () => void; removeIcon?: "trash" | "x" }) {
    return <div className="relative overflow-hidden rounded-2xl border border-gray-200 bg-white"><img src={src || stayHubImage} alt="Ảnh tòa nhà" onError={(event) => { event.currentTarget.src = stayHubImage }} className="h-32 w-full object-cover cursor-pointer hover:opacity-90 transition-opacity" onClick={onView} /><button type="button" onClick={onPrimary} disabled={disabledPrimary} className={`absolute left-2 top-2 rounded-full px-2 py-1 text-[10px] font-black text-white disabled:cursor-not-allowed disabled:opacity-70 ${isPrimary ? "bg-amber-500" : "bg-black/60"}`}><Star className="mr-1 inline h-3 w-3" /> {primaryLabel}</button><button type="button" onClick={onRemove} className="absolute right-2 top-2 rounded-full bg-black/60 p-1.5 text-white transition hover:bg-rose-600">{removeIcon === "x" ? <X className="h-3.5 w-3.5" /> : <Trash2 className="h-3.5 w-3.5" />}</button></div>;
}

interface QuickCreateManagerModalProps {
    onClose: () => void;
    onCreated: (manager: AdminManagerResource) => void;
}

export function QuickCreateManagerModal({ onClose, onCreated }: QuickCreateManagerModalProps) {
    const [form, setForm] = useState({
        username: "",
        full_name: "",
        email: "",
        phone: "",
    });
    const [isSaving, setIsSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!form.username.trim() || !form.full_name.trim() || !form.email.trim() || !form.phone.trim()) {
            setError("Vui lòng điền đầy đủ các trường bắt buộc.");
            return;
        }

        try {
            setIsSaving(true);
            setError(null);

            const response = await createAdminAccount({
                username: form.username.trim(),
                full_name: form.full_name.trim(),
                email: form.email.trim(),
                phone: form.phone.trim(),
                role: 1, // Building manager
                status: 1, // Active
            });

            if (response.result) {
                const account = response.result;
                onCreated({
                    id: account.id,
                    username: account.username,
                    full_name: account.full_name,
                    email: account.email,
                    phone: account.phone,
                    role: account.role,
                    status: account.status,
                });
            }
        } catch (err: any) {
            setError(getVisibleErrorMessage(err, "Không thể tạo tài khoản quản lý."));
        } finally {
            setIsSaving(false);
        }
    };

    return (
        <div className="fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <button type="button" aria-label="Đóng" onClick={onClose} className="absolute inset-0 bg-stone-950/65 backdrop-blur-sm" />
            <div className="relative z-10 w-full max-w-md rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] p-6 shadow-2xl">
                <h2 className="text-lg font-black text-[#24170d]">Tạo quản lý mới</h2>
                <p className="mt-1 text-xs font-bold text-[#8b5e34]/70">Tài khoản quản lý mới sẽ được thêm vào hệ thống và gán vào tòa nhà này.</p>

                {error && <p className="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs font-black text-rose-700">{error}</p>}

                <form onSubmit={handleSubmit} className="mt-4 space-y-4">
                    <div>
                        <label className="mb-1.5 block px-1 text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/65">Tên đăng nhập *</label>
                        <input
                            className="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-900 outline-none transition focus:border-gray-950"
                            value={form.username}
                            onChange={(e) => setForm(f => ({ ...f, username: e.target.value }))}
                            placeholder="Ví dụ: manager_b"
                            required
                        />
                    </div>
                    <div>
                        <label className="mb-1.5 block px-1 text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/65">Họ tên *</label>
                        <input
                            className="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-900 outline-none transition focus:border-gray-950"
                            value={form.full_name}
                            onChange={(e) => setForm(f => ({ ...f, full_name: e.target.value }))}
                            placeholder="Nhập họ tên"
                            required
                        />
                    </div>
                    <div>
                        <label className="mb-1.5 block px-1 text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/65">Email *</label>
                        <input
                            type="email"
                            className="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-900 outline-none transition focus:border-gray-950"
                            value={form.email}
                            onChange={(e) => setForm(f => ({ ...f, email: e.target.value }))}
                            placeholder="example@stayhub.vn"
                            required
                        />
                    </div>
                    <div>
                        <label className="mb-1.5 block px-1 text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/65">Số điện thoại *</label>
                        <input
                            type="tel"
                            className="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-900 outline-none transition focus:border-gray-950"
                            value={form.phone}
                            onChange={(e) => setForm(f => ({ ...f, phone: e.target.value.replace(/\D/g, '') }))}
                            placeholder="Nhập số điện thoại"
                            maxLength={10}
                            required
                        />
                    </div>

                    <div className="mt-5 flex gap-3">
                        <button type="button" onClick={onClose} className="h-12 flex-1 rounded-xl border border-[#3d2a18]/10 bg-white text-sm font-black text-[#8b5e34] hover:bg-gray-50">
                            Hủy
                        </button>
                        <button
                            type="submit"
                            disabled={isSaving}
                            className="h-12 flex-1 rounded-xl bg-[#24170d] text-sm font-black text-[#fff4df] hover:bg-[#3d2a18] disabled:opacity-60"
                        >
                            {isSaving ? "Đang tạo..." : "Tạo quản lý"}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
