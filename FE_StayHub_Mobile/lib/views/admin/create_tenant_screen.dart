import 'dart:io';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:image_picker/image_picker.dart';
import '../../controllers/auth_controller.dart';
import '../../controllers/tenant_controller.dart';
import '../../controllers/facility_controller.dart';
import '../../models/tenant.dart';
import '../auth/login_screen.dart'; // import GridPainter

class CreateTenantScreen extends StatefulWidget {
  final Tenant? tenant;
  const CreateTenantScreen({super.key, this.tenant});

  @override
  State<CreateTenantScreen> createState() => _CreateTenantScreenState();
}

class _CreateTenantScreenState extends State<CreateTenantScreen> {
  final _formKey = GlobalKey<FormState>();
  final ImagePicker _picker = ImagePicker();

  // Controller states
  late final TextEditingController _usernameController;
  late final TextEditingController _fullNameController;
  late final TextEditingController _phoneController;
  late final TextEditingController _emailController;
  late final TextEditingController _dobController;
  late final TextEditingController _identityNumController;
  late final TextEditingController _permanentAddressController;
  late final TextEditingController _currentAddressController;

  int? _selectedBuildingId;
  int _gender = 1; // 1 = Nam, 2 = Nữ
  int _status = 1; // 1 = Đang thuê, 2 = Ngừng thuê
  int _identityType = 1; // 1 = CCCD, 3 = Hộ chiếu

  XFile? _frontImageFile;
  XFile? _backImageFile;
  bool _deleteFrontImage = false;
  bool _deleteBackImage = false;

  bool _isSaving = false;

  @override
  void initState() {
    super.initState();
    final t = widget.tenant;

    _usernameController = TextEditingController(text: t?.username ?? '');
    _fullNameController = TextEditingController(text: t?.fullName ?? '');
    _phoneController = TextEditingController(text: t?.phone ?? '');
    _emailController = TextEditingController(text: t?.email ?? '');
    _dobController = TextEditingController(text: t?.dateOfBirth ?? '');
    _identityNumController = TextEditingController(text: t?.identityNumber ?? '');
    _permanentAddressController = TextEditingController(text: t?.permanentAddress ?? '');
    _currentAddressController = TextEditingController(text: t?.currentAddress ?? '');

    _gender = t?.gender ?? 1;
    _status = t?.status ?? 1;
    _identityType = t?.identityType ?? 1;
    _selectedBuildingId = t?.buildingId;

    WidgetsBinding.instance.addPostFrameCallback((_) {
      final auth = context.read<AuthController>();
      final facility = context.read<FacilityController>();

      // Fetch buildings if SuperAdmin
      if (auth.currentAdmin?.role == 2) {
        facility.fetchBuildings().then((_) {
          if (mounted && _selectedBuildingId == null && facility.buildings.isNotEmpty) {
            setState(() {
              _selectedBuildingId = facility.buildings.first.id;
            });
          }
        });
      } else {
        // Manager defaults to their first managed building
        if (_selectedBuildingId == null && auth.currentAdmin?.managedBuildingIds.isNotEmpty == true) {
          setState(() {
            _selectedBuildingId = auth.currentAdmin!.managedBuildingIds.first;
          });
        }
      }
    });
  }

  @override
  void dispose() {
    _usernameController.dispose();
    _fullNameController.dispose();
    _phoneController.dispose();
    _emailController.dispose();
    _dobController.dispose();
    _identityNumController.dispose();
    _permanentAddressController.dispose();
    _currentAddressController.dispose();
    super.dispose();
  }

  bool get _isEditMode => widget.tenant != null;

