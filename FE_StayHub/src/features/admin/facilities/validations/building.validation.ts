import type { AdminManagerResource, AdminRegionResource } from "../types/facility-api.model";

export type BuildingFormValues = {
    region_id: string;
    manager_admin_id: string;
    name: string;
    address: string;
    total_floors: number;
    gender_policy: number;
    description: string;
    status: number;
};

export type BuildingFormErrors = Partial<Record<keyof BuildingFormValues | "images", string>>;

export function validateBuildingForm(
    form: BuildingFormValues,
    regions: AdminRegionResource[],
    managers: AdminManagerResource[],
    imageFiles: File[] = [],
): BuildingFormErrors {
    const errors: BuildingFormErrors = {};
    const name = form.name.trim();
    const address = form.address.trim();

    if (!form.region_id || !regions.some((region) => region.id === Number(form.region_id))) {
        errors.region_id = "Vui lòng chọn khu vực hợp lệ.";
    }

    if (form.manager_admin_id && !managers.some((manager) => manager.id === Number(form.manager_admin_id))) {
        errors.manager_admin_id = "Người quản lý không hợp lệ.";
    }

    if (!name) {
        errors.name = "Vui lòng nhập tên tòa nhà.";
    } else if (name.length > 150) {
        errors.name = "Tên tòa nhà tối đa 150 ký tự.";
    }

    if (address.length > 500) {
        errors.address = "Địa chỉ tối đa 500 ký tự.";
    }

    if (!Number.isInteger(form.total_floors) || form.total_floors < 1 || form.total_floors > 1000) {
        errors.total_floors = "Tổng số tầng phải là số nguyên từ 1 đến 1000.";
    }

    if (![1, 2, 3].includes(Number(form.gender_policy))) {
        errors.gender_policy = "Chính sách giới tính không hợp lệ.";
    }

    if (![1, 2, 3].includes(Number(form.status))) {
        errors.status = "Trạng thái tòa nhà không hợp lệ.";
    }

    if (imageFiles.length > 20) {
        errors.images = "Mỗi lần chỉ được tải lên tối đa 20 ảnh tòa nhà.";
    } else {
        const invalidImage = imageFiles.find((file) => !["image/jpeg", "image/png", "image/webp"].includes(file.type) || file.size > 10 * 1024 * 1024);

        if (invalidImage) {
            errors.images = "Ảnh tòa nhà chỉ hỗ trợ JPG, PNG, WEBP và mỗi ảnh tối đa 10MB.";
        }
    }

    return errors;
}
