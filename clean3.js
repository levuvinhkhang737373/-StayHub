const fs = require('fs');
let code = fs.readFileSync('FE_StayHub/src/features/admin/facilities/components/create-building-screen.tsx', 'utf8');

// Imports
code = code.replace(/import { createAdminAssetTemplate, fetchAdminAssetTemplates } from "\.\.\/\.\.\/asset-templates\/services\/asset-templates\.service";\n/, '');
code = code.replace(/import type { AdminAssetTemplateResource } from "\.\.\/\.\.\/asset-templates\/types\/asset-template-api\.model";\n/, '');
code = code.replace(/import { createAdminRoomType, fetchAdminRoomTypes } from "\.\.\/\.\.\/room-types\/services\/room-types\.service";\n/, '');
code = code.replace(/import type { AdminRoomTypeResource } from "\.\.\/\.\.\/room-types\/types\/room-type-api\.model";\n/, '');
code = code.replace(/    type BuildingAssetTemplateFormRow,\n/g, '');
code = code.replace(/    type BuildingRoomTypeFormRow,\n/g, '');

// Types
code = code.replace(/type ConfigKey = "room_types" \| "asset_templates" \| "service_prices" \| "settings";/, 'type ConfigKey = "service_prices" | "settings";');

// State declarations
code = code.replace(/    const \[roomTypeCatalog, setRoomTypeCatalog\] = useState<AdminRoomTypeResource\[\]>\(\[\]\);\n/, '');
code = code.replace(/    const \[assetTemplateCatalog, setAssetTemplateCatalog\] = useState<AdminAssetTemplateResource\[\]>\(\[\]\);\n/, '');
code = code.replace(/    const \[isCreatingRoomType, setIsCreatingRoomType\] = useState\(false\);\n/, '');
code = code.replace(/    const \[isCreatingAssetTemplate, setIsCreatingAssetTemplate\] = useState\(false\);\n/, '');
code = code.replace(/    const \[deleteRoomTypeIds, setDeleteRoomTypeIds\] = useState<number\[\]>\(\[\]\);\n/, '');
code = code.replace(/    const \[deleteAssetTemplateIds, setDeleteAssetTemplateIds\] = useState<number\[\]>\(\[\]\);\n/, '');

// State init
code = code.replace(/    const \[openCreateForms, setOpenCreateForms\] = useState<Record<ConfigKey, boolean>>\({ room_types: false, asset_templates: false, service_prices: false, settings: false }\);/, '    const [openCreateForms, setOpenCreateForms] = useState<Record<ConfigKey, boolean>>({ service_prices: false, settings: false });');
code = code.replace(/    const \[openConfigCards, setOpenConfigCards\] = useState<Record<ConfigKey, boolean>>\({ room_types: true, asset_templates: false, service_prices: false, settings: false }\);/, '    const [openConfigCards, setOpenConfigCards] = useState<Record<ConfigKey, boolean>>({ service_prices: true, settings: false });');

code = code.replace(/    const \[quickRoomType, setQuickRoomType\] = useState\({ name: "", description: "", status: 1 }\);\n/, '');
code = code.replace(/    const \[quickAssetTemplate, setQuickAssetTemplate\] = useState\({ name: "", default_unit_name: 1, description: "", status: 1 }\);\n/, '');

// Promise.all calls
code = code.replace(/            fetchAdminRoomTypes\({ per_page: 100, status: 1, only_global: true }\),\n/, '');
code = code.replace(/            fetchAdminAssetTemplates\({ per_page: 100, status: 1, only_global: true }\),\n/, '');

// then arguments
code = code.replace(/            \.then\(\(\[regionsResponse, managersResponse, servicesResponse, roomTypesResponse, assetTemplatesResponse, settingsResponse, buildingResponse\]\) => {/, '            .then(([regionsResponse, managersResponse, servicesResponse, settingsResponse, buildingResponse]) => {');

// then sets
code = code.replace(/                setRoomTypeCatalog\(getResourceList\(roomTypesResponse\.result\)\);\n/, '');
code = code.replace(/                setAssetTemplateCatalog\(getResourceList\(assetTemplatesResponse\.result\)\);\n/, '');

// addRow
code = code.replace(/        const nextRow = row \|\| \(key === "room_types" \? { name: "", description: "", status: 1 }\n            : key === "asset_templates" \? { name: "", default_unit_name: 1, description: "", status: 1 }\n                : key === "service_prices" \? { service_id: "", price: "0", effective_from: getTodayIsoDate\(\), effective_to: "", status: 1 }\n                    : { setting_label: "", setting_name: "", setting_value: "", description: "", is_public: true }\);/, '        const nextRow = row || (key === "service_prices" ? { service_id: "", price: "0", effective_from: getTodayIsoDate(), effective_to: "", status: 1 }\n            : { setting_label: "", setting_name: "", setting_value: "", description: "", is_public: true });');
code = code.replace(/    const addRow = \(key: ConfigKey, row\?: BuildingRoomTypeFormRow \| BuildingAssetTemplateFormRow \| BuildingServicePriceFormRow \| BuildingSettingFormRow\) => {/, '    const addRow = (key: ConfigKey, row?: BuildingServicePriceFormRow | BuildingSettingFormRow) => {');

// removeRow
code = code.replace(/        if \(key === "room_types" && row\.rooms_count && row\.rooms_count > 0\) return;\n/, '');
code = code.replace(/        if \(key === "asset_templates" && row\.room_assets_count && row\.room_assets_count > 0\) return;\n/, '');
code = code.replace(/            if \(key === "room_types"\) setDeleteRoomTypeIds\(\(current\) => \(current\.includes\(row\.id!\) \? current : \[\.\.\.current, row\.id!\]\)\);\n/, '');
code = code.replace(/            if \(key === "asset_templates"\) setDeleteAssetTemplateIds\(\(current\) => \(current\.includes\(row\.id!\) \? current : \[\.\.\.current, row\.id!\]\)\);\n/, '');

// function declarations logic
const logicToRemoveRegex = /    const roomTypeOptions = useMemo\(\(\) => mergeRoomTypeOptions[\s\S]*?    };\n/g;
code = code.replace(logicToRemoveRegex, '');

// Payload build logic
code = code.replace(/                deleteRoomTypeIds,\n/, '');
code = code.replace(/                deleteAssetTemplateIds,\n/, '');

// Merge options functions at bottom
code = code.replace(/function mergeRoomTypeOptions[\s\S]*?}\n\nfunction mergeAssetTemplateOptions[\s\S]*?}\n/, '');

// JSX tags
const roomTypeCardRegex = /                    <ConfigCard icon={Boxes} title="Loại phòng"[\s\S]*?<\/ConfigCard>\n\n/g;
code = code.replace(roomTypeCardRegex, '');
const assetTemplateCardRegex = /                    <ConfigCard icon={Boxes} title="Mẫu tài sản"[\s\S]*?<\/ConfigCard>\n\n/g;
code = code.replace(assetTemplateCardRegex, '');


fs.writeFileSync('FE_StayHub/src/features/admin/facilities/components/create-building-screen.tsx', code);