  Future<void> _selectDate() async {
    DateTime initial = DateTime.now().subtract(const Duration(days: 365 * 20));
    if (_dobController.text.isNotEmpty) {
      try {
        initial = DateTime.parse(_dobController.text);
      } catch (_) {}
    }
    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(1920),
      lastDate: DateTime.now(),
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.light(
              primary: Color(0xFF1C1917),
              onPrimary: Colors.white,
              onSurface: Color(0xFF1C1917),
            ),
          ),
          child: child!,
        );
      },
    );
    if (picked != null) {
      setState(() {
        _dobController.text =
            "${picked.year}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}";
      });
    }
  }

  Future<void> _pickImage(bool isFront) async {
    showModalBottomSheet(
      context: context,
      backgroundColor: const Color(0xFF1C1917),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.only(
          topLeft: Radius.circular(20),
          topRight: Radius.circular(20),
        ),
      ),
      builder: (context) {
        return SafeArea(
          child: Wrap(
            children: [
              ListTile(
                leading: const Icon(Icons.camera_alt_outlined, color: Color(0xFFEAB308)),
                title: const Text('Chụp ảnh mới', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                onTap: () async {
                  Navigator.pop(context);
                  final file = await _picker.pickImage(source: ImageSource.camera, imageQuality: 85);
                  if (file != null) {
                    setState(() {
                      if (isFront) {
                        _frontImageFile = file;
                        _deleteFrontImage = false;
                      } else {
                        _backImageFile = file;
                        _deleteBackImage = false;
                      }
                    });
                  }
                },
              ),
              ListTile(
                leading: const Icon(Icons.photo_library_outlined, color: Color(0xFFEAB308)),
                title: const Text('Chọn từ thư viện', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                onTap: () async {
                  Navigator.pop(context);
                  final file = await _picker.pickImage(source: ImageSource.gallery, imageQuality: 85);
                  if (file != null) {
                    setState(() {
                      if (isFront) {
                        _frontImageFile = file;
                        _deleteFrontImage = false;
                      } else {
                        _backImageFile = file;
                        _deleteBackImage = false;
                      }
                    });
                  }
                },
              ),
            ],
          ),
        );
      },
    );
  }

  void _removeImage(bool isFront) {
    setState(() {
      if (isFront) {
        _frontImageFile = null;
        if (_isEditMode) _deleteFrontImage = true;
      } else {
        _backImageFile = null;
        if (_isEditMode) _deleteBackImage = true;
      }
    });
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    if (_selectedBuildingId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Vui lòng chọn tòa nhà quản lý.')),
      );
      return;
    }

    setState(() {
      _isSaving = true;
    });

    final data = {
      'building_id': _selectedBuildingId,
      if (!_isEditMode) 'username': _usernameController.text.trim(),
      'full_name': _fullNameController.text.trim(),
      'email': _emailController.text.trim(),
      'phone': _phoneController.text.trim(),
      'date_of_birth': _dobController.text,
      'gender': _gender,
      'status': _status,
      'identity_type': _identityType,
      'identity_number': _identityNumController.text.trim(),
      'permanent_address': _permanentAddressController.text.trim(),
      'current_address': _currentAddressController.text.trim(),
    };

    final controller = context.read<TenantController>();
    bool success;

    if (_isEditMode) {
      success = await controller.updateTenant(
        widget.tenant!.id,
        data: data,
        frontImage: _frontImageFile,
        backImage: _backImageFile,
        deleteFront: _deleteFrontImage,
        deleteBack: _deleteBackImage,
      );
    } else {
      success = await controller.createTenant(
        data: data,
        frontImage: _frontImageFile,
        backImage: _backImageFile,
      );
    }

    setState(() {
      _isSaving = false;
    });

    if (mounted) {
      if (success) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(_isEditMode
                ? 'Cập nhật thông tin khách thuê thành công!'
                : 'Thêm mới khách thuê thành công! Mật khẩu đã gửi qua email.'),
            backgroundColor: Colors.green,
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
        );
        Navigator.pop(context, true);
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(controller.errorMessage ?? 'Thao tác thất bại. Vui lòng thử lại.'),
            backgroundColor: Colors.redAccent,
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
        );
      }
    }
  }

  InputDecoration _inputDecoration(String label, String hint) {
    return InputDecoration(
      labelText: label,
      hintText: hint,
      labelStyle: const TextStyle(color: Color(0xFF78716C), fontWeight: FontWeight.bold, fontSize: 13),
      hintStyle: TextStyle(color: Colors.grey, fontSize: 13),
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: Color(0xFFE4E2D7), width: 1.5),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: Color(0xFF1C1917), width: 1.5),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: Colors.redAccent, width: 1.5),
      ),
      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: Colors.redAccent, width: 2.0),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthController>();
    final isSuperAdmin = auth.currentAdmin?.role == 2;
    final facility = context.watch<FacilityController>();

    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        title: Text(
          _isEditMode ? 'Chỉnh sửa Khách thuê' : 'Thêm mới Khách thuê',
          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
        ),
        backgroundColor: const Color(0xFF1C1917),
        foregroundColor: Colors.white,
      ),
      body: Stack(
        children: [
          Positioned.fill(child: CustomPaint(painter: GridPainter())),
          Form(
            key: _formKey,
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(20.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // Title Header Card
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      color: const Color(0xFF1C1917),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Row(
                      children: [
                        const Icon(Icons.person_add_alt_1_rounded, color: Color(0xFFEAB308), size: 36),
                        const SizedBox(width: 16),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'Hồ sơ cư dân',
                                style: TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w900,
                                  fontSize: 16,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                _isEditMode
                                    ? 'Cập nhật lý lịch và tài khoản khách thuê.'
                                    : 'Khai báo thông tin tài khoản và định danh khách thuê.',
                                style: TextStyle(
                                  color: Colors.white.withOpacity(0.7),
                                  fontSize: 11,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),

                  // Building select (only shown for SuperAdmin)
                  if (isSuperAdmin) ...[
                    DropdownButtonFormField<int>(
                      value: _selectedBuildingId,
                      decoration: _inputDecoration('Tòa nhà quản lý *', 'Chọn tòa nhà'),
                      items: facility.buildings.map((b) {
                        return DropdownMenuItem<int>(
                          value: b.id,
                          child: Text(b.name),
                        );
                      }).toList(),
                      onChanged: (val) {
                        setState(() {
                          _selectedBuildingId = val;
                        });
                      },
                      validator: (val) => val == null ? 'Vui lòng chọn tòa nhà' : null,
                    ),
                    const SizedBox(height: 16),
                  ],

                  // Account Fields Card
                  _buildFormSection(
                    title: 'THÔNG TIN TÀI KHOẢN',
                    children: [
                      TextFormField(
                        controller: _usernameController,
                        enabled: !_isEditMode,
                        decoration: _inputDecoration('Tên đăng nhập *', 'Ví dụ: tenant_nguyenan'),
                        style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                        validator: (val) => val == null || val.trim().isEmpty ? 'Tên đăng nhập không được trống' : null,
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: _fullNameController,
                        decoration: _inputDecoration('Họ và tên *', 'Nhập đầy đủ họ tên'),
                        style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                        validator: (val) => val == null || val.trim().isEmpty ? 'Họ tên là bắt buộc' : null,
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: _phoneController,
                        keyboardType: TextInputType.phone,
                        decoration: _inputDecoration('Số điện thoại *', 'Ví dụ: 0912345678'),
                        style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                        validator: (val) {
                          if (val == null || val.trim().isEmpty) return 'Số điện thoại là bắt buộc';
                          if (!RegExp(r'^0[35789]\d{8}$').hasMatch(val.trim())) {
                            return 'Số điện thoại không đúng định dạng VN';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: _emailController,
                        keyboardType: TextInputType.emailAddress,
                        decoration: _inputDecoration('Email liên hệ *', 'Ví dụ: tenant.an@gmail.com'),
                        style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                        validator: (val) {
                          if (val == null || val.trim().isEmpty) return 'Email là bắt buộc';
                          if (!RegExp(r'^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$').hasMatch(val.trim())) {
                            return 'Email không đúng định dạng';
                          }
                          return null;
                        },
                      ),
                    ],
                  ),
                  const SizedBox(height: 20),

                  // Personal Fields Card
                  _buildFormSection(
                    title: 'LÝ LỊCH CÁ NHÂN',
                    children: [
                      TextFormField(
                        controller: _dobController,
                        readOnly: true,
                        onTap: _selectDate,
                        decoration: _inputDecoration('Ngày sinh *', 'Chọn ngày sinh').copyWith(
                          suffixIcon: const Icon(Icons.calendar_today_rounded, color: Color(0xFF1C1917)),
                        ),
                        style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                        validator: (val) => val == null || val.trim().isEmpty ? 'Vui lòng chọn ngày sinh' : null,
                      ),
                      const SizedBox(height: 16),
                      const Text(
                        'Giới tính *',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF78716C)),
                      ),
                      const SizedBox(height: 8),
                      Row(
                        children: [
                          Expanded(
                            child: ChoiceChip(
                              label: const Center(
                                child: Text('Nam', style: TextStyle(fontWeight: FontWeight.bold)),
                              ),
                              selected: _gender == 1,
                              selectedColor: const Color(0xFFEAB308),
                              backgroundColor: Colors.white,
                              labelStyle: TextStyle(color: _gender == 1 ? const Color(0xFF1C1917) : Colors.grey),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                              onSelected: (val) {
                                if (val) setState(() => _gender = 1);
                              },
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: ChoiceChip(
                              label: const Center(
                                child: Text('Nữ', style: TextStyle(fontWeight: FontWeight.bold)),
                              ),
                              selected: _gender == 2,
                              selectedColor: const Color(0xFFEAB308),
                              backgroundColor: Colors.white,
                              labelStyle: TextStyle(color: _gender == 2 ? const Color(0xFF1C1917) : Colors.grey),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                              onSelected: (val) {
                                if (val) setState(() => _gender = 2);
                              },
                            ),
                          ),
                        ],
                      ),
                      if (_isEditMode) ...[
                        const SizedBox(height: 16),
                        DropdownButtonFormField<int>(
                          value: _status,
                          decoration: _inputDecoration('Trạng thái *', ''),
                          items: const [
                            DropdownMenuItem(value: 1, child: Text('Đang thuê')),
                            DropdownMenuItem(value: 2, child: Text('Ngừng thuê')),
                          ],
                          onChanged: (val) {
                            if (val != null) setState(() => _status = val);
                          },
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: 20),

                  // Identity Fields Card
                  _buildFormSection(
                    title: 'GIẤY TỜ ĐỊNH DANH',
                    children: [
                      DropdownButtonFormField<int>(
                        value: _identityType,
                        decoration: _inputDecoration('Loại giấy tờ *', ''),
                        items: const [
                          DropdownMenuItem(value: 1, child: Text('CCCD')),
                          DropdownMenuItem(value: 3, child: Text('Hộ chiếu')),
                        ],
                        onChanged: (val) {
                          if (val != null) setState(() => _identityType = val);
                        },
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: _identityNumController,
                        decoration: _inputDecoration('Số giấy tờ *', 'Nhập số CCCD hoặc Hộ chiếu'),
                        style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                        validator: (val) => val == null || val.trim().isEmpty ? 'Số định danh là bắt buộc' : null,
                      ),
                      const SizedBox(height: 20),

                      // Image Pickers Front & Back
                      const Text(
                        'Ảnh chụp giấy tờ',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF78716C)),
                      ),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: _buildImagePickerBox(
                              label: 'Mặt trước',
                              file: _frontImageFile,
                              existingUrl: widget.tenant?.frontImageUrl,
                              isDeleted: _deleteFrontImage,
                              onTap: () => _pickImage(true),
                              onRemove: () => _removeImage(true),
                            ),
                          ),
                          const SizedBox(width: 16),
                          Expanded(
                            child: _buildImagePickerBox(
                              label: 'Mặt sau',
                              file: _backImageFile,
                              existingUrl: widget.tenant?.backImageUrl,
                              isDeleted: _deleteBackImage,
                              onTap: () => _pickImage(false),
                              onRemove: () => _removeImage(false),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                  const SizedBox(height: 20),

                  // Address Fields Card
                  _buildFormSection(
                    title: 'ĐỊA CHỈ THƯỜNG TRÚ & HIỆN TẠI',
                    children: [
                      TextFormField(
                        controller: _permanentAddressController,
                        maxLines: 2,
                        decoration: _inputDecoration('Nơi thường trú', 'Quê quán trên sổ hộ khẩu'),
                        style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: _currentAddressController,
                        maxLines: 2,
                        decoration: _inputDecoration('Địa chỉ hiện tại', 'Địa chỉ sinh sống hiện nay'),
                        style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                      ),
                    ],
                  ),
                  const SizedBox(height: 32),

                  // Submit Button
                  ElevatedButton(
                    onPressed: _isSaving ? null : _submit,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF1C1917),
                      foregroundColor: const Color(0xFFEAB308),
                      disabledBackgroundColor: Colors.grey.shade400,
                      padding: const EdgeInsets.symmetric(vertical: 18),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      elevation: 4,
                    ),
                    child: _isSaving
                        ? const SizedBox(
                            width: 24,
                            height: 24,
                            child: CircularProgressIndicator(color: Color(0xFFEAB308), strokeWidth: 3),
                          )
                        : Text(
                            _isEditMode ? 'CẬP NHẬT THÔNG TIN' : 'TẠO MỚI KHÁCH THUÊ',
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, letterSpacing: 0.5),
                          ),
                  ),
                  const SizedBox(height: 40),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFormSection({required String title, required List<Widget> children}) {
    return Card(
      color: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
        side: const BorderSide(color: Color(0xFFE4E2D7)),
      ),
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(20.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              title,
              style: const TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.bold,
                color: Color(0xFF78716C),
                letterSpacing: 1.0,
              ),
            ),
            const Divider(height: 24, color: Color(0xFFE4E2D7)),
            ...children,
          ],
        ),
      ),
    );
  }

  Widget _buildImagePickerBox({
    required String label,
    required XFile? file,
    required String? existingUrl,
    required bool isDeleted,
    required VoidCallback onTap,
    required VoidCallback onRemove,
  }) {
    final showExistingImage = existingUrl != null && existingUrl.isNotEmpty && !isDeleted && file == null;

    return GestureDetector(
      onTap: onTap,
      child: Container(
        height: 120,
        decoration: BoxDecoration(
          color: const Color(0xFFF7F6F0),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: const Color(0xFFE4E2D7), width: 1.5),
        ),
        clipBehavior: Clip.antiAlias,
        child: Stack(
          children: [
            Center(
              child: file != null
                  ? Image.file(File(file.path), fit: BoxFit.cover, width: double.infinity, height: double.infinity)
                  : showExistingImage
                      ? Image.network(existingUrl, fit: BoxFit.cover, width: double.infinity, height: double.infinity)
                      : Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const Icon(Icons.add_a_photo_outlined, color: Color(0xFF78716C), size: 28),
                            const SizedBox(height: 8),
                            Text(
                              label,
                              style: const TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.bold,
                                color: Color(0xFF78716C),
                              ),
                            ),
                          ],
                        ),
            ),
            if (file != null || showExistingImage)
              Positioned(
                top: 4,
                right: 4,
                child: GestureDetector(
                  onTap: onRemove,
                  child: Container(
                    padding: const EdgeInsets.all(4),
                    decoration: const BoxDecoration(
                      color: Colors.redAccent,
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(Icons.close, color: Colors.white, size: 16),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
