import type { AdminRegionResource } from "../types/facility-api.model";

export type RegionFormValues = {
    parent_id: string;
    code: string;
    name: string;
    description: string;
};

export type RegionFormErrors = Partial<Record<keyof RegionFormValues, string>>;

export function validateRegionForm(form: RegionFormValues, regions: AdminRegionResource[]): RegionFormErrors {
    const errors: RegionFormErrors = {};
    const code = form.code.trim();
    const name = form.name.trim();
    const description = form.description.trim();

    if (form.parent_id && !regions.some((region) => region.id === Number(form.parent_id))) {
        errors.parent_id = "Khu vực cha không hợp lệ.";
    }

    if (!code) {
        errors.code = "Vui lòng nhập mã khu vực.";
    } else if (code.length > 50) {
        errors.code = "Mã khu vực tối đa 50 ký tự.";
    } else if (!/^[A-Za-z0-9_-]+$/.test(code)) {
        errors.code = "Mã khu vực chỉ gồm chữ, số, dấu gạch ngang hoặc gạch dưới.";
    } else if (regions.some((region) => region.code.toLowerCase() === code.toLowerCase())) {
        errors.code = "Mã khu vực đã tồn tại.";
    }

    if (!name) {
        errors.name = "Vui lòng nhập tên khu vực.";
    } else if (name.length > 150) {
        errors.name = "Tên khu vực tối đa 150 ký tự.";
    }

    if (description.length > 1000) {
        errors.description = "Mô tả tối đa 1000 ký tự.";
    }

    return errors;
}
