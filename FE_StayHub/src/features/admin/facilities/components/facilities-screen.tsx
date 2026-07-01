import React, { useCallback, useEffect, useMemo, useState } from "react";
import { Link, Navigate, useNavigate, useLocation } from "react-router-dom";
import { Building2, ChevronLeft, ChevronRight, Eye, MapPin, Pencil, Plus, Power, Search, Trash2, X } from "lucide-react";
import { isSuperAdminRole, useAdminSession } from "../../auth/hooks/use-admin-session";
import { BuildingDetailModal } from "./building-detail-modal";
import { RegionModal } from "./region-modal";
import { deleteAdminBuilding, deleteAdminRegion, fetchAdminBuildingDetail, fetchAdminBuildings, fetchAdminRegions, fetchAdminRegionDetail, updateAdminBuildingStatus, updateAdminRegionStatus } from "../services/facilities.service";
import type { AdminRegionResource } from "../types/facility-api.model";
import type { Building, BuildingStatus } from "../types/building.model";
import { cn } from "../../../../shared/lib/utils/cn";
import { AdminSelect } from "../../shared/components/AdminSelect";
import { mapBuildingResourceToBuilding } from "../lib/data-utils";
import { ImageViewerModal } from "../../../../shared/components/ImageViewerModal";

type BuildingStatusFilter = "all" | BuildingStatus;

function getResourceList<T>(result: { data?: T[] } | T[] | null | undefined): T[] {
    if (!result) return [];
    if (Array.isArray(result)) return result;
    return result.data || [];
}

const statusLabels: Record<BuildingStatus, string> = {
    active: "Hoạt động",
    inactive: "Ngừng hoạt động",
    maintenance: "Bảo trì",
};

const statusClassNames: Record<BuildingStatus, string> = {
    active: "border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59] shadow-[#0f766e]/5",
    inactive: "border-[#3d2a18]/10 bg-[#efe2cf]/65 text-[#6f6254] shadow-[#6b3f1d]/5",
    maintenance: "border-[#f3c56b]/45 bg-[#f3c56b]/18 text-[#8a4f18] shadow-[#a65f16]/5",
};

const perPageOptions = [
    { value: 5, label: "5 dòng", tone: "default" as const },
    { value: 10, label: "10 dòng", tone: "default" as const },
    { value: 20, label: "20 dòng", tone: "default" as const },
    { value: 50, label: "50 dòng", tone: "default" as const },
];

const stayHubImage = "/images/stayhub.png";

function countByStatus(buildings: Building[], status: BuildingStatusFilter) {
    if (status === "all") return buildings.length;
    return buildings.filter((building) => building.status === status).length;
}

function getAllChildRegionIds(regions: AdminRegionResource[], regionId: number): number[] {
    const children = regions.filter((region) => region.parent_id === regionId);
    return children.reduce<number[]>((ids, child) => [...ids, ...getAllChildRegionIds(regions, child.id)], [regionId]);
}

