import { useState, useCallback } from "react";

interface ConfirmOptions {
    title: string;
    message: string;
    confirmLabel?: string;
    cancelLabel?: string;
    onConfirm: () => void | Promise<void>;
    variant?: "danger" | "warning" | "info";
    hideCancel?: boolean;
}

interface ConfirmState extends ConfirmOptions {
    isOpen: boolean;
}

const defaultState: ConfirmState = {
    isOpen: false,
    title: "",
    message: "",
    onConfirm: () => {},
};

export function useConfirmModal() {
    const [confirmState, setConfirmState] = useState<ConfirmState>(defaultState);
    const [isConfirmLoading, setIsConfirmLoading] = useState(false);

    const showConfirm = useCallback((options: ConfirmOptions) => {
        setConfirmState({ ...options, isOpen: true });
    }, []);

    const closeConfirm = useCallback(() => {
        setConfirmState((prev) => ({ ...prev, isOpen: false }));
    }, []);

    const showAlert = useCallback((title: string, message: string, variant: "danger" | "warning" | "info" = "info") => {
        setConfirmState({
            isOpen: true,
            title,
            message,
            confirmLabel: "OK",
            onConfirm: () => {},
            variant,
            hideCancel: true,
        });
    }, []);

    return {
        confirmState,
        isConfirmLoading,
        setIsConfirmLoading,
        showConfirm,
        showAlert,
        closeConfirm,
    };
}
