import type { AdminManagerResource, AdminRegionResource } from "../types/facility-api.model";



export type BuildingServicePriceFormRow = {
    id?: number;
    service_id: string;
    service_name?: string;
    price: string;
    effective_from: string;
    effective_to: string;
    status: number;
};

export type BuildingSettingFormRow = {
    id?: number;
    source_id?: number;
    setting_label: string;
    setting_value: string;
    description: string;
    is_public: boolean;
};

function normalizeDateValue(value: string) {
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

function isValidDateValue(value: string) {
    if (!value.trim()) return true;
    return /^\d{4}-\d{2}-\d{2}$/.test(normalizeDateValue(value));
}

function isValidMoneyValue(value: string) {
    return /^\d+(\.\d{1,2})?$/.test(value.trim());
}

export type BuildingFormValues = {
    region_id: string;
    manager_admin_id: string;
    name: string;
    address: string;
    total_floors: number;
    gender_policy: number;
    description: string;
    status: number;
    service_prices: BuildingServicePriceFormRow[];
    settings: BuildingSettingFormRow[];
};

export type BuildingFormErrors = Partial<Record<keyof Omit<BuildingFormValues, "service_prices" | "settings"> | "images" | "service_prices" | "settings", string>>;

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



    if (form.service_prices.length > 100) {
        errors.service_prices = "Mỗi lần chỉ được gửi tối đa 100 bảng giá dịch vụ.";
    } else if (form.service_prices.some((item) => {
        return !item.service_id
            || !isValidMoneyValue(item.price)
            || ![1, 2].includes(Number(item.status))
            || !isValidDateValue(item.effective_from);
    })) {
        errors.service_prices = "Bảng giá cần chọn dịch vụ, số tiền không hợp lệ hoặc ngày bắt đầu hiệu lực không đúng.";
    }

    if (form.settings.length > 100) {
        errors.settings = "Mỗi lần chỉ được gửi tối đa 100 cài đặt.";
    } else {
        const invalidSetting = form.settings.find((item) => {
            if (!item.setting_label.trim()) return true;
            return false;
        });

        if (invalidSetting) {
            errors.settings = "Cài đặt cần có tên hiển thị.";
        }
    }

    return errors;
}
