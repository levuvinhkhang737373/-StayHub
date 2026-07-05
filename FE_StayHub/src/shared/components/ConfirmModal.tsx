import { useEffect } from "react";
import { AnimatePresence, motion } from "motion/react";
import { AlertTriangle, Info, Trash2, X } from "lucide-react";
import { cn } from "../lib/utils/cn";

export interface ConfirmModalProps {
    isOpen: boolean;
    title: string;
    message: string;
    confirmLabel?: string;
    cancelLabel?: string;
    onConfirm: () => void | Promise<void>;
    onCancel: () => void;
    variant?: "danger" | "warning" | "info";
    isLoading?: boolean;
    hideCancel?: boolean;
}

export function ConfirmModal({
    isOpen,
    title,
    message,
    confirmLabel = "Xác nhận",
    cancelLabel = "Hủy",
    onConfirm,
    onCancel,
    variant = "warning",
    isLoading = false,
    hideCancel = false,
}: ConfirmModalProps) {
    // Close on Escape key
    useEffect(() => {
        if (!isOpen) return;

        function handleKeyDown(event: KeyboardEvent) {
            if (event.key === "Escape" && !isLoading) onCancel();
        }

        document.addEventListener("keydown", handleKeyDown);
        return () => document.removeEventListener("keydown", handleKeyDown);
    }, [isOpen, onCancel, isLoading]);

    if (!isOpen) return null;

    const variantStyles = {
        danger: {
            bgIcon: "bg-rose-50 border border-rose-100 text-rose-600",
            icon: Trash2,
            confirmBtn: "bg-rose-600 hover:bg-rose-700 focus:ring-rose-200 text-white shadow-rose-600/10",
        },
        warning: {
            bgIcon: "bg-amber-50 border border-amber-100 text-amber-600",
            icon: AlertTriangle,
            confirmBtn: "bg-[#24170d] hover:bg-[#3d2a18] focus:ring-[#f3c56b]/30 text-[#fff4df] shadow-[#6b3f1d]/18",
        },
        info: {
            bgIcon: "bg-sky-50 border border-sky-100 text-sky-600",
            icon: Info,
            confirmBtn: "bg-sky-600 hover:bg-sky-700 focus:ring-sky-200 text-white shadow-sky-600/10",
        },
    };

    const styles = variantStyles[variant];
    const IconComponent = styles.icon;

    return (
        <AnimatePresence>
            {isOpen && (
                <div
                    className="fixed inset-0 z-[100] flex items-center justify-center p-4"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="confirm-modal-title"
                >
                    {/* Backdrop */}
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        onClick={() => {
                            if (!isLoading) onCancel();
                        }}
                        className="absolute inset-0 bg-stone-950/60 backdrop-blur-sm"
                    />

                    {/* Content Box */}
                    <motion.div
                        initial={{ y: 20, opacity: 0, scale: 0.96 }}
                        animate={{ y: 0, opacity: 1, scale: 1 }}
                        exit={{ y: 20, opacity: 0, scale: 0.96 }}
                        className="relative z-10 w-full max-w-md overflow-hidden rounded-3xl border border-[#3d2a18]/10 bg-[#fffaf1] p-6 shadow-2xl text-[#24170d]"
                    >
                        {/* Close button */}
                        {!isLoading && (
                            <button
                                type="button"
                                onClick={onCancel}
                                className="absolute right-4 top-4 flex h-8 w-8 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] transition hover:bg-[#f3c56b]/15 hover:text-[#24170d] focus:outline-none focus:ring-2 focus:ring-[#f3c56b]/20"
                                aria-label="Đóng"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        )}

                        <div className="flex flex-col items-center text-center">
                            {/* Icon */}
                            <div className={cn("mb-4 flex h-14 w-14 items-center justify-center rounded-2xl", styles.bgIcon)}>
                                <IconComponent className="h-7 w-7" />
                            </div>

                            {/* Title */}
                            <h3 id="confirm-modal-title" className="text-lg font-black tracking-tight text-[#24170d]">
                                {title}
                            </h3>

                            {/* Message */}
                            <p className="mt-3.5 text-sm font-semibold leading-relaxed text-[#6f6254]">
                                {message}
                            </p>
                        </div>

                        {/* Actions */}
                        <div className="mt-6 flex flex-col gap-2.5 sm:flex-row sm:justify-end">
                            {!hideCancel && (
                                <button
                                    type="button"
                                    onClick={onCancel}
                                    disabled={isLoading}
                                    className="inline-flex min-h-11 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] px-5 py-2 text-xs font-black uppercase tracking-wider text-[#6f6254] transition hover:border-[#f3c56b]/45 hover:bg-[#f3c56b]/15 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20 disabled:opacity-50 sm:flex-1 cursor-pointer"
                                >
                                    {cancelLabel}
                                </button>
                            )}
                            <button
                                type="button"
                                onClick={hideCancel ? onCancel : onConfirm}
                                disabled={isLoading}
                                className={cn(
                                    "inline-flex min-h-11 items-center justify-center rounded-xl px-5 py-2 text-xs font-black uppercase tracking-wider transition focus:outline-none focus:ring-4 disabled:opacity-50 sm:flex-1 cursor-pointer",
                                    styles.confirmBtn
                                )}
                            >
                                {isLoading ? "Đang xử lý..." : confirmLabel}
                            </button>
                        </div>
                    </motion.div>
                </div>
            )}
        </AnimatePresence>
    );
}
