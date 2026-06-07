import { X } from "lucide-react";
import { useEffect } from "react";

interface ImageViewerModalProps {
    isOpen: boolean;
    src: string | null;
    alt?: string;
    onClose: () => void;
}

export function ImageViewerModal({ isOpen, src, alt = "Image preview", onClose }: ImageViewerModalProps) {
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === "Escape") onClose();
        };

        if (isOpen) {
            document.body.style.overflow = "hidden";
            window.addEventListener("keydown", handleKeyDown);
        }

        return () => {
            document.body.style.overflow = "unset";
            window.removeEventListener("keydown", handleKeyDown);
        };
    }, [isOpen, onClose]);

    if (!isOpen || !src) return null;

    return (
        <div
            className="fixed inset-0 z-[9999] flex items-center justify-center bg-black/80 backdrop-blur-sm transition-opacity"
            onClick={onClose}
        >
            <button
                type="button"
                onClick={onClose}
                className="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white transition-colors hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50"
                aria-label="Đóng"
            >
                <X className="h-6 w-6" />
            </button>
            <img
                src={src}
                alt={alt}
                onError={(e) => {
                    const target = e.currentTarget;
                    if (target.src !== window.location.origin + "/images/stayhub.png") {
                        target.src = "/images/stayhub.png";
                    }
                }}
                className="max-h-[90vh] max-w-[90vw] object-contain rounded-lg shadow-2xl bg-white"
                onClick={(e) => e.stopPropagation()}
            />
        </div>
    );
}
