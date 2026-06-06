const fs = require('fs');
let code = fs.readFileSync('FE_StayHub/src/features/admin/facilities/components/create-building-screen.tsx', 'utf8');

code = code.replace(/const createQuickRoomType = async \(\) => {[\s\S]*?};/g, '');
code = code.replace(/const createQuickAssetTemplate = async \(\) => {[\s\S]*?};/g, '');

const configCardRegex1 = /<ConfigCard icon={Boxes} title="Loại phòng".*?<\/ConfigCard>/gs;
code = code.replace(configCardRegex1, '');

const configCardRegex2 = /<ConfigCard icon={Boxes} title="Mẫu tài sản".*?<\/ConfigCard>/gs;
code = code.replace(configCardRegex2, '');

const rowShellRoomTypeRegex = /function mergeRoomTypeOptions[\s\S]*?}/g;
code = code.replace(rowShellRoomTypeRegex, '');
const rowShellAssetRegex = /function mergeAssetTemplateOptions[\s\S]*?}/g;
code = code.replace(rowShellAssetRegex, '');

fs.writeFileSync('FE_StayHub/src/features/admin/facilities/components/create-building-screen.tsx', code);
