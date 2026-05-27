import { useEffect, useMemo, useState } from "react";
import { Link, Navigate, useNavigate, useParams } from "react-router-dom";
import { ArrowLeft, CheckCircle2, MapPin, Save } from "lucide-react";
import { isSuperAdminRole, useAdminSession } from "../../auth/hooks/use-admin-session";
import { createAdminRegion, fetchAdminRegionDetail, fetchAdminRegions, updateAdminRegion } from "../services/facilities.service";
import type { AdminRegionPayload, AdminRegionResource } from "../types/facility-api.model";
import { AdminSelect } from "../../shared/components/AdminSelect";
import { validateRegionForm, type RegionFormErrors } from "../validations/region.validation";

function getResourceList<T>(result: { data?: T[] } | T[] | null | undefined): T[] {
    if (!result) return [];
    if (Array.isArray(result)) return result;
    return result.data || [];
}

const inputClass = "w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#24170d] outline-none transition placeholder:text-[#8b5e34]/45 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20";
const inputErrorClass = "border-rose-300 bg-rose-50/70 focus:border-rose-400 focus:ring-rose-100";
const labelClass = "mb-1.5 block px-1 text-[10px] font-black uppercase tracking-[0.18em] text-[#8b5e34]/65";

function formatRegionOption(region: AdminRegionResource) {
    const prefix = region.parent_id ? "— " : "";
    return `${prefix}${region.name} (${region.code})`;
}

function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <p className="mt-2 px-1 text-xs font-black text-rose-600" role="alert">{message}</p>;
}

