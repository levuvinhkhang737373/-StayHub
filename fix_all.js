const fs = require('fs');

// 1. Fix facility-api.model.ts
let modelCode = fs.readFileSync('FE_StayHub/src/features/admin/facilities/types/facility-api.model.ts', 'utf8');
modelCode = modelCode.replace(/room_types\?:.*?;/g, '');
modelCode = modelCode.replace(/asset_templates\?:.*?;/g, '');
modelCode = modelCode.replace(/room_types_count\?:.*?;/g, '');
modelCode = modelCode.replace(/asset_templates_count\?:.*?;/g, '');
modelCode = modelCode.replace(/room_type_ids\?:.*?;/g, '');
modelCode = modelCode.replace(/asset_template_ids\?:.*?;/g, '');
modelCode = modelCode.replace(/import type { AdminRoomTypeResource } from "\.\.\/\.\.\/room-types\/types\/room-type-api.model";/g, '');
modelCode = modelCode.replace(/import type { AdminAssetTemplateResource } from "\.\.\/\.\.\/asset-templates\/types\/asset-template-api.model";/g, '');
fs.writeFileSync('FE_StayHub/src/features/admin/facilities/types/facility-api.model.ts', modelCode);

// 2. Fix building.validation.ts
let validCode = fs.readFileSync('FE_StayHub/src/features/admin/facilities/validations/building.validation.ts', 'utf8');
validCode = validCode.replace(/export interface BuildingRoomTypeFormRow {[\s\S]*?}/g, '');
validCode = validCode.replace(/export interface BuildingAssetTemplateFormRow {[\s\S]*?}/g, '');
validCode = validCode.replace(/room_types: BuildingRoomTypeFormRow\[\];/g, '');
validCode = validCode.replace(/asset_templates: BuildingAssetTemplateFormRow\[\];/g, '');
validCode = validCode.replace(/room_types\?: string;/g, '');
validCode = validCode.replace(/asset_templates\?: string;/g, '');

const validateRegex = /    const roomTypeNames = new Set<string>\(\);\n    form\.room_types\.forEach\(\(item, index\) => {[\s\S]*?    }\);\n\n    const assetTemplateNames = new Set<string>\(\);\n    form\.asset_templates\.forEach\(\(item, index\) => {[\s\S]*?    }\);\n/g;
validCode = validCode.replace(validateRegex, '');
fs.writeFileSync('FE_StayHub/src/features/admin/facilities/validations/building.validation.ts', validCode);

// 3. Fix building-form.utils.ts
let utilsCode = fs.readFileSync('FE_StayHub/src/features/admin/facilities/utils/building-form.utils.ts', 'utf8');
utilsCode = utilsCode.replace(/        room_types: \[\],\n/g, '');
utilsCode = utilsCode.replace(/        asset_templates: \[\],\n/g, '');
utilsCode = utilsCode.replace(/        room_types: detail\.room_types \|\| \[\],\n/g, '');
utilsCode = utilsCode.replace(/        asset_templates: detail\.asset_templates \|\| \[\],\n/g, '');

const payloadRegex = /    deleteRoomTypeIds: number\[\];\n    deleteAssetTemplateIds: number\[\];\n/g;
utilsCode = utilsCode.replace(payloadRegex, '');

const mapPayloadRegex = /        room_types: form\.room_types\?\.map\(\(item\) => \({[\s\S]*?        }\)\) \|\| \[\],\n        asset_templates: form\.asset_templates\?\.map\(\(item\) => \({[\s\S]*?        }\)\) \|\| \[\],\n        delete_room_type_ids: deleteRoomTypeIds,\n        delete_asset_template_ids: deleteAssetTemplateIds,\n/g;
utilsCode = utilsCode.replace(mapPayloadRegex, '');
fs.writeFileSync('FE_StayHub/src/features/admin/facilities/utils/building-form.utils.ts', utilsCode);

// 4. Fix data-utils.ts
let dataUtilsCode = fs.readFileSync('FE_StayHub/src/features/admin/facilities/lib/data-utils.ts', 'utf8');
dataUtilsCode = dataUtilsCode.replace(/    room_types: detail\.room_types \|\| \[\],\n/g, '');
dataUtilsCode = dataUtilsCode.replace(/    asset_templates: detail\.asset_templates \|\| \[\],\n/g, '');
dataUtilsCode = dataUtilsCode.replace(/    room_types_count: building\.room_types_count,\n/g, '');
dataUtilsCode = dataUtilsCode.replace(/    asset_templates_count: building\.asset_templates_count,\n/g, '');
fs.writeFileSync('FE_StayHub/src/features/admin/facilities/lib/data-utils.ts', dataUtilsCode);

