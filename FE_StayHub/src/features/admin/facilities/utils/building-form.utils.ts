import type { AdminBuildingDetailResource, AdminBuildingPayload, AdminBuildingServicePriceResource, AdminRegionResource } from "../types/facility-api.model";
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
        room_types: [],
        asset_templates: [],
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
        room_types: (building?.room_types || []).map((item) => ({
            id: item.id,
            source_id: item.id,
            name: item.name || "",
            description: item.description || "",
            status: Number(item.status || 1),
            rooms_count: item.rooms_count || 0,
        })),
        asset_templates: (building?.asset_templates || []).map((item) => ({
            id: item.id,
            source_id: item.id,
            name: item.name || "",
            default_unit_name: Number(item.default_unit_name || 1),
            description: item.description || "",
            status: Number(item.status || 1),
            room_assets_count: item.room_assets_count || 0,
        })),
        service_prices: (building?.service_prices || []).filter(isCurrentActiveServicePrice).map((item) => ({
            id: item.id,
            service_id: String(item.service_id || ""),
            service_name: item.service_name || item.service?.name || "",
            price: item.price === null || item.price === undefined ? "0" : String(item.price),
            effective_from: item.effective_from || "",
            effective_to: item.effective_to || "",
            status: Number(item.status || 1),
        })),
        settings: (building?.settings || []).map((item) => ({
            id: item.id,
            source_id: item.id,
            setting_label: item.setting_label || "",
            setting_name: item.setting_name || "",
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
    deleteRoomTypeIds,
    deleteAssetTemplateIds,
    deleteServicePriceIds,
    deleteSettingIds,
}: {
    form: BuildingFormValues;
    imageFiles: File[];
    visibleExistingImages: BuildingImage[];
    deleteImageIds: number[];
    primaryImageId: number | null;
    primaryNewImageIndex: number | null;
    deleteRoomTypeIds: number[];
    deleteAssetTemplateIds: number[];
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
        room_type_ids: form.room_types.filter((item) => item.source_id && !item.id).map((item) => item.source_id!),
        room_types: form.room_types.filter((item) => !item.source_id || item.id).map((item) => ({
            id: item.id,
            name: item.name.trim(),
            description: item.description.trim() || undefined,
            status: Number(item.status),
        })),
        delete_room_type_ids: deleteRoomTypeIds,
        asset_template_ids: form.asset_templates.filter((item) => item.source_id && !item.id).map((item) => item.source_id!),
        asset_templates: form.asset_templates.filter((item) => !item.source_id || item.id).map((item) => ({
            id: item.id,
            name: item.name.trim(),
            default_unit_name: Number(item.default_unit_name),
            description: item.description.trim() || undefined,
            status: Number(item.status),
        })),
        delete_asset_template_ids: deleteAssetTemplateIds,
        service_prices: form.service_prices.map((item) => ({
            id: item.id,
            service_id: Number(item.service_id),
            price: item.price.trim() || "0",
            effective_from: parseVietnameseDate(item.effective_from) || getTodayIsoDate(),
            effective_to: parseVietnameseDate(item.effective_to) || undefined,
            status: Number(item.status),
        })),
        delete_service_price_ids: deleteServicePriceIds,
        setting_ids: form.settings.filter((item) => item.source_id && !item.id).map((item) => item.source_id!),
        settings: form.settings.filter((item) => !item.source_id || item.id).map((item) => ({
            id: item.id,
            setting_label: item.setting_label.trim(),
            setting_name: item.setting_name.trim(),
            setting_value: item.setting_value.trim() || undefined,
            description: item.description.trim() || undefined,
            is_public: item.is_public,
        })),
        delete_setting_ids: deleteSettingIds,
    };
}
