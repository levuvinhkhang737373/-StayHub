import type { AdminBuildingDetailResource, AdminBuildingPayload, AdminBuildingServicePriceResource, AdminRegionResource } from "../types/facility-api.model";
import { formatMoneyInput, parseMoneyInput } from "../../../../shared/lib/utils/format";
import type { BuildingImage } from "../types/building.model";
import type { BuildingFormValues } from "../validations/building.validation";

export function getTodayIsoDate() {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, "0");
    const day = String(today.getDate()).padStart(2, "0");

    return `${year}-${month}-${day}`;
}

export function parseVietnameseDate(value: string) {
    const trimmedValue = value.trim();
    if (!trimmedValue) return "";

    const isoMatch = trimmedValue.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (isoMatch) return trimmedValue;

    const vietnameseMatch = trimmedValue.match(/^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$/);
    if (!vietnameseMatch) return trimmedValue;

    const day = vietnameseMatch[1].padStart(2, "0");
    const month = vietnameseMatch[2].padStart(2, "0");
    const year = vietnameseMatch[3];

    return `${year}-${month}-${day}`;
}

export function createDefaultBuildingForm(): BuildingFormValues {
    return {
        name: "",
        address: "",
        region_id: "",
        manager_admin_id: "",
        status: 1,
        gender_policy: 1,
        description: "",
        total_floors: 1,
        service_prices: [],
        settings: [],
    };
}

function isCurrentActiveServicePrice(price: AdminBuildingServicePriceResource) {
    return Number(price.status || 1) === 1 && !price.effective_to;
}

export function mapBuildingDetailToForm(
    building: AdminBuildingDetailResource | null | undefined,
    regions: AdminRegionResource[],
    currentForm: BuildingFormValues,
): BuildingFormValues {
    return {
        ...currentForm,
        name: building?.name || currentForm.name,
        address: building?.address || currentForm.address,
        region_id: String(building?.region_id || regions.find((region) => region.status)?.id || ""),
        manager_admin_id: building?.manager_admin_id ? String(building.manager_admin_id) : "",
        status: Number(building?.status || currentForm.status),
        gender_policy: Number(building?.gender_policy || currentForm.gender_policy),
        description: building?.description || currentForm.description,
        total_floors: building?.total_floors || currentForm.total_floors,

        service_prices: (building?.service_prices || []).filter(isCurrentActiveServicePrice).map((item) => ({
            id: item.id,
            service_id: String(item.service_id || ""),
            service_name: item.service_name || item.service?.name || "",
            price: item.price === null || item.price === undefined ? "0" : formatMoneyInput(String(item.price)),
            effective_from: item.effective_from || "",
            effective_to: item.effective_to || "",
            status: Number(item.status || 1),
        })),
        settings: (building?.settings || []).map((item) => ({
            id: item.id,
            source_id: item.id,
            setting_label: item.setting_label || "",
            setting_value: item.setting_value || "",
            description: item.description || "",
            is_public: Boolean(item.is_public),
        })),
    };
}

export function buildBuildingPayload({
    form,
    imageFiles,
    visibleExistingImages,
    deleteImageIds,
    primaryImageId,
    primaryNewImageIndex,

    deleteServicePriceIds,
    deleteSettingIds,
}: {
    form: BuildingFormValues;
    imageFiles: File[];
    visibleExistingImages: BuildingImage[];
    deleteImageIds: number[];
    primaryImageId: number | null;
    primaryNewImageIndex: number | null;
    deleteServicePriceIds: number[];
    deleteSettingIds: number[];
}): AdminBuildingPayload {
    const fallbackNewPrimary = imageFiles.length > 0 && visibleExistingImages.length === 0 && primaryNewImageIndex === null ? 0 : primaryNewImageIndex;

    return {
        region_id: Number(form.region_id),
        manager_admin_id: form.manager_admin_id ? Number(form.manager_admin_id) : undefined,
        name: form.name.trim(),
        address: form.address.trim() || undefined,
        description: form.description.trim() || undefined,
        total_floors: form.total_floors,
        gender_policy: Number(form.gender_policy),
        status: Number(form.status),
        images: imageFiles,
        image_metadata: imageFiles.map((_, index) => ({ is_primary: fallbackNewPrimary === index, sort_order: visibleExistingImages.length + index, status: 1 })),
        delete_image_ids: deleteImageIds,
        primary_image_id: primaryImageId || undefined,

        service_prices: form.service_prices.map((item) => ({
            id: item.id,
            service_id: Number(item.service_id),
            price: parseMoneyInput(item.price.trim()) || "0",
            effective_from: parseVietnameseDate(item.effective_from) || getTodayIsoDate(),
            effective_to: parseVietnameseDate(item.effective_to) || undefined,
            status: Number(item.status),
        })),
        delete_service_price_ids: deleteServicePriceIds,
        setting_ids: form.settings.filter((item) => item.source_id && !item.id).map((item) => item.source_id!),
        settings: form.settings.filter((item) => !item.source_id || item.id).map((item) => ({
            id: item.id,
            setting_label: item.setting_label.trim(),
            setting_value: item.setting_value.trim() || undefined,
            description: item.description.trim() || undefined,
            is_public: item.is_public,
        })),
        delete_setting_ids: deleteSettingIds,
    };
}
