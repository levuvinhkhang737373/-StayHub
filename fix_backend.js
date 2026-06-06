const fs = require('fs');

function processRequest(file) {
    if (!fs.existsSync(file)) return;
    let code = fs.readFileSync(file, 'utf8');
    
    // Remove room_types rules
    code = code.replace(/            'room_type_ids' => \['nullable', 'array', 'max:100'\],\n/, '');
    code = code.replace(/            'room_type_ids\.\*' => \['required', 'integer', 'distinct', Rule::exists\('room_types', 'id'\)\],\n/, '');
    code = code.replace(/            'room_types' => \['nullable', 'array', 'max:100'\],\n/, '');
    code = code.replace(/            'room_types\.\*\.id' => \['nullable', 'integer', 'distinct', Rule::exists\('room_types', 'id'\)->where\('building_id', \$buildingId\)\],\n/, '');
    code = code.replace(/            'room_types\.\*\.id' => \['nullable', 'integer', 'distinct', Rule::exists\('room_types', 'id'\)\],\n/, '');
    code = code.replace(/            'room_types\.\*\.name' => \['required_with:room_types', 'string', 'max:150'\],\n/, '');
    code = code.replace(/            'room_types\.\*\.description' => \['nullable', 'string'\],\n/, '');
    code = code.replace(/            'room_types\.\*\.status' => \['nullable', 'integer', Rule::in\(array_keys\(RoomType::STATUS_LABELS\)\)\],\n/, '');
    code = code.replace(/            'delete_room_type_ids' => \['nullable', 'array', 'max:100'\],\n/, '');
    code = code.replace(/            'delete_room_type_ids\.\*' => \['required', 'integer', 'distinct', Rule::exists\('room_types', 'id'\)->where\('building_id', \$buildingId\)\],\n/, '');

    // Remove asset_templates rules
    code = code.replace(/            'asset_template_ids' => \['nullable', 'array', 'max:100'\],\n/, '');
    code = code.replace(/            'asset_template_ids\.\*' => \['required', 'integer', 'distinct', Rule::exists\('asset_templates', 'id'\)\],\n/, '');
    code = code.replace(/            'asset_templates' => \['nullable', 'array', 'max:100'\],\n/, '');
    code = code.replace(/            'asset_templates\.\*\.id' => \['nullable', 'integer', 'distinct', Rule::exists\('asset_templates', 'id'\)->where\('building_id', \$buildingId\)\],\n/, '');
    code = code.replace(/            'asset_templates\.\*\.id' => \['nullable', 'integer', 'distinct', Rule::exists\('asset_templates', 'id'\)\],\n/, '');
    code = code.replace(/            'asset_templates\.\*\.name' => \['required_with:asset_templates', 'string', 'max:150'\],\n/, '');
    code = code.replace(/            'asset_templates\.\*\.default_unit_name' => \['nullable', 'integer', Rule::in\(array_keys\(AssetTemplate::UNIT_LABELS\)\)\],\n/, '');
    code = code.replace(/            'asset_templates\.\*\.description' => \['nullable', 'string'\],\n/, '');
    code = code.replace(/            'asset_templates\.\*\.status' => \['nullable', 'integer', Rule::in\(array_keys\(AssetTemplate::STATUS_LABELS\)\)\],\n/, '');
    code = code.replace(/            'delete_asset_template_ids' => \['nullable', 'array', 'max:100'\],\n/, '');
    code = code.replace(/            'delete_asset_template_ids\.\*' => \['required', 'integer', 'distinct', Rule::exists\('asset_templates', 'id'\)->where\('building_id', \$buildingId\)\],\n/, '');

    // Remove duplicates logic for room_types
    code = code.replace(/            foreach \(\$this->duplicateIndexes\('room_types', 'name'\) as \$index\) {\n                \$validator->errors\(\)->add\("room_types\.{\$index}\.name", 'Tên loại phòng không được trùng trong cùng tòa nhà\.'\);\n            }\n\n/, '');

    // Remove validation messages for room_types
    code = code.replace(/            'room_type_ids.*[\s\S]*?'delete_room_type_ids.*exists' => 'Loại phòng cần xóa không tồn tại trong tòa nhà này.',\n/g, '');
    code = code.replace(/            'asset_template_ids.*[\s\S]*?'delete_asset_template_ids.*exists' => 'Mẫu tài sản cần xóa không tồn tại trong tòa nhà này.',\n/g, '');
    
    // Fallback if regex for messages didn't work perfectly due to multiline
    const lines = code.split('\n');
    const filteredLines = lines.filter(line => !line.includes("'room_type_ids.") && !line.includes("'room_types") && !line.includes("'delete_room_type_ids.") && !line.includes("'asset_template_ids.") && !line.includes("'asset_templates.") && !line.includes("'delete_asset_template_ids."));
    code = filteredLines.join('\n');

    fs.writeFileSync(file, code);
}

processRequest('BE_StayHub/app/Http/Requests/Admin/Building/UpdateRequest.php');
processRequest('BE_StayHub/app/Http/Requests/Admin/Building/StoreRequest.php');

