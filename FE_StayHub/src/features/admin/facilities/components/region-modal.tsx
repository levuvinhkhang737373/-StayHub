import { useEffect, useMemo, useState } from "react";
import { AnimatePresence, motion } from "motion/react";
import { MapPin, Save, X } from "lucide-react";
import { AdminSelect } from "../../shared/components/AdminSelect";
import { createAdminRegion, updateAdminRegion } from "../services/facilities.service";
import type { AdminRegionPayload, AdminRegionResource } from "../types/facility-api.model";
import { getVisibleErrorMessage } from "../../shared/utils/error-message";
import { validateRegionForm, type RegionFormErrors } from "../validations/region.validation";

interface RegionModalProps {
    isOpen: boolean;
    onClose: () => void; // Closes without resetting draft data
    regions: AdminRegionResource[];
    editingRegionId: number | null;
    form: {
        parent_id: string;
        code: string;
        name: string;
        description: string;
        is_active: boolean;
    };
    setForm: React.Dispatch<React.SetStateAction<{
        parent_id: string;
        code: string;
        name: string;
        description: string;
        is_active: boolean;
    }>>;
    onCancel: () => void; // Triggered when clicking "Hủy" (resets data and closes)
    onSubmitSuccess: () => void; // Triggered after successful save (clears data)
    isFormLoading?: boolean;
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

export function RegionModal({
    isOpen,
    onClose,
    regions,
    editingRegionId,
    form,
    setForm,
    onCancel,
    onSubmitSuccess,
    isFormLoading = false,
}: RegionModalProps) {
    const isEditing = editingRegionId !== null;
    const [isSaving, setIsSaving] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [errors, setErrors] = useState<RegionFormErrors>({});

    // Close on Escape key press
    useEffect(() => {
        if (!isOpen) return;

        function handleKeyDown(event: KeyboardEvent) {
            if (event.key === "Escape") onClose();
        }

        document.addEventListener("keydown", handleKeyDown);
        return () => document.removeEventListener("keydown", handleKeyDown);
    }, [isOpen, onClose]);

    // Clear local validation errors when opening/closing or switching mode
    useEffect(() => {
        setErrors({});
        setErrorMessage(null);
    }, [isOpen, editingRegionId]);

    const activeParentRegions = useMemo(() => {
        // Exclude current editing region and its descendants if in edit mode to prevent circular reference
        return regions.filter((region) => {
            const isSelf = isEditing && region.id === editingRegionId;
            const isDescendant = isEditing && region.path?.split("/").includes(String(editingRegionId));
            return region.status && !region.parent_id && !isSelf && !isDescendant;
        });
    }, [regions, isEditing, editingRegionId]);

    const parentRegionOptions = useMemo(() => [
        { value: "", label: "Không chọn - tạo khu vực cấp 1", description: "Khu vực gốc trong hệ thống", tone: "warning" as const },
        ...activeParentRegions.map((region) => ({ value: region.id, label: formatRegionOption(region), tone: "default" as const })),
    ], [activeParentRegions]);

    const updateForm = (key: keyof typeof form, value: string | boolean) => {
        setForm((current) => ({ ...current, [key]: value }));
        setErrors((current) => ({ ...current, [key]: undefined }));
    };

    const submit = async () => {
        if (isSaving || isFormLoading) return;

        const nextErrors = validateRegionForm(
            form,
            isEditing && editingRegionId ? regions.filter((region) => region.id !== editingRegionId) : regions
        );
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

            onSubmitSuccess();
        } catch (error) {
            setErrorMessage(getVisibleErrorMessage(error, "Không thể lưu khu vực."));
        } finally {
            setIsSaving(false);
        }
    };

    if (!isOpen) return null;

