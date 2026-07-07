import { useEffect, useState } from "react";
import { AnimatePresence, motion } from "motion/react";
import { ReceiptText, Save, X } from "lucide-react";
import { AdminSelect } from "../../shared/components/AdminSelect";
import { getVisibleErrorMessage } from "../../shared/utils/error-message";
import { createAdminExpenseCategory, updateAdminExpenseCategory } from "../services/expense-categories.service";
import { validateExpenseCategoryForm, type ExpenseCategoryFormErrors } from "../validations/expense-category.validation";

interface ExpenseCategoryModalProps {
    isOpen: boolean;
    onClose: () => void;
    editingExpenseCategoryId: number | null;
    form: {
        name: string;
        description: string;
        is_active: boolean;
    };
    setForm: React.Dispatch<React.SetStateAction<{
        name: string;
        description: string;
        is_active: boolean;
    }>>;
    onCancel: () => void;
    onSubmitSuccess: () => void;
    isFormLoading?: boolean;
}

const inputClass = "w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-3 text-sm font-bold text-[#3d2a18] outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20";
const inputErrorClass = "border-rose-300 bg-rose-50 focus:border-rose-400 focus:ring-rose-100";
const labelClass = "mb-1.5 block px-1 text-[10px] font-black uppercase tracking-widest text-[#8b5e34]/65";

const formStatusOptions = [
    { value: 1, label: 'Đang sử dụng', tone: 'success' as const },
    { value: 0, label: 'Hết sử dụng', tone: 'danger' as const },
];

function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <p className="mt-2 px-1 text-xs font-black text-rose-600" role="alert">{message}</p>;
}

export function ExpenseCategoryModal({
    isOpen,
    onClose,
    editingExpenseCategoryId,
    form,
    setForm,
    onCancel,
    onSubmitSuccess,
    isFormLoading = false,
}: ExpenseCategoryModalProps) {
    const isEditing = editingExpenseCategoryId !== null;
    const [isSaving, setIsSaving] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [errors, setErrors] = useState<ExpenseCategoryFormErrors>({});

    useEffect(() => {
        if (!isOpen) return;

        function handleKeyDown(event: KeyboardEvent) {
            if (event.key === "Escape") onClose();
        }

        document.addEventListener("keydown", handleKeyDown);
        return () => document.removeEventListener("keydown", handleKeyDown);
    }, [isOpen, onClose]);

    useEffect(() => {
        setErrors({});
        setErrorMessage(null);
    }, [isOpen, editingExpenseCategoryId]);

    const updateForm = (key: keyof typeof form, value: string | boolean) => {
        setForm((current) => ({ ...current, [key]: value }));
        setErrors((current) => ({ ...current, [key]: undefined }));
    };

    const submit = async () => {
        if (isSaving || isFormLoading) return;

        const nextErrors = validateExpenseCategoryForm(form);
        setErrors(nextErrors);

        if (Object.keys(nextErrors).length > 0) {
            setErrorMessage("Vui lòng kiểm tra lại thông tin danh mục chi phí.");
            return;
        }

        try {
            setIsSaving(true);
            setErrorMessage(null);

            const payload = {
                name: form.name.trim(),
                description: form.description.trim(),
                is_active: Boolean(form.is_active),
            };

            if (isEditing && editingExpenseCategoryId) {
                await updateAdminExpenseCategory(editingExpenseCategoryId, payload);
            } else {
                await createAdminExpenseCategory(payload);
            }

            onSubmitSuccess();
        } catch (error) {
            setErrorMessage(getVisibleErrorMessage(error, "Không thể lưu danh mục chi phí."));
        } finally {
            setIsSaving(false);
        }
    };

    if (!isOpen) return null;

    return (
        <AnimatePresence>
            {isOpen && (
                <div className="fixed inset-0 z-[60] flex items-center justify-center p-3 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="expense-modal-title">
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        onClick={onClose}
                        className="absolute inset-0 bg-stone-950/70 backdrop-blur-md"
                    />

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
                                        <ReceiptText className="h-3.5 w-3.5" /> Danh mục chi phí
                                    </div>
                                    <h2 id="expense-modal-title" className="mt-1 text-xl font-black tracking-tight text-[#fff4df]">
                                        {isEditing ? "Cập nhật danh mục chi phí" : "Thêm danh mục chi phí"}
                                    </h2>
                                </div>
                                <button
                                    type="button"
                                    onClick={onClose}
                                    className="flex h-9 w-9 items-center justify-center rounded-xl border border-white/10 bg-white/10 text-white backdrop-blur-md transition-all hover:bg-white/20 focus:outline-none focus:ring-4"
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
                                    Đang tải dữ liệu danh mục...
                                </div>
                            ) : (
                                <>
                                    <div>
                                        <label className={labelClass}>Tên danh mục</label>
                                        <input
                                            className={`${inputClass} ${errors.name ? inputErrorClass : ''}`}
                                            value={form.name}
                                            onChange={(event) => updateForm('name', event.target.value)}
                                            placeholder="Ví dụ: Sửa chữa, Vệ sinh, Internet"
                                            disabled={isSaving}
                                        />
                                        <FieldError message={errors.name} />
                                    </div>

                                    <div>
                                        <label className={labelClass}>Trạng thái</label>
                                        <AdminSelect
                                            value={form.is_active ? 1 : 0}
                                            options={formStatusOptions}
                                            invalid={!!errors.is_active}
                                            disabled={isSaving}
                                            onChange={(nextValue) => updateForm('is_active', Number(nextValue) === 1)}
                                        />
                                        <FieldError message={errors.is_active} />
                                    </div>

                                    <div>
                                        <label className={labelClass}>Mô tả</label>
                                        <textarea
                                            className={`${inputClass} min-h-24 resize-y`}
                                            value={form.description}
                                            onChange={(event) => updateForm('description', event.target.value)}
                                            placeholder="Mô tả phạm vi sử dụng của danh mục chi phí..."
                                            disabled={isSaving}
                                        />
                                        <FieldError message={errors.description} />
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
                                className="inline-flex min-h-12 items-center justify-center rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-6 text-sm font-black uppercase tracking-widest text-[#6f6254] transition hover:border-[#f3c56b]/45 hover:bg-[#f3c56b]/15 hover:text-[#24170d] focus:outline-none focus:ring-4 disabled:opacity-50"
                            >
                                Hủy
                            </button>
                            <button
                                type="button"
                                onClick={submit}
                                disabled={isSaving || isFormLoading}
                                className="inline-flex min-h-12 items-center justify-center gap-2 rounded-2xl bg-[#24170d] px-6 text-sm font-black uppercase tracking-widest text-[#fff4df] shadow-xl shadow-[#6b3f1d]/18 transition hover:bg-[#3d2a18] focus:outline-none focus:ring-4 disabled:opacity-50"
                            >
                                <Save className="h-4 w-4 text-[#f3c56b] stroke-[2.8]" />
                                <span>{isSaving ? "Đang lưu..." : "Lưu danh mục"}</span>
                            </button>
                        </div>
                    </motion.div>
                </div>
            )}
        </AnimatePresence>
    );
}