export function CreateRegionScreen() {
    const navigate = useNavigate();
    const { regionId } = useParams();
    const editingRegionId = regionId ? Number(regionId) : null;
    const isEditing = Number.isFinite(editingRegionId);
    const { session } = useAdminSession();
    const [regions, setRegions] = useState<AdminRegionResource[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [isSaving, setIsSaving] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [errors, setErrors] = useState<RegionFormErrors>({});
    const [form, setForm] = useState({
        parent_id: "",
        code: "",
        name: "",
        description: "",
        is_active: true,
    });

    const isSuperAdmin = isSuperAdminRole(session?.admin.role);
    const activeParentRegions = useMemo(() => regions.filter((region) => region.status && !region.parent_id), [regions]);
    const parentRegionOptions = useMemo(() => [
        { value: "", label: "Không chọn - tạo khu vực cấp 1", description: "Khu vực gốc trong hệ thống", tone: "warning" as const },
        ...activeParentRegions.map((region) => ({ value: region.id, label: formatRegionOption(region), tone: "default" as const })),
    ], [activeParentRegions]);

    useEffect(() => {
        if (!isSuperAdmin) return;

        const loadRegionForm = async () => {
            try {
                setIsLoading(true);
                const regionsResponse = await fetchAdminRegions({ per_page: 100 });
                const nextRegions = getResourceList(regionsResponse.result);
                setRegions(nextRegions);

                if (isEditing && editingRegionId) {
                    const regionResponse = await fetchAdminRegionDetail(editingRegionId);
                    const region = regionResponse.result;

                    setForm({
                        parent_id: region.parent_id ? String(region.parent_id) : "",
                        code: region.code,
                        name: region.name,
                        description: region.description || "",
                        is_active: region.status,
                    });
                }
            } catch (error) {
                setErrorMessage(error instanceof Error ? error.message : "Không thể tải dữ liệu khu vực.");
            } finally {
                setIsLoading(false);
            }
        };

        void loadRegionForm();
    }, [editingRegionId, isEditing, isSuperAdmin]);

    if (!isSuperAdmin) {
        return <Navigate to="/admin/facilities" replace />;
    }

    const updateForm = (key: keyof typeof form, value: string | boolean) => {
        setForm((current) => ({ ...current, [key]: value }));
        setErrors((current) => ({ ...current, [key]: undefined }));
    };

    const submit = async () => {
        if (isSaving) return;

        const nextErrors = validateRegionForm(form, isEditing && editingRegionId ? regions.filter((region) => region.id !== editingRegionId) : regions);
        setErrors(nextErrors);

        if (Object.keys(nextErrors).length > 0) {
            setErrorMessage("Vui lòng kiểm tra lại thông tin khu vực.");
            return;
        }

        try {
            setIsSaving(true);
            setErrorMessage(null);

            const payload: AdminRegionPayload = {
                code: form.code.trim(),
                name: form.name.trim(),
                description: form.description.trim() || undefined,
                is_active: form.is_active,
            };

            if (form.parent_id) {
                payload.parent_id = Number(form.parent_id);
            }

            if (isEditing && editingRegionId) {
                await updateAdminRegion(editingRegionId, payload);
            } else {
                await createAdminRegion(payload);
            }

            navigate("/admin/facilities");
        } catch (error) {
            setErrorMessage(error instanceof Error ? error.message : "Không thể lưu khu vực.");
        } finally {
            setIsSaving(false);
        }
    };

    return (
        <div className="relative overflow-hidden rounded-[2rem] bg-[#f4efe6] text-[#24170d] shadow-inner shadow-white/70">
            <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(to_right,rgba(77,51,25,0.08)_1px,transparent_1px),linear-gradient(to_bottom,rgba(77,51,25,0.08)_1px,transparent_1px)] bg-[size:36px_36px]" />
            <div className="pointer-events-none absolute -right-24 -top-28 h-72 w-72 rounded-full bg-[#f3c56b]/30 blur-3xl" />
            <div className="pointer-events-none absolute bottom-8 left-8 h-56 w-56 rounded-full bg-[#0f766e]/10 blur-3xl" />

            <div className="relative space-y-6 p-4 sm:p-6">
                <div className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
                    <div className="relative p-4 text-[#fff4df] sm:p-5">
                        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_18%,rgba(243,197,107,0.24),transparent_30%),radial-gradient(circle_at_82%_16%,rgba(15,118,110,0.22),transparent_32%),linear-gradient(135deg,#24170d_0%,#3d2a18_54%,#0f3f3b_100%)]" />
                        <div className="relative">
                            <Link to="/admin/facilities" className="mb-3 inline-flex min-h-9 items-center gap-2 rounded-xl border border-[#f8e8c8]/15 bg-[#f8e8c8]/10 px-3 text-xs font-black text-[#fff4df] transition hover:bg-[#f8e8c8]/15 focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20">
                                <ArrowLeft className="h-3.5 w-3.5 text-[#f3c56b]" /> Quay lại danh sách
                            </Link>
                            <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                                <div>
                                    <div className="mb-1 flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] text-[#f3c56b]">
                                        <MapPin className="h-3.5 w-3.5" /> Khu vực StayHub
                                    </div>
                                    <h1 className="max-w-3xl text-2xl font-black tracking-[-0.04em] text-[#fff4df] sm:text-3xl lg:text-4xl">{isEditing ? "Sửa khu vực" : "Thêm khu vực mới"}</h1>
                                    <p className="mt-2 max-w-2xl text-sm font-semibold leading-5 text-[#f8e8c8]/82">{isEditing ? "Cập nhật thông tin và trạng thái khu vực đang quản lý." : "Khai báo khu vực cấp 1 hoặc chọn khu vực cha để tạo khu vực cấp 2 phục vụ phân loại tòa nhà."}</p>
                                </div>
                                <div className="rounded-2xl border border-[#f8e8c8]/15 bg-[#f8e8c8]/10 p-3 text-sm font-bold text-[#f8e8c8]/85">
                                    <div className="flex items-center gap-2 text-[#fff4df]"><CheckCircle2 className="h-4 w-4 text-[#f3c56b]" /> Trường bắt buộc</div>
                                    <p className="mt-1 text-xs leading-5 text-[#f8e8c8]/70">Mã khu vực và tên khu vực cần duy nhất, rõ ràng để dễ tìm kiếm và lọc dữ liệu.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {errorMessage && <div role="alert" className="rounded-3xl border border-rose-200 bg-rose-50/95 p-4 text-sm font-black text-rose-700 shadow-sm">{errorMessage}</div>}

                <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_330px]">
                    <section className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/90 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
                        <div className="border-b border-[#3d2a18]/10 bg-[#fff7e8]/75 p-5">
                            <h2 className="text-lg font-black tracking-tight text-[#24170d]">Thông tin khu vực</h2>
                            <p className="mt-1 text-sm font-semibold text-[#6f6254]">Đặt tên hiển thị thân thiện, mã ngắn gọn và mô tả dễ hiểu cho quản trị viên.</p>
                        </div>

                        <div className="grid grid-cols-1 gap-5 p-5 lg:grid-cols-2">
                            <div>
                                <label htmlFor="region-parent" className={labelClass}>Khu vực cha</label>
                                <AdminSelect id="region-parent" value={form.parent_id} options={parentRegionOptions} disabled={isLoading} invalid={!!errors.parent_id} ariaDescribedBy={errors.parent_id ? "region-parent-error" : undefined} onChange={(nextValue) => updateForm("parent_id", String(nextValue))} />
                                <div id="region-parent-error"><FieldError message={errors.parent_id} /></div>
                                <p className="mt-2 px-1 text-xs font-semibold leading-5 text-[#8b5e34]/70">Chỉ chọn khi khu vực mới là cấp con của một khu vực hiện có.</p>
                            </div>

                            <div>
                                <label htmlFor="region-status" className={labelClass}>Trạng thái</label>
                                <button
                                    id="region-status"
                                    type="button"
                                    onClick={() => updateForm("is_active", !form.is_active)}
                                    className={`flex min-h-12 w-full items-center justify-between rounded-2xl border px-4 py-3 text-left text-sm font-black transition focus:outline-none focus:ring-4 ${form.is_active ? "border-emerald-200 bg-emerald-50 text-emerald-700 focus:ring-emerald-100" : "border-[#3d2a18]/10 bg-[#efe2cf]/65 text-[#6f6254] focus:ring-[#3d2a18]/10"}`}
                                    aria-pressed={form.is_active}
                                >
                                    <span>{form.is_active ? "Đang hoạt động" : "Tạm ngưng"}</span>
                                    <span className={`h-3 w-3 rounded-full ${form.is_active ? "bg-emerald-500" : "bg-[#8b5e34]/45"}`} />
                                </button>
                                <p className="mt-2 px-1 text-xs font-semibold leading-5 text-[#8b5e34]/70">Khu vực tạm ngưng sẽ không ưu tiên hiển thị trong bộ lọc vận hành.</p>
                            </div>

                            <div>
                                <label htmlFor="region-code" className={labelClass}>Mã khu vực *</label>
                                <input id="region-code" className={`${inputClass} ${errors.code ? inputErrorClass : ""}`} value={form.code} placeholder="VD: KV-HCM-01" autoComplete="off" maxLength={50} aria-invalid={!!errors.code} aria-describedby={errors.code ? "region-code-error" : undefined} onChange={(event) => updateForm("code", event.target.value)} />
                                <div id="region-code-error"><FieldError message={errors.code} /></div>
                            </div>

                            <div>
                                <label htmlFor="region-name" className={labelClass}>Tên khu vực *</label>
                                <input id="region-name" className={`${inputClass} ${errors.name ? inputErrorClass : ""}`} value={form.name} placeholder="VD: Quận Bình Thạnh" autoComplete="off" maxLength={150} aria-invalid={!!errors.name} aria-describedby={errors.name ? "region-name-error" : undefined} onChange={(event) => updateForm("name", event.target.value)} />
                                <div id="region-name-error"><FieldError message={errors.name} /></div>
                            </div>

                            <div className="lg:col-span-2">
                                <label htmlFor="region-description" className={labelClass}>Mô tả</label>
                                <textarea id="region-description" className={`${inputClass} min-h-32 resize-y leading-6 ${errors.description ? inputErrorClass : ""}`} value={form.description} placeholder="Ghi chú phạm vi quản lý, tuyến đường hoặc đặc điểm khu vực..." maxLength={1000} aria-invalid={!!errors.description} aria-describedby={errors.description ? "region-description-error" : undefined} onChange={(event) => updateForm("description", event.target.value)} />
                                <div className="mt-2 flex items-start justify-between gap-3 px-1">
                                    <FieldError message={errors.description} />
                                    <span className="ml-auto text-xs font-bold text-[#8b5e34]/55">{form.description.trim().length}/1000</span>
                                </div>
                            </div>
                        </div>

                        <div className="flex flex-col gap-3 border-t border-[#3d2a18]/10 bg-[#fff7e8]/72 p-5 sm:flex-row sm:items-center sm:justify-end">
                            <Link to="/admin/facilities" className="inline-flex min-h-12 items-center justify-center rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-6 text-sm font-black uppercase tracking-widest text-[#6f6254] transition hover:border-[#f3c56b]/45 hover:bg-[#f3c56b]/15 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20">
                                Hủy
                            </Link>
                            <button type="button" onClick={submit} disabled={isSaving || isLoading} className="inline-flex min-h-12 items-center justify-center gap-2 rounded-2xl bg-[#24170d] px-6 text-sm font-black uppercase tracking-widest text-[#fff4df] shadow-xl shadow-[#6b3f1d]/18 transition hover:bg-[#3d2a18] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/30 disabled:cursor-not-allowed disabled:opacity-50">
                                <Save className="h-4 w-4 text-[#f3c56b] stroke-[2.8]" />
                                <span>{isSaving ? "Đang lưu..." : isEditing ? "Cập nhật khu vực" : "Lưu khu vực"}</span>
                            </button>
                        </div>
                    </section>

                    <aside className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/82 p-5 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
                        <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#f3c56b]/18 text-[#a65f16]">
                            <MapPin className="h-5 w-5" />
                        </div>
                        <h2 className="mt-4 text-lg font-black tracking-tight text-[#24170d]">Cấu trúc phân cấp</h2>
                        <div className="mt-4 space-y-3 text-sm font-semibold leading-6 text-[#6f6254]">
                            <p><span className="font-black text-[#24170d]">Cấp 1:</span> Không chọn khu vực cha, dùng cho tỉnh/thành phố hoặc nhóm lớn.</p>
                            <p><span className="font-black text-[#24170d]">Cấp 2:</span> Chọn khu vực cha, dùng cho quận/huyện/phường hoặc cụm nhỏ hơn.</p>
                        </div>
                        
                    </aside>
                </div>
            </div>
        </div>
    );
}
