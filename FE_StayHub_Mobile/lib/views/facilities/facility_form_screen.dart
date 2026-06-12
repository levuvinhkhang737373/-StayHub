import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../controllers/facility_controller.dart';
import '../../models/region.dart';
import '../../models/building.dart';
import '../auth/login_screen.dart'; // import GridPainter

class FacilityFormScreen extends StatefulWidget {
  final bool isBuildingForm;
  final Region? region;
  final Building? building;

  const FacilityFormScreen({
    super.key,
    required this.isBuildingForm,
    this.region,
    this.building,
  });

  @override
  State<FacilityFormScreen> createState() => _FacilityFormScreenState();
}

class _FacilityFormScreenState extends State<FacilityFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _descriptionController = TextEditingController();
  final _addressController = TextEditingController();
  
  int _status = 1; // 1: Active, 2: Inactive
  int? _selectedRegionId;

  bool get isEditMode => widget.isBuildingForm ? widget.building != null : widget.region != null;

  @override
  void initState() {
    super.initState();
    if (widget.isBuildingForm) {
      if (widget.building != null) {
        _nameController.text = widget.building!.name;
        _descriptionController.text = widget.building!.description ?? '';
        _addressController.text = widget.building!.address ?? '';
        _status = widget.building!.status;
        _selectedRegionId = widget.building!.regionId;
      }
    } else {
      if (widget.region != null) {
        _nameController.text = widget.region!.name;
        _descriptionController.text = widget.region!.description ?? '';
        _status = widget.region!.status;
      }
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    _descriptionController.dispose();
    _addressController.dispose();
    super.dispose();
  }

  Future<void> _handleSave() async {
    if (!_formKey.currentState!.validate()) return;

    final controller = context.read<FacilityController>();
    bool success = false;

    if (widget.isBuildingForm) {
      if (isEditMode) {
        success = await controller.updateBuilding(
          id: widget.building!.id,
          name: _nameController.text.trim(),
          regionId: _selectedRegionId!,
          managerAdminId: widget.building?.managerAdminId,
          address: _addressController.text.trim(),
          description: _descriptionController.text.trim(),
          status: _status,
        );
      } else {
        success = await controller.createBuilding(
          name: _nameController.text.trim(),
          regionId: _selectedRegionId!,
          managerAdminId: null,
          address: _addressController.text.trim(),
          description: _descriptionController.text.trim(),
          status: _status,
        );
      }
    } else {
      if (isEditMode) {
        success = await controller.updateRegion(
          id: widget.region!.id,
          name: _nameController.text.trim(),
          description: _descriptionController.text.trim(),
          status: _status,
        );
      } else {
        success = await controller.createRegion(
          name: _nameController.text.trim(),
          description: _descriptionController.text.trim(),
          status: _status,
        );
      }
    }

    if (!mounted) return;

    if (success) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(isEditMode ? 'Cập nhật thành công!' : 'Thêm mới thành công!'),
          backgroundColor: Colors.green,
        ),
      );
      Navigator.pop(context);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(controller.errorMessage ?? 'Thao tác thất bại'),
          backgroundColor: Colors.redAccent,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final facilityController = context.watch<FacilityController>();
    final regions = facilityController.regions;

    if (widget.isBuildingForm && _selectedRegionId == null && regions.isNotEmpty) {
      _selectedRegionId = regions.first.id;
    }

    final titleText = isEditMode
        ? (widget.isBuildingForm ? 'Sửa Tòa Nhà' : 'Sửa Khu Vực')
        : (widget.isBuildingForm ? 'Thêm Tòa Nhà' : 'Thêm Khu Vực');

    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        title: Text(titleText, style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white, fontSize: 18)),
        backgroundColor: const Color(0xFF1C1917),
      ),
      body: Stack(
        children: [
          Positioned.fill(child: CustomPaint(painter: GridPainter())),
          SingleChildScrollView(
            padding: const EdgeInsets.all(24.0),
            child: Container(
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(24),
                border: Border.all(color: const Color(0xFFE4E2D7)),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.02),
                    blurRadius: 10,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Name Field
                    TextFormField(
                      controller: _nameController,
                      style: const TextStyle(color: Color(0xFF1C1917)),
                      decoration: InputDecoration(
                        labelText: widget.isBuildingForm ? 'Tên Tòa Nhà' : 'Tên Khu Vực',
                        prefixIcon: Icon(widget.isBuildingForm ? Icons.business : Icons.map, color: const Color(0xFF1C1917)),
                        filled: true,
                        fillColor: const Color(0xFFF9F8F6),
                        enabledBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: const BorderSide(color: Color(0xFFE4E2D7)),
                        ),
                        focusedBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: const BorderSide(color: Color(0xFF1C1917), width: 1.5),
                        ),
                      ),
                      validator: (val) => val == null || val.trim().isEmpty ? 'Vui lòng nhập tên' : null,
                    ),
                    const SizedBox(height: 16),

                    // Region Dropdown (Only for Buildings)
                    if (widget.isBuildingForm) ...[
                      DropdownButtonFormField<int>(
                        initialValue: _selectedRegionId,
                        decoration: InputDecoration(
                          labelText: 'Khu vực trực thuộc',
                          prefixIcon: const Icon(Icons.map, color: Color(0xFF1C1917)),
                          filled: true,
                          fillColor: const Color(0xFFF9F8F6),
                          enabledBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(color: Color(0xFFE4E2D7)),
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(color: Color(0xFF1C1917), width: 1.5),
                          ),
                        ),
                        items: regions.map((region) {
                          return DropdownMenuItem<int>(
                            value: region.id,
                            child: Text(region.name),
                          );
                        }).toList(),
                        onChanged: (val) {
                          setState(() {
                            _selectedRegionId = val;
                          });
                        },
                        validator: (val) => val == null ? 'Vui lòng chọn khu vực' : null,
                      ),
                      const SizedBox(height: 16),

                      // Address Field
                      TextFormField(
                        controller: _addressController,
                        style: const TextStyle(color: Color(0xFF1C1917)),
                        decoration: InputDecoration(
                          labelText: 'Địa chỉ',
                          prefixIcon: const Icon(Icons.location_on, color: Color(0xFF1C1917)),
                          filled: true,
                          fillColor: const Color(0xFFF9F8F6),
                          enabledBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(color: Color(0xFFE4E2D7)),
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(color: Color(0xFF1C1917), width: 1.5),
                          ),
                        ),
                        validator: (val) => val == null || val.trim().isEmpty ? 'Vui lòng nhập địa chỉ' : null,
                      ),
                      const SizedBox(height: 16),
                    ],

                    // Description Field
                    TextFormField(
                      controller: _descriptionController,
                      maxLines: 3,
                      style: const TextStyle(color: Color(0xFF1C1917)),
                      decoration: InputDecoration(
                        labelText: 'Mô tả',
                        prefixIcon: const Icon(Icons.description, color: Color(0xFF1C1917)),
                        filled: true,
                        fillColor: const Color(0xFFF9F8F6),
                        enabledBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: const BorderSide(color: Color(0xFFE4E2D7)),
                        ),
                        focusedBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: const BorderSide(color: Color(0xFF1C1917), width: 1.5),
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),

                    // Status Dropdown
                    DropdownButtonFormField<int>(
                      initialValue: _status,
                      decoration: InputDecoration(
                        labelText: 'Trạng thái hoạt động',
                        prefixIcon: const Icon(Icons.info, color: Color(0xFF1C1917)),
                        filled: true,
                        fillColor: const Color(0xFFF9F8F6),
                        enabledBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: const BorderSide(color: Color(0xFFE4E2D7)),
                        ),
                        focusedBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: const BorderSide(color: Color(0xFF1C1917), width: 1.5),
                        ),
                      ),
                      items: const [
                        DropdownMenuItem<int>(value: 1, child: Text('Hoạt động')),
                        DropdownMenuItem<int>(value: 2, child: Text('Ngừng hoạt động')),
                      ],
                      onChanged: (val) {
                        if (val != null) {
                          setState(() {
                            _status = val;
                          });
                        }
                      },
                    ),
                    const SizedBox(height: 32),

                    // Save Button
                    ElevatedButton(
                      onPressed: facilityController.isLoading ? null : _handleSave,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF1C1917),
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      child: facilityController.isLoading
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                            )
                          : const Text('LƯU LẠI', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold)),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