    return (
        <AnimatePresence>
            {isOpen && (
                <div className="fixed inset-0 z-[60] flex items-center justify-center p-3 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="region-modal-title">
                    {/* Backdrop Overlay */}
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        onClick={onClose}
                        className="absolute inset-0 bg-stone-950/70 backdrop-blur-md"
                    />

                    {/* Modal Content container */}
                    <motion.div
                        initial={{ y: 40, opacity: 0, scale: 0.98 }}
                        animate={{ y: 0, opacity: 1, scale: 1 }}
                        exit={{ y: 40, opacity: 0, scale: 0.98 }}
                        className="relative z-10 flex max-h-[92vh] w-full max-w-xl flex-col overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1] shadow-2xl shadow-black/40 text-[#24170d]"
                    >
                        {/* Header */}
                        <div className="relative overflow-hidden bg-[#24170d] p-5 text-[#fff4df]">
                            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_18%,rgba(243,197,107,0.2),transparent_30%),linear-gradient(135deg,#24170d_0%,#3d2a18_100%)]" />
                            <div className="relative flex items-center justify-between">
                                <div>
                                    <div className="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-[0.2em] text-[#f3c56b]">
                                        <MapPin className="h-3.5 w-3.5" /> Khu vực StayHub
                                    </div>
                                    <h2 id="region-modal-title" className="mt-1 text-xl font-black tracking-tight text-[#fff4df]">
                                        {isEditing ? "Sửa khu vực" : "Thêm khu vực mới"}
                                    </h2>
                                </div>
                                <button
                                    type="button"
                                    onClick={onClose}
                                    className="flex h-9 w-9 items-center justify-center rounded-xl border border-white/10 bg-white/10 text-white backdrop-blur-md transition-all hover:bg-white/20 focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20"
                                    aria-label="Đóng modal"
                                >
                                    <X className="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        {/* Form Body */}
                        <div className="flex-1 overflow-y-auto p-5 space-y-4">
                            {errorMessage && (
                                <div role="alert" className="rounded-2xl border border-rose-200 bg-rose-50/95 p-4 text-sm font-black text-rose-700 shadow-sm">
                                    {errorMessage}
                                </div>
                            )}

                            {isFormLoading ? (
                                <div className="py-10 text-center font-bold text-[#8b5e34]/70">
                                    Đang tải dữ liệu khu vực...
                                </div>
                            ) : (
                                <>
                                    <div>
                                        <label htmlFor="region-parent" className={labelClass}>Khu vực cha</label>
                                        <AdminSelect
                                            id="region-parent"
                                            value={form.parent_id}
                                            options={parentRegionOptions}
                                            disabled={isSaving}
                                            invalid={!!errors.parent_id}
                                            ariaDescribedBy={errors.parent_id ? "region-parent-error" : undefined}
                                            onChange={(nextValue) => updateForm("parent_id", String(nextValue))}
                                        />
                                        <div id="region-parent-error"><FieldError message={errors.parent_id} /></div>
                                        <p className="mt-1.5 px-1 text-[11px] font-semibold leading-relaxed text-[#8b5e34]/70">
                                            Chỉ chọn khi khu vực mới là cấp con của một khu vực hiện có.
                                        </p>
                                    </div>

                                    <div>
                                        <label htmlFor="region-status" className={labelClass}>Trạng thái</label>
                                        <button
                                            id="region-status"
                                            type="button"
                                            onClick={() => updateForm("is_active", !form.is_active)}
                                            disabled={isSaving}
                                            className={`flex min-h-12 w-full items-center justify-between rounded-2xl border px-4 py-3 text-left text-sm font-black transition focus:outline-none focus:ring-4 ${
                                                form.is_active
                                                    ? "border-emerald-200 bg-emerald-50 text-emerald-700 focus:ring-emerald-100"
                                                    : "border-[#3d2a18]/10 bg-[#efe2cf]/65 text-[#6f6254] focus:ring-[#3d2a18]/10"
                                            }`}
                                            aria-pressed={form.is_active}
                                        >
                                            <span>{form.is_active ? "Đang hoạt động" : "Tạm ngưng"}</span>
                                            <span className={`h-3 w-3 rounded-full ${form.is_active ? "bg-emerald-500" : "bg-[#8b5e34]/45"}`} />
                                        </button>
                                        <p className="mt-1.5 px-1 text-[11px] font-semibold leading-relaxed text-[#8b5e34]/70">
                                            Khu vực tạm ngưng sẽ không ưu tiên hiển thị trong bộ lọc vận hành.
                                        </p>
                                    </div>

                                    <div>
                                        <label htmlFor="region-code" className={labelClass}>Mã khu vực *</label>
                                        <input
                                            id="region-code"
                                            className={`${inputClass} ${errors.code ? inputErrorClass : ""}`}
                                            value={form.code}
                                            placeholder="VD: KV-HCM-01"
                                            autoComplete="off"
                                            maxLength={50}
                                            disabled={isSaving}
                                            aria-invalid={!!errors.code}
                                            aria-describedby={errors.code ? "region-code-error" : undefined}
                                            onChange={(event) => updateForm("code", event.target.value)}
                                        />
                                        <div id="region-code-error"><FieldError message={errors.code} /></div>
                                    </div>

                                    <div>
                                        <label htmlFor="region-name" className={labelClass}>Tên khu vực *</label>
                                        <input
                                            id="region-name"
                                            className={`${inputClass} ${errors.name ? inputErrorClass : ""}`}
                                            value={form.name}
                                            placeholder="VD: Quận Bình Thạnh"
                                            autoComplete="off"
                                            maxLength={150}
                                            disabled={isSaving}
                                            aria-invalid={!!errors.name}
                                            aria-describedby={errors.name ? "region-name-error" : undefined}
                                            onChange={(event) => updateForm("name", event.target.value)}
                                        />
                                        <div id="region-name-error"><FieldError message={errors.name} /></div>
                                    </div>

                                    <div>
                                        <label htmlFor="region-description" className={labelClass}>Mô tả</label>
                                        <textarea
                                            id="region-description"
                                            className={`${inputClass} min-h-24 resize-y leading-relaxed ${errors.description ? inputErrorClass : ""}`}
                                            value={form.description}
                                            placeholder="Ghi chú phạm vi quản lý, tuyến đường hoặc đặc điểm..."
                                            maxLength={1000}
                                            disabled={isSaving}
                                            aria-invalid={!!errors.description}
                                            aria-describedby={errors.description ? "region-description-error" : undefined}
                                            onChange={(event) => updateForm("description", event.target.value)}
                                        />
                                        <div className="mt-1.5 flex items-start justify-between gap-3 px-1">
                                            <FieldError message={errors.description} />
                                            <span className="ml-auto text-xs font-bold text-[#8b5e34]/55">{form.description.trim().length}/1000</span>
                                        </div>
                                    </div>
                                </>
                            )}
                        </div>

                        {/* Footer Actions */}
                        <div className="flex flex-col gap-3 border-t border-[#3d2a18]/10 bg-[#fff7e8]/70 p-5 sm:flex-row sm:items-center sm:justify-end">
                            <button
                                type="button"
                                onClick={onCancel}
                                disabled={isSaving || isFormLoading}
                                className="inline-flex min-h-12 items-center justify-center rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-6 text-sm font-black uppercase tracking-widest text-[#6f6254] transition hover:border-[#f3c56b]/45 hover:bg-[#f3c56b]/15 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20 disabled:opacity-50"
                            >
                                Hủy
                            </button>
                            <button
                                type="button"
                                onClick={submit}
                                disabled={isSaving || isFormLoading}
                                className="inline-flex min-h-12 items-center justify-center gap-2 rounded-2xl bg-[#24170d] px-6 text-sm font-black uppercase tracking-widest text-[#fff4df] shadow-xl shadow-[#6b3f1d]/18 transition hover:bg-[#3d2a18] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/30 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <Save className="h-4 w-4 text-[#f3c56b] stroke-[2.8]" />
                                <span>{isSaving ? "Đang lưu..." : "Lưu khu vực"}</span>
                            </button>
                        </div>
                    </motion.div>
                </div>
            )}
        </AnimatePresence>
    );
}