export function FacilitiesScreen() {
    const [keyword, setKeyword] = useState("");
    const [regionKeyword, setRegionKeyword] = useState("");
    const [regions, setRegions] = useState<AdminRegionResource[]>([]);
    const [buildings, setBuildings] = useState<Building[]>([]);
    const [expandedIds, setExpandedIds] = useState<number[]>([]);
    const [selectedRegionId, setSelectedRegionId] = useState<number | null>(null);
    const [selectedStatus, setSelectedStatus] = useState<BuildingStatusFilter>("all");
    const [isViewModalOpen, setIsViewModalOpen] = useState(false);
    const [viewingBuilding, setViewingBuilding] = useState<Building | null>(null);
    const [isDetailLoading, setIsDetailLoading] = useState(false);
    const [detailErrorMessage, setDetailErrorMessage] = useState<string | null>(null);
    const [statusChangingId, setStatusChangingId] = useState<number | null>(null);
    const [perPage, setPerPage] = useState(10);
    const [isLoading, setIsLoading] = useState(true);
    const [viewingImageSrc, setViewingImageSrc] = useState<string | null>(null);
    const navigate = useNavigate();
    const location = useLocation();
    const { session } = useAdminSession();
    const isSuperAdmin = isSuperAdminRole(session?.admin.role);

    const [successMessage, setSuccessMessage] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [activeMessage, setActiveMessage] = useState<string | null>(null);
    const [activeType, setActiveType] = useState<"success" | "error" | null>(null);

    // Read success message from navigation state (e.g. from create/edit building screens)
    useEffect(() => {
        if (location.state?.successMessage) {
            setSuccessMessage(location.state.successMessage);
            // Clear the router state to avoid displaying it again on refresh
            navigate(location.pathname, { replace: true });
        }
    }, [location, navigate]);

    // Handle auto-clear and transition logic
    useEffect(() => {
        if (successMessage) {
            const timer = setTimeout(() => {
                setSuccessMessage(null);
            }, 3000);
            return () => clearTimeout(timer);
        }
    }, [successMessage]);

    useEffect(() => {
        if (errorMessage) {
            const timer = setTimeout(() => {
                setErrorMessage(null);
            }, 3000);
            return () => clearTimeout(timer);
        }
    }, [errorMessage]);

    useEffect(() => {
        if (successMessage) {
            setActiveMessage(successMessage);
            setActiveType("success");
        } else if (errorMessage) {
            setActiveMessage(errorMessage);
            setActiveType("error");
        } else {
            const timer = setTimeout(() => {
                setActiveMessage(null);
                setActiveType(null);
            }, 500);
            return () => clearTimeout(timer);
        }
    }, [successMessage, errorMessage]);

    // Region modal state and handlers
    const [isRegionModalOpen, setIsRegionModalOpen] = useState(false);
    const [editingRegionId, setEditingRegionId] = useState<number | null>(null);
    const [isRegionFormLoading, setIsRegionFormLoading] = useState(false);
    const [regionForm, setRegionForm] = useState({
        parent_id: "",
        code: "",
        name: "",
        description: "",
        is_active: true,
    });

    const defaultRegionForm = useMemo(() => ({
        parent_id: "",
        code: "",
        name: "",
        description: "",
        is_active: true,
    }), []);

    const openCreateRegionModal = () => {
        if (editingRegionId !== null) {
            setRegionForm(defaultRegionForm);
        }
        setEditingRegionId(null);
        setIsRegionModalOpen(true);
    };

    const openEditRegionModal = async (region: AdminRegionResource) => {
        if (editingRegionId !== region.id) {
            setEditingRegionId(region.id);
            setIsRegionFormLoading(true);
            setIsRegionModalOpen(true);
            try {
                const response = await fetchAdminRegionDetail(region.id);
                const detail = response.result;
                setRegionForm({
                    parent_id: detail.parent_id ? String(detail.parent_id) : "",
                    code: detail.code || "",
                    name: detail.name || "",
                    description: detail.description || "",
                    is_active: detail.status,
                });
            } catch (error) {
                setErrorMessage(error instanceof Error ? error.message : "Không thể tải thông tin khu vực.");
                setIsRegionModalOpen(false);
                setEditingRegionId(null);
            } finally {
                setIsRegionFormLoading(false);
            }
        } else {
            setIsRegionModalOpen(true);
        }
    };

    const handleCancelRegionModal = () => {
        setIsRegionModalOpen(false);
        setEditingRegionId(null);
        setRegionForm(defaultRegionForm);
    };

    const handleCloseRegionModal = () => {
        setIsRegionModalOpen(false);
    };

    const handleRegionSubmitSuccess = async () => {
        const isEdit = editingRegionId !== null;
        setIsRegionModalOpen(false);
        setEditingRegionId(null);
        setRegionForm(defaultRegionForm);
        setSuccessMessage(isEdit ? "Cập nhật khu vực thành công." : "Tạo khu vực thành công.");
        await loadFacilities();
    };

    const isSearchingRegions = regionKeyword.trim() !== "";
    const activeRegions = useMemo(() => regions.filter((region) => region.status), [regions]);
    const rootRegions = useMemo(() => {
        if (isSearchingRegions) {
            const regionIds = new Set(regions.map((region) => region.id));

            return regions.filter((region) => !region.parent_id || !regionIds.has(region.parent_id));
        }

        return regions.filter((region) => !region.parent_id || region.level === "city" || region.level === "province");
    }, [regions, isSearchingRegions]);

    const loadFacilities = useCallback(async () => {
        if (!isSuperAdmin) return;

        setIsLoading(true);

        const nextRegionKeyword = regionKeyword.trim();
        const [regionsResult, buildingsResult] = await Promise.allSettled([
            fetchAdminRegions({ keyword: nextRegionKeyword || undefined, per_page: 100 }),
            fetchAdminBuildings({ keyword: keyword.trim() || undefined, per_page: 100 }),
        ]);

        setRegions(regionsResult.status === "fulfilled" ? getResourceList(regionsResult.value.result) : []);
        setBuildings(buildingsResult.status === "fulfilled" ? getResourceList(buildingsResult.value.result).map(mapBuildingResourceToBuilding) : []);

        setIsLoading(false);
    }, [isSuperAdmin, keyword, regionKeyword]);

    useEffect(() => {
        const timer = window.setTimeout(() => {
            void loadFacilities();
        }, 250);

        return () => window.clearTimeout(timer);
    }, [loadFacilities]);

    const childRegionIdsByRegionId = useMemo(() => {
        return new Map(activeRegions.map((region) => [region.id, getAllChildRegionIds(activeRegions, region.id)]));
    }, [activeRegions]);

    const filteredBuildings = useMemo(() => {
        return buildings.filter((building) => {
            const matchRegion = selectedRegionId ? !!building.region_id && (childRegionIdsByRegionId.get(selectedRegionId) ?? []).includes(building.region_id) : true;
            const matchStatus = selectedStatus === "all" ? true : building.status === selectedStatus;

            return matchRegion && matchStatus;
        });
    }, [buildings, childRegionIdsByRegionId, selectedRegionId, selectedStatus]);

    const filterKey = `${keyword}|${selectedRegionId ?? ""}|${selectedStatus}|${perPage}`;
    const [paginationState, setPaginationState] = useState({ filterKey, page: 1 });
    const currentFilterPage = paginationState.filterKey === filterKey ? paginationState.page : 1;
    const totalPages = Math.max(1, Math.ceil(filteredBuildings.length / perPage));
    const safeCurrentPage = Math.min(currentFilterPage, totalPages);
    const paginatedBuildings = useMemo(() => {
        const startIndex = (safeCurrentPage - 1) * perPage;
        return filteredBuildings.slice(startIndex, startIndex + perPage);
    }, [filteredBuildings, perPage, safeCurrentPage]);
    const paginationStart = filteredBuildings.length === 0 ? 0 : (safeCurrentPage - 1) * perPage + 1;
    const paginationEnd = Math.min(safeCurrentPage * perPage, filteredBuildings.length);
    const visiblePages = useMemo(() => {
        const pages = new Set<number>([1, totalPages, safeCurrentPage - 1, safeCurrentPage, safeCurrentPage + 1]);
        return Array.from(pages)
            .filter((page) => page >= 1 && page <= totalPages)
            .sort((a, b) => a - b);
    }, [safeCurrentPage, totalPages]);
    const activeRegionName = activeRegions.find((region) => region.id === selectedRegionId)?.name;

    const changePage = (page: number) => {
        setPaginationState({ filterKey, page });
    };

    const openViewBuildingModal = async (building: Building) => {
        setViewingBuilding(building);
        setIsViewModalOpen(true);
        setIsDetailLoading(true);
        setDetailErrorMessage(null);

        try {
            const response = await fetchAdminBuildingDetail(building.id);
            setViewingBuilding(mapBuildingResourceToBuilding(response.result));
        } catch (error) {
            const message = error instanceof Error ? error.message : "Không thể tải chi tiết tòa nhà.";
            setDetailErrorMessage(message);
        } finally {
            setIsDetailLoading(false);
        }
    };

    const openEditBuildingPage = useCallback((building: Building) => {
        navigate(`/admin/facilities/buildings/${building.id}/edit`);
    }, [navigate]);

    const closeViewBuildingModal = useCallback(() => {
        setIsViewModalOpen(false);
        setDetailErrorMessage(null);
    }, []);

    const editViewedBuilding = useCallback((building: Building) => {
        openEditBuildingPage(building);
        setIsViewModalOpen(false);
    }, [openEditBuildingPage]);

    const toggleBuildingStatus = async (building: Building) => {
        const nextStatus = building.status === "active" ? 2 : 1;

        if (nextStatus === 2 && !window.confirm(`Bạn có chắc muốn tắt hoạt động tòa nhà ${building.name}?`)) return;

        try {
            setStatusChangingId(building.id);
            await updateAdminBuildingStatus(building.id, nextStatus);
            setSuccessMessage(`Đã ${nextStatus === 2 ? "ngừng hoạt động" : "kích hoạt"} tòa nhà thành công.`);
            await loadFacilities();
        } catch {
            setErrorMessage("Không thể cập nhật trạng thái tòa nhà. Vui lòng thử lại sau.");
        } finally {
            setStatusChangingId(null);
        }
    };

    const deleteBuilding = async (building: Building) => {
        if (!window.confirm(`Bạn có chắc chắn muốn xóa tòa nhà ${building.name}?`)) return;

        try {
            await deleteAdminBuilding(building.id);
            setSuccessMessage("Xóa tòa nhà thành công.");
            await loadFacilities();
        } catch {
            setErrorMessage("Không thể xóa tòa nhà. Vui lòng thử lại sau.");
        }
    };

    const editRegion = (region: AdminRegionResource) => {
        void openEditRegionModal(region);
    };

    const toggleRegionStatus = async (region: AdminRegionResource) => {
        const nextStatus = !region.status;
        const actionLabel = nextStatus ? "mở hoạt động" : "tạm ngưng";

        if (!window.confirm(`Bạn có chắc chắn muốn ${actionLabel} khu vực ${region.name}?`)) return;

        try {
            await updateAdminRegionStatus(region.id, nextStatus);
            setSuccessMessage(`${nextStatus ? "Mở hoạt động" : "Tạm ngưng"} khu vực thành công.`);
            await loadFacilities();
        } catch {
            setErrorMessage("Không thể cập nhật trạng thái khu vực. Vui lòng thử lại sau.");
        }
    };

    const deleteRegion = async (region: AdminRegionResource) => {
        if (!window.confirm(`Bạn có chắc chắn muốn xóa khu vực ${region.name}?`)) return;

        try {
            await deleteAdminRegion(region.id);
            setSelectedRegionId((current) => (current === region.id ? null : current));
            setExpandedIds((current) => current.filter((id) => id !== region.id));
            setSuccessMessage("Xóa khu vực thành công.");
            await loadFacilities();
        } catch (error) {
            setErrorMessage(error instanceof Error ? error.message : "Không thể xóa khu vực. Vui lòng thử lại sau.");
        }
    };

    const clearFilters = () => {
        setSelectedRegionId(null);
        setSelectedStatus("all");
        setKeyword("");
        setRegionKeyword("");
    };

    const renderRegionNode = (region: AdminRegionResource, depth = 0) => {
        const children = regions.filter((item) => item.parent_id === region.id);
        const isExpanded = expandedIds.includes(region.id);
        const isSelected = selectedRegionId === region.id;
        const buildingCount = buildings.filter((building) => building.region_id && (childRegionIdsByRegionId.get(region.id) ?? []).includes(building.region_id)).length;

        return (
            <div key={region.id} className="space-y-1">
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => {
                            if (children.length > 0) {
                                setExpandedIds((prev) => (prev.includes(region.id) ? prev.filter((id) => id !== region.id) : [...prev, region.id]));
                            }
                        }}
                        disabled={children.length === 0}
                        aria-label={`${isExpanded ? "Thu gọn" : "Mở rộng"} khu vực ${region.name}`}
                        aria-expanded={children.length > 0 ? isExpanded : undefined}
                        className={cn(
                            "inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1]/70 text-[#8b5e34] transition focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20",
                            children.length > 0 ? "hover:border-[#f3c56b]/45 hover:bg-[#f3c56b]/15 hover:text-[#24170d]" : "cursor-default opacity-40",
                        )}
                    >
                        <ChevronRight className={cn("h-3.5 w-3.5 transition-transform", isExpanded && children.length > 0 && "rotate-90")} />
                    </button>

                    <div className="group/region relative min-w-0 flex-1">
                        <button
                            type="button"
                            onClick={() => setSelectedRegionId(isSelected ? null : region.id)}
                            className={cn(
                                "flex w-full min-w-0 items-center justify-between gap-2 rounded-2xl border px-3 py-2.5 text-left text-sm transition-all duration-200 focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20 group-hover/region:pr-28 group-focus-within/region:pr-28",
                                isSelected ? "border-[#f3c56b]/35 bg-[#24170d] text-[#fff4df] shadow-lg shadow-[#24170d]/12" : "border-[#3d2a18]/10 bg-[#fffaf1]/70 text-[#6f6254] hover:border-[#f3c56b]/45 hover:bg-[#f3c56b]/15 hover:text-[#24170d]",
                            )}
                            style={{ paddingLeft: 12 + depth * 16 }}
                            aria-pressed={isSelected}
                        >
                            <span className="flex min-w-0 flex-1 items-center gap-2 pr-1">
                                <MapPin className={cn("h-4 w-4 shrink-0", isSelected ? "text-[#f3c56b]" : "text-[#a65f16]")} />
                                <span className={cn("min-w-0 flex-1 whitespace-nowrap font-black tracking-tight group-hover/region:truncate group-focus-within/region:truncate", !region.status && "opacity-55")}>{region.name}</span>
                            </span>
                            <span className={cn("shrink-0 rounded-full px-2 py-0.5 text-xs font-black transition-opacity duration-150 group-hover/region:opacity-0 group-focus-within/region:opacity-0", isSelected ? "bg-white/15 text-[#fff4df]" : "bg-[#efe2cf]/80 text-[#8b5e34]")}>{buildingCount}</span>
                        </button>

                        {isSuperAdmin && (
                            <div className="pointer-events-none absolute right-2 top-1/2 z-10 flex -translate-y-1/2 items-center gap-1 opacity-0 transition-opacity duration-150 group-hover/region:pointer-events-auto group-hover/region:opacity-100 group-focus-within/region:pointer-events-auto group-focus-within/region:opacity-100">
                                <button
                                    type="button"
                                    aria-label={`Sửa khu vực ${region.name}`}
                                    title="Sửa khu vực"
                                    onClick={() => editRegion(region)}
                                    className={cn("inline-flex h-9 w-9 items-center justify-center rounded-xl transition focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20", isSelected ? "text-[#f3c56b] hover:bg-[#f3c56b]/20" : "text-[#8b5e34] hover:bg-[#f3c56b]/20 hover:text-[#24170d]")}
                                >
                                    <Pencil className="h-3.5 w-3.5" />
                                </button>
                                <button
                                    type="button"
                                    aria-label={`${region.status ? "Tạm ngưng" : "Mở hoạt động"} khu vực ${region.name}`}
                                    title={region.status ? "Tạm ngưng khu vực" : "Mở hoạt động khu vực"}
                                    onClick={() => void toggleRegionStatus(region)}
                                    className={cn("inline-flex h-9 w-9 items-center justify-center rounded-xl transition focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20", region.status ? "text-emerald-700 hover:bg-emerald-50" : "text-rose-600 hover:bg-rose-50")}
                                >
                                    <Power className="h-3.5 w-3.5" />
                                </button>
                                <button
                                    type="button"
                                    aria-label={`Xóa khu vực ${region.name}`}
                                    title="Xóa khu vực"
                                    onClick={() => void deleteRegion(region)}
                                    className="inline-flex h-9 w-9 items-center justify-center rounded-xl text-rose-600 transition hover:bg-rose-50 focus:outline-none focus:ring-4 focus:ring-rose-100"
                                >
                                    <Trash2 className="h-3.5 w-3.5" />
                                </button>
                            </div>
                        )}
                    </div>
                </div>
                {isExpanded && children.length > 0 && <div className="space-y-1 border-l border-dashed border-[#f3c56b]/55 pl-2">{children.map((child) => renderRegionNode(child, depth + 1))}</div>}
            </div>
        );
    };

    if (!session?.admin) {
        return <Navigate to="/admin/login" replace />;
    }

    if (!isSuperAdmin) {
        return <Navigate to="/admin/dashboard" replace />;
    }

    return (
        <>
            <>
                <section className="space-y-5 sm:space-y-6 text-[#24170d]">
                    <div className="overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] shadow-2xl shadow-[#6b3f1d]/18">
                        <div className="relative p-5 text-[#fff4df] sm:p-6 lg:p-7">
                            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_18%,rgba(243,197,107,0.24),transparent_30%),radial-gradient(circle_at_82%_16%,rgba(15,118,110,0.22),transparent_32%),linear-gradient(135deg,#24170d_0%,#3d2a18_54%,#0f3f3b_100%)]" />
                            <div className="relative flex min-w-0 flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                                <div className="min-w-0">
                                    <span className="block text-xs font-black uppercase tracking-[0.18em] text-[#f3c56b]/80">QUẢN LÝ LƯU TRÚ</span>
                                    <h1 className="mt-3 text-3xl font-black tracking-[-0.05em] text-[#fff4df] sm:text-4xl lg:text-[2.65rem] flex items-center gap-3">
                                        <Building2 className="h-8 w-8 text-[#f3c56b] shrink-0" />
                                        Khu vực và tòa nhà
                                    </h1>
                                </div>
                                <div className="flex w-full flex-col gap-3 items-end sm:flex-row sm:justify-end lg:w-auto">
                                    {isSuperAdmin && (
                                        <button type="button" onClick={openCreateRegionModal} className="inline-flex h-10 w-fit self-end lg:self-auto items-center justify-center gap-2 whitespace-nowrap rounded-xl border border-[#f8e8c8]/15 bg-[#f8e8c8]/10 px-4 text-sm font-black text-[#fff4df] shadow-xl shadow-black/20 transition-all hover:bg-[#f8e8c8]/15 focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/20 active:scale-[0.98] lg:min-w-40 cursor-pointer">
                                            <Plus className="h-4 w-4 shrink-0 text-[#f3c56b] stroke-[2.8]" />
                                            <span>Thêm khu vực</span>
                                        </button>
                                    )}
                                    <Link to="/admin/facilities/buildings/create" className="inline-flex h-10 w-fit self-end lg:self-auto items-center justify-center gap-2 whitespace-nowrap rounded-xl bg-[#f3c56b] px-4 text-sm font-black text-[#24170d] shadow-xl shadow-[#a65f16]/20 transition-all hover:bg-[#ffd56f] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/35 active:scale-[0.98] lg:min-w-40">
                                        <Building2 className="h-4 w-4 shrink-0 stroke-[2.8]" />
                                        <span>Thêm tòa nhà</span>
                                    </Link>
                                </div>
                            </div>

                            <div className="relative mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 2xl:grid-cols-4">
                                <MetricCard label="Tổng tòa nhà" value={buildings.length} tone="neutral" />
                                <MetricCard label="Đang hoạt động" value={countByStatus(buildings, "active")} tone="emerald" />
                                <MetricCard label="Ngừng hoạt động" value={countByStatus(buildings, "inactive")} tone="amber" />
                                <MetricCard label="Khu vực" value={activeRegions.length} tone="stone" />
                            </div>
                        </div>
                    </div>

                    <div
                        className={cn(
                            "rounded-3xl border px-4 text-sm font-black shadow-sm transition-all duration-500 ease-in-out transform overflow-hidden",
                            successMessage || errorMessage
                                ? "opacity-100 max-h-20 py-3 translate-y-0 scale-100"
                                : "opacity-0 max-h-0 py-0 -translate-y-2 scale-95 pointer-events-none border-transparent",
                            errorMessage || activeType === "error"
                                ? "border-rose-200 bg-rose-50 text-rose-700"
                                : "border-emerald-200 bg-emerald-50 text-emerald-700"
                        )}
                    >
                        {activeMessage || errorMessage || successMessage}
                    </div>

                    <div className="grid min-w-0 grid-cols-1 gap-4 lg:gap-6 2xl:grid-cols-[330px_minmax(0,1fr)]">
                        <aside className="min-w-0 space-y-4">
                            <Panel title="Khu vực" subtitle="" icon={<MapPin className="h-5 w-5" />}>
                                <div className="relative mb-3">
                                    <Search className="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#a65f16]" />
                                    <input
                                        type="text"
                                        value={regionKeyword}
                                        onChange={(event) => setRegionKeyword(event.target.value)}
                                        placeholder="Tìm mã, tên, đường dẫn khu vực..."
                                        className="h-11 w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] pl-10 pr-3 text-sm font-bold text-[#3d2a18] shadow-sm outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20"
                                    />
                                </div>
                                <div className="max-h-[540px] space-y-1 overflow-y-auto pr-1">{rootRegions.map((region) => renderRegionNode(region))}</div>
                            </Panel>
                        </aside>

                        <section className="min-w-0 overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/88 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
                            <div className="border-b border-[#3d2a18]/10 bg-[#fff7e8]/72 p-4 sm:p-5">
                                <div className="flex min-w-0 flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                    <div className="flex min-w-0 flex-wrap items-center gap-2 rounded-2xl bg-[#efe2cf]/55 p-1.5">
                                        {([
                                            ["all", "Tất cả"],
                                            ["active", "Hoạt động"],
                                            ["inactive", "Ngừng hoạt động"],
                                        ] as [BuildingStatusFilter, string][]).map(([value, label]) => (
                                            <button
                                                key={value}
                                                type="button"
                                                onClick={() => setSelectedStatus(value)}
                                                className={cn(
                                                    "rounded-xl px-3.5 py-2 text-sm font-black transition-all",
                                                    selectedStatus === value ? "bg-[#24170d] text-[#fff4df] shadow-lg shadow-[#24170d]/12" : "text-[#6f6254] hover:bg-[#fffaf1] hover:text-[#24170d]",
                                                )}
                                            >
                                                {label} <span className="ml-1 opacity-70">({countByStatus(buildings, value)})</span>
                                            </button>
                                        ))}
                                    </div>

                                    <div className="relative w-full lg:w-80 2xl:w-[380px]">
                                        <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#a65f16]" />
                                        <input
                                            type="text"
                                            value={keyword}
                                            onChange={(event) => setKeyword(event.target.value)}
                                            placeholder="Tìm tên, địa chỉ, quản lý..."
                                            className="h-12 w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] pl-11 pr-4 text-sm font-bold text-[#3d2a18] shadow-sm outline-none transition placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20"
                                        />
                                    </div>
                                </div>

                                {(selectedRegionId || selectedStatus !== "all" || keyword || regionKeyword) && (
                                    <div className="mt-4 flex flex-wrap items-center gap-2">
                                        <span className="text-[11px] font-black uppercase tracking-[0.2em] text-[#8b5e34]/65">Bộ lọc</span>
                                        {activeRegionName && <FilterPill label={`Khu vực: ${activeRegionName}`} onClear={() => setSelectedRegionId(null)} />}
                                        {regionKeyword && <FilterPill label={`Tìm khu vực: ${regionKeyword}`} onClear={() => setRegionKeyword("")} />}
                                        {selectedStatus !== "all" && <FilterPill label={`Trạng thái: ${statusLabels[selectedStatus]}`} onClear={() => setSelectedStatus("all")} />}
                                        {keyword && <FilterPill label={`Từ khóa: ${keyword}`} onClear={() => setKeyword("")} />}
                                        <button type="button" onClick={clearFilters} className="text-xs font-black text-[#8b5e34]/65 underline underline-offset-4 transition hover:text-[#24170d]">Xóa tất cả</button>
                                    </div>
                                )}
                            </div>

                            <div className="overflow-x-auto">
                                <table className="min-w-[980px] w-full text-left">
                                    <thead className="bg-[#24170d] text-[10px] font-black uppercase tracking-[0.18em] text-[#f8e8c8]">
                                        <tr>
                                            <th className="px-4 py-4">Tòa nhà</th>
                                            <th className="px-4 py-4">Khu vực</th>
                                            <th className="px-4 py-4">Quản lý</th>
                                            <th className="px-4 py-4 text-center">Phòng</th>
                                            <th className="px-4 py-4 text-center">Trạng thái</th>
                                            <th className="px-4 py-4 w-[162px]"><div className="flex justify-end"><div className="w-[162px] text-center">Thao tác</div></div></th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-[#3d2a18]/8">
                                        {isLoading && Array.from({ length: 6 }).map((_, index) => (
                                            <tr key={index}>
                                                <td colSpan={8} className="px-5 py-4"><div className="h-12 animate-pulse rounded-2xl bg-stone-100" /></td>
                                            </tr>
                                        ))}

                                        {!isLoading && paginatedBuildings.map((building) => (
                                            <tr key={building.id} className="group group/building transition hover:bg-[#f3c56b]/12">
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-2.5">
                                                        <div
                                                            className="flex h-10 w-10 overflow-hidden rounded-xl border border-[#f3c56b]/35 bg-[#fffaf1] text-[#a65f16] shadow-sm transition group-hover:scale-105 cursor-pointer"
                                                            onClick={() => setViewingImageSrc(building.primary_image?.image_url || stayHubImage)}
                                                        >
                                                            <img src={building.primary_image?.image_url || stayHubImage} alt={building.name} onError={(event) => { event.currentTarget.src = stayHubImage }} className="h-full w-full object-cover" />
                                                        </div>
                                                        <div className="min-w-0">
                                                            <p className="whitespace-normal wrap-break-word text-[13px] font-black leading-snug tracking-tight text-[#24170d]">{building.name}</p>
                                                            <p className="mt-1 whitespace-normal wrap-break-word text-[10px] font-black uppercase leading-snug tracking-[0.12em] text-[#8b5e34]/60">{building.address || "Chưa cập nhật địa chỉ"}</p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 text-[13px] font-bold text-[#6f6254]">{building.region_name || "Chưa cập nhật"}</td>
                                                <td className="px-4 py-3 text-[13px] font-bold text-[#6f6254]">{building.manager_name || "Chưa phân công"}</td>
                                                <td className="px-4 py-3 text-center text-[13px] font-black text-[#24170d] tabular-nums">{building.rooms_count ?? 0}</td>
                                                <td className="px-4 py-3 text-center"><span className={cn("inline-flex items-center justify-center whitespace-nowrap rounded-full border px-2.5 py-0.5 text-[11px] font-black shadow-sm", statusClassNames[building.status])}>{statusLabels[building.status]}</span></td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <button type="button" onClick={() => void openViewBuildingModal(building)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#0f766e]/25 hover:bg-[#0f766e]/10 hover:text-[#0f5f59] focus:outline-none focus:ring-4 focus:ring-[#0f766e]/10 active:scale-95" title="Xem chi tiết" aria-label={`Xem chi tiết tòa nhà ${building.name}`}><Eye className="h-5 w-5" /></button>
                                                        <button type="button" onClick={() => openEditBuildingPage(building)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-[#3d2a18]/25 hover:bg-[#f3c56b]/15 hover:text-[#24170d] focus:outline-none focus:ring-4 focus:ring-[#3d2a18]/10 active:scale-95" title="Chỉnh sửa" aria-label={`Chỉnh sửa tòa nhà ${building.name}`}><Pencil className="h-4.5 w-4.5" /></button>
                                                        <button type="button" disabled={statusChangingId === building.id} onClick={() => void toggleBuildingStatus(building)} className={cn("inline-flex h-10 w-10 items-center justify-center rounded-xl border shadow-sm transition focus:outline-none focus:ring-4 active:scale-95 disabled:cursor-not-allowed disabled:opacity-55", building.status === "active" ? "border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 focus:ring-emerald-100" : "border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100 focus:ring-rose-100")} title={building.status === "active" ? "Ngừng hoạt động" : "Kích hoạt"} aria-label={`${building.status === "active" ? "Ngừng hoạt động" : "Kích hoạt"} tòa nhà ${building.name}`}><Power className="h-5 w-5" /></button>
                                                        <button type="button" onClick={() => void deleteBuilding(building)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600 focus:outline-none focus:ring-4 focus:ring-rose-100 active:scale-95" title="Xóa" aria-label={`Xóa tòa nhà ${building.name}`}><Trash2 className="h-5 w-5" /></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}

                                        {!isLoading && filteredBuildings.length === 0 && (
                                            <tr>
                                                <td colSpan={8} className="px-5 py-20 text-center">
                                                    <div className="mx-auto flex max-w-sm flex-col items-center">
                                                        <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-[1.75rem] border border-dashed border-[#f3c56b] bg-[#f3c56b]/15 text-[#a65f16]"><Building2 className="h-9 w-9" /></div>
                                                        <p className="text-lg font-black tracking-tight text-[#24170d]">Không tìm thấy tòa nhà</p>
                                                        <p className="mt-2 text-sm font-semibold leading-6 text-[#6f6254]">Hãy thử đổi từ khóa hoặc xóa bớt bộ lọc hiện tại.</p>
                                                        <button type="button" onClick={clearFilters} className="mt-5 rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] px-4 py-2 text-sm font-black text-[#3d2a18] transition hover:border-[#f3c56b]/45 hover:bg-[#f3c56b]/15">Xóa bộ lọc</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            {!isLoading && filteredBuildings.length > 0 && (
                                <div className="flex flex-col gap-4 border-t border-[#3d2a18]/8 bg-[#fff7e8]/72 px-5 py-4 md:flex-row md:items-center md:justify-between">
                                    <div className="text-sm font-bold text-[#6f6254]">
                                        Hiển thị <span className="font-black text-[#24170d]">{paginationStart}</span> - <span className="font-black text-[#24170d]">{paginationEnd}</span> / <span className="font-black text-[#24170d]">{filteredBuildings.length}</span> tòa nhà
                                    </div>

                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                                        <label className="flex items-center gap-2 text-sm font-black text-[#6f6254]">
                                            Mỗi trang
                                            <AdminSelect value={perPage} options={perPageOptions} className="w-36" menuPlacement="top" onChange={(nextValue) => setPerPage(Number(nextValue))} />
                                        </label>

                                        <div className="flex items-center gap-1">
                                            <button type="button" onClick={() => changePage(Math.max(1, safeCurrentPage - 1))} disabled={safeCurrentPage <= 1} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 text-[#8b5e34] transition hover:border-[#f3c56b] hover:text-[#a65f16] disabled:cursor-not-allowed disabled:opacity-40">
                                                <ChevronLeft className="h-4 w-4" />
                                            </button>

                                            {visiblePages.map((page, index) => {
                                                const previousPage = visiblePages[index - 1];
                                                const showDots = previousPage && page - previousPage > 1;

                                                return (
                                                    <React.Fragment key={page}>
                                                        {showDots && <span className="px-2 text-sm font-black text-[#8b5e34]/45">...</span>}
                                                        <button type="button" onClick={() => changePage(page)} className={cn("inline-flex h-9 min-w-9 items-center justify-center rounded-xl px-3 text-sm font-black transition", safeCurrentPage === page ? "bg-[#24170d] text-[#fff4df] shadow-sm" : "border border-[#3d2a18]/10 text-[#8b5e34] hover:border-[#f3c56b] hover:text-[#a65f16]")}>{page}</button>
                                                    </React.Fragment>
                                                );
                                            })}

                                            <button type="button" onClick={() => changePage(Math.min(totalPages, safeCurrentPage + 1))} disabled={safeCurrentPage >= totalPages} className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#3d2a18]/10 text-[#8b5e34] transition hover:border-[#f3c56b] hover:text-[#a65f16] disabled:cursor-not-allowed disabled:opacity-40">
                                                <ChevronRight className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </section>
                    </div>

                    <BuildingDetailModal
                        isOpen={isViewModalOpen}
                        onClose={closeViewBuildingModal}
                        building={viewingBuilding}
                        isLoading={isDetailLoading}
                        errorMessage={detailErrorMessage}
                        onEdit={editViewedBuilding}
                    />

                    <ImageViewerModal
                        isOpen={!!viewingImageSrc}
                        src={viewingImageSrc}
                        onClose={() => setViewingImageSrc(null)}
                    />

                    <RegionModal
                        isOpen={isRegionModalOpen}
                        onClose={handleCloseRegionModal}
                        regions={regions}
                        editingRegionId={editingRegionId}
                        form={regionForm}
                        setForm={setRegionForm}
                        onCancel={handleCancelRegionModal}
                        onSubmitSuccess={handleRegionSubmitSuccess}
                        isFormLoading={isRegionFormLoading}
                    />
                </section>
            </>
        </>
    );
}

function MetricCard({ label, value, tone }: { label: string; value: number; tone: "neutral" | "emerald" | "amber" | "stone" }) {
    const toneClassNames = {
        neutral: "border-[#f8e8c8]/12 bg-[#f8e8c8]/10 text-[#fff4df]",
        emerald: "border-[#0f766e]/35 bg-[#0f766e]/16 text-[#c8fff4]",
        amber: "border-[#f3c56b]/35 bg-[#f3c56b]/18 text-[#fff4df]",
        stone: "border-[#f8e8c8]/16 bg-[#f8e8c8]/10 text-[#f8e8c8]",
    }[tone];

    return (
        <div className={cn("rounded-2xl border px-3 py-2.5 backdrop-blur", toneClassNames)}>
            <p className="text-[10px] font-black uppercase tracking-[0.18em] opacity-60">{label}</p>
            <p className="mt-0.5 text-2xl font-black tracking-tight tabular-nums">{value}</p>
        </div>
    );
}

function Panel({ title, subtitle, icon, children }: { title: string; subtitle: string; icon: React.ReactNode; children: React.ReactNode }) {
    return (
        <div className="rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/88 p-4 shadow-xl shadow-[#6b3f1d]/8 backdrop-blur-md">
            <div className="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h2 className="text-base font-black tracking-tight text-[#24170d]">{title}</h2>
                    <p className="text-xs font-bold text-[#8b5e34]/60">{subtitle}</p>
                </div>
                <div className="flex h-11 w-11 items-center justify-center rounded-2xl border border-[#f3c56b]/40 bg-[#f3c56b]/15 text-[#a65f16]">{icon}</div>
            </div>
            {children}
        </div>
    );
}

function FilterPill({ label, onClear }: { label: string; onClear: () => void }) {
    return (
        <span className="inline-flex items-center gap-2 rounded-full border border-[#f3c56b]/45 bg-[#f3c56b]/15 px-3 py-1.5 text-xs font-black text-[#8a4f18] shadow-sm shadow-[#a65f16]/5">
            {label}
            <button type="button" onClick={onClear} className="rounded-full p-0.5 transition hover:bg-[#f3c56b]/25 focus:outline-none focus:ring-2 focus:ring-[#f3c56b]/35" aria-label={`Xóa bộ lọc ${label}`}>
                <X className="h-3 w-3" />
            </button>
        </span>
    );
}
