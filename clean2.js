const fs = require('fs');
let code = fs.readFileSync('FE_StayHub/src/features/admin/facilities/components/building-detail-modal.tsx', 'utf8');

code = code.replace(/<InfoStat label="Loại phòng" value={building\?\.room_types_count \|\| 0} \/>/g, '');
code = code.replace(/<InfoStat label="Mẫu tài sản" value={building\?\.asset_templates_count \|\| 0} \/>/g, '');
code = code.replace(/<PreviewGroup label="Loại phòng" items={building\?\.room_types\?\.map\(\(item\) => item\.name\) \|\| \[\]} \/>/g, '');
code = code.replace(/<PreviewGroup label="Mẫu tài sản" items={building\?\.asset_templates\?\.map\(\(item\) => item\.name\) \|\| \[\]} \/>/g, '');

fs.writeFileSync('FE_StayHub/src/features/admin/facilities/components/building-detail-modal.tsx', code);
