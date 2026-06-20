import 'dart:typed_data';
import 'dart:ui' as ui;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../../controllers/auth_controller.dart';
import '../../controllers/contract_controller.dart';
import '../../models/contract.dart';
import '../auth/login_screen.dart'; // import GridPainter if available

class SignContractScreen extends StatefulWidget {
  final Contract contract;

  const SignContractScreen({super.key, required this.contract});

  @override
  State<SignContractScreen> createState() => _SignContractScreenState();
}

class _SignContractScreenState extends State<SignContractScreen> {
  final _formKey = GlobalKey<FormState>();

  late TextEditingController _fullNameController;
  late TextEditingController _identityNumberController;
  late TextEditingController _identityDateController;
  late TextEditingController _identityPlaceController;
  late TextEditingController _permanentAddressController;

  DateTime? _selectedIdentityDate;
  final List<Offset?> _points = [];
  bool _isSigning = false;
  bool _agreeTerms = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    final authController = context.read<AuthController>();
    final tenant = authController.currentTenant;

    // Pre-fill from current tenant profile
    _fullNameController = TextEditingController(text: tenant?.fullName ?? widget.contract.tenantName);
    _identityNumberController = TextEditingController(text: tenant?.identityNumber ?? '');
    _identityPlaceController = TextEditingController(text: tenant?.identityPlace ?? '');
    _permanentAddressController = TextEditingController(text: tenant?.permanentAddress ?? '');

    String dateStr = '';
    if (tenant?.identityDate != null && tenant!.identityDate!.isNotEmpty) {
      try {
        _selectedIdentityDate = DateTime.parse(tenant.identityDate!);
        dateStr = _formatDate(_selectedIdentityDate!);
      } catch (_) {}
    }
    _identityDateController = TextEditingController(text: dateStr);
  }

  @override
  void dispose() {
    _fullNameController.dispose();
    _identityNumberController.dispose();
    _identityDateController.dispose();
    _identityPlaceController.dispose();
    _permanentAddressController.dispose();
    super.dispose();
  }

  String _formatDate(DateTime date) {
    final day = date.day.toString().padLeft(2, '0');
    final month = date.month.toString().padLeft(2, '0');
    final year = date.year.toString();
    return '$day/$month/$year';
  }

  String _formatDateDb(DateTime date) {
    final day = date.day.toString().padLeft(2, '0');
    final month = date.month.toString().padLeft(2, '0');
    final year = date.year.toString();
    return '$year-$month-$day';
  }

  Future<void> _selectIdentityDate(BuildContext context) async {
    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: _selectedIdentityDate ?? DateTime.now().subtract(const Duration(days: 365 * 18)),
      firstDate: DateTime(1900),
      lastDate: DateTime.now(),
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.light(
              primary: Color(0xFF1C1917),
              onPrimary: Colors.white,
              onSurface: Color(0xFF1C1917),
            ),
            textButtonTheme: TextButtonThemeData(
              style: TextButton.styleFrom(foregroundColor: const Color(0xFF1C1917)),
            ),
          ),
          child: child!,
        );
      },
    );
    if (picked != null && picked != _selectedIdentityDate) {
      setState(() {
        _selectedIdentityDate = picked;
        _identityDateController.text = _formatDate(picked);
      });
    }
  }

  // Draw points into raw PNG bytes (compatible with all platforms including Web)
  Future<Uint8List?> _exportSignatureBytes(double width, double height) async {
    if (_points.isEmpty) return null;

    final recorder = ui.PictureRecorder();
    final canvas = Canvas(recorder, Rect.fromPoints(const Offset(0, 0), Offset(width, height)));

    // Draw background (white background for clean contrast in documents)
    final paintBg = Paint()..color = Colors.white;
    canvas.drawRect(Rect.fromLTWH(0, 0, width, height), paintBg);

    // Draw drawing lines
    final paintLine = Paint()
      ..color = const ui.Color(0xFF1C1917)
      ..strokeCap = StrokeCap.round
      ..strokeWidth = 4.5
      ..isAntiAlias = true;

    for (int i = 0; i < _points.length - 1; i++) {
      if (_points[i] != null && _points[i + 1] != null) {
        canvas.drawLine(_points[i]!, _points[i + 1]!, paintLine);
      }
    }

    final picture = recorder.endRecording();
    final img = await picture.toImage(width.toInt(), height.toInt());
    final byteData = await img.toByteData(format: ui.ImageByteFormat.png);
    if (byteData == null) return null;

    return byteData.buffer.asUint8List();
  }

  Future<void> _submitSignature(double canvasWidth, double canvasHeight) async {
    setState(() {
      _errorMessage = null;
    });

    if (!_formKey.currentState!.validate()) {
      return;
    }

    if (_points.isEmpty) {
      setState(() {
        _errorMessage = 'Vui lòng vẽ chữ ký tay của bạn.';
      });
      return;
    }

    if (!_agreeTerms) {
      setState(() {
        _errorMessage = 'Bạn phải đồng ý với các điều khoản hợp đồng.';
      });
      return;
    }

    setState(() {
      _isSigning = true;
    });

    try {
      final sigBytes = await _exportSignatureBytes(canvasWidth, canvasHeight);
      if (sigBytes == null) {
        throw Exception('Không thể tạo ảnh chữ ký.');
      }

      final dateDbStr = _selectedIdentityDate != null ? _formatDateDb(_selectedIdentityDate!) : '';

      final contractController = context.read<ContractController>();
      final success = await contractController.signContract(
        contractId: widget.contract.id,
        fullName: _fullNameController.text.trim(),
        identityNumber: _identityNumberController.text.trim(),
        identityType: 1, // CCCD
        identityDate: dateDbStr,
        identityPlace: _identityPlaceController.text.trim(),
        permanentAddress: _permanentAddressController.text.trim(),
        signatureBytes: sigBytes,
      );

      if (success) {
        // Refresh session to get updated identity information in AuthController
        final authController = context.read<AuthController>();
        await authController.checkSession();

        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Ký hợp đồng thành công! Hợp đồng đã có hiệu lực.'),
              backgroundColor: Colors.green,
              behavior: SnackBarBehavior.floating,
            ),
          );
          Navigator.pop(context, true); // return true to refresh
        }
      } else {
        setState(() {
          _errorMessage = contractController.errorMessage ?? 'Ký hợp đồng thất bại.';
        });
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Đã xảy ra lỗi: $e';
      });
    } finally {
      if (mounted) {
        setState(() {
          _isSigning = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        title: const Text('Ký hợp đồng thuê phòng', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: const Color(0xFF1C1917),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: Stack(
        children: [
          Positioned.fill(child: CustomPaint(painter: GridPainter())),
          GestureDetector(
            onTap: () => FocusScope.of(context).unfocus(),
            child: SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (_errorMessage != null) ...[
                      _buildErrorDisplay(),
                      const SizedBox(height: 16),
                    ],
                    _buildStepHeader('1', 'THÔNG TIN PHÁP LÝ (BÊN B)', 'Vui lòng cung cấp chính xác thông tin định danh để đưa vào văn bản hợp đồng.'),
                    const SizedBox(height: 12),
                    _buildFormCard(),
                    const SizedBox(height: 24),
                    _buildStepHeader('2', 'CHỮ KÝ VẼ TAY CỦA BẠN', 'Đặt ngón tay hoặc bút cảm ứng và vẽ chữ ký của bạn vào khung bên dưới.'),
                    const SizedBox(height: 12),
                    _buildSignatureCard(),
                    const SizedBox(height: 24),
                    _buildTermsCheck(),
                    const SizedBox(height: 24),
                    _buildSubmitButton(),
                    const SizedBox(height: 40),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildErrorDisplay() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        color: const Color(0xFFFEF2F2),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFFCA5A5)),
      ),
      child: Row(
        children: [
          const Icon(Icons.error_outline_rounded, color: Color(0xFFDC2626), size: 20),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              _errorMessage!,
              style: const TextStyle(
                color: Color(0xFF991B1B),
                fontSize: 13,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStepHeader(String stepNum, String title, String subtitle) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Container(
              width: 22,
              height: 22,
              decoration: const BoxDecoration(
                color: Color(0xFF1C1917),
                shape: BoxShape.circle,
              ),
              child: Center(
                child: Text(
                  stepNum,
                  style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold),
                ),
              ),
            ),
            const SizedBox(width: 8),
            Text(
              title,
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.bold,
                color: Color(0xFF1C1917),
                letterSpacing: 0.5,
              ),
            ),
          ],
        ),
        const SizedBox(height: 4),
        Padding(
          padding: const EdgeInsets.only(left: 30),
          child: Text(
            subtitle,
            style: const TextStyle(
              fontSize: 12,
              color: Color(0xFF78716C),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildFormCard() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE4E2D7)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.02),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        children: [
          _buildTextField(
            controller: _fullNameController,
            label: 'Họ và tên khách thuê',
            hint: 'Nhập họ và tên đầy đủ',
            icon: Icons.person_outline_rounded,
            validator: (value) {
              if (value == null || value.trim().isEmpty) return 'Vui lòng nhập họ và tên';
              return null;
            },
          ),
          const SizedBox(height: 16),
          _buildTextField(
            controller: _identityNumberController,
            label: 'Số CMND/CCCD/Hộ chiếu',
            hint: 'Nhập số CCCD gồm 12 số',
            icon: Icons.badge_outlined,
            keyboardType: TextInputType.number,
            validator: (value) {
              if (value == null || value.trim().isEmpty) return 'Vui lòng nhập số định danh';
              if (value.trim().length < 9) return 'Số định danh không hợp lệ';
              return null;
            },
          ),
          const SizedBox(height: 16),
          _buildDateField(),
          const SizedBox(height: 16),
          _buildTextField(
            controller: _identityPlaceController,
            label: 'Nơi cấp',
            hint: 'Ví dụ: Cục Cảnh sát QLHC về Trật tự xã hội',
            icon: Icons.location_city_outlined,
            validator: (value) {
              if (value == null || value.trim().isEmpty) return 'Vui lòng nhập nơi cấp';
              return null;
            },
          ),
          const SizedBox(height: 16),
          _buildTextField(
            controller: _permanentAddressController,
            label: 'Địa chỉ thường trú',
            hint: 'Nhập địa chỉ ghi trên CCCD',
            icon: Icons.home_outlined,
            maxLines: 2,
            validator: (value) {
              if (value == null || value.trim().isEmpty) return 'Vui lòng nhập địa chỉ thường trú';
              return null;
            },
          ),
        ],
      ),
    );
  }

  Widget _buildTextField({
    required TextEditingController controller,
    required String label,
    required String hint,
    required IconData icon,
    TextInputType keyboardType = TextInputType.text,
    int maxLines = 1,
    String? Function(String?)? validator,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF44403C)),
        ),
        const SizedBox(height: 6),
        TextFormField(
          controller: controller,
          keyboardType: keyboardType,
          maxLines: maxLines,
          style: const TextStyle(fontSize: 14, color: Color(0xFF1C1917), fontWeight: FontWeight.w500),
          decoration: InputDecoration(
            hintText: hint,
            hintStyle: const TextStyle(color: Colors.grey, fontSize: 13),
            prefixIcon: Icon(icon, color: const Color(0xFF78716C), size: 18),
            contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            filled: true,
            fillColor: const Color(0xFFF7F6F0),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: Color(0xFFE4E2D7)),
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: Color(0xFFE4E2D7)),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: Color(0xFF1C1917), width: 1.5),
            ),
            errorBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: Colors.red, width: 1),
            ),
            focusedErrorBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: Colors.red, width: 1.5),
            ),
          ),
          validator: validator,
        ),
      ],
    );
  }

  Widget _buildDateField() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Ngày cấp',
          style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF44403C)),
        ),
        const SizedBox(height: 6),
        InkWell(
          onTap: () => _selectIdentityDate(context),
          borderRadius: BorderRadius.circular(12),
          child: IgnorePointer(
            child: TextFormField(
              controller: _identityDateController,
              style: const TextStyle(fontSize: 14, color: Color(0xFF1C1917), fontWeight: FontWeight.w500),
              decoration: InputDecoration(
                hintText: 'Chọn ngày cấp',
                hintStyle: const TextStyle(color: Colors.grey, fontSize: 13),
                prefixIcon: const Icon(Icons.calendar_today_outlined, color: const Color(0xFF78716C), size: 18),
                suffixIcon: const Icon(Icons.arrow_drop_down_rounded, color: const Color(0xFF78716C)),
                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                filled: true,
                fillColor: const Color(0xFFF7F6F0),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: Color(0xFFE4E2D7)),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: Color(0xFFE4E2D7)),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: Color(0xFF1C1917), width: 1.5),
                ),
              ),
              validator: (value) {
                if (value == null || value.isEmpty) return 'Vui lòng chọn ngày cấp';
                return null;
              },
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildSignatureCard() {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE4E2D7)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.02),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'Khung vẽ chữ ký',
                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF78716C)),
                ),
                Row(
                  children: [
                    TextButton.icon(
                      onPressed: () {
                        setState(() {
                          if (_points.isNotEmpty) {
                            // Find the last stroke and remove it
                            int index = _points.lastIndexOf(null);
                            if (index == -1) {
                              _points.clear();
                            } else {
                              _points.removeRange(index, _points.length);
                            }
                          }
                        });
                      },
                      icon: const Icon(Icons.undo_rounded, size: 16, color: Color(0xFF78716C)),
                      label: const Text('Hoàn tác', style: TextStyle(fontSize: 12, color: Color(0xFF78716C))),
                      style: TextButton.styleFrom(padding: EdgeInsets.zero, minimumSize: const Size(60, 30)),
                    ),
                    const SizedBox(width: 8),
                    TextButton.icon(
                      onPressed: () {
                        setState(() {
                          _points.clear();
                        });
                      },
                      icon: const Icon(Icons.clear_rounded, size: 16, color: Colors.red),
                      label: const Text('Xóa vẽ', style: TextStyle(fontSize: 12, color: Colors.red)),
                      style: TextButton.styleFrom(padding: EdgeInsets.zero, minimumSize: const Size(60, 30)),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const Divider(height: 1, color: Color(0xFFE4E2D7)),
          LayoutBuilder(
            builder: (context, constraints) {
              final canvasWidth = constraints.maxWidth;
              const canvasHeight = 220.0;
              return Container(
                width: canvasWidth,
                height: canvasHeight,
                color: const Color(0xFFFAF9F6), // Slightly off-white inside drawing pad
                child: GestureDetector(
                  onPanStart: (details) {
                    final RenderBox renderBox = context.findRenderObject() as RenderBox;
                    final localPosition = renderBox.globalToLocal(details.globalPosition);
                    // Adjust offset for custom padding/bar if any, but since GestureDetector is matched 
                    // on the container size:
                    final offsetInContainer = details.localPosition;
                    if (offsetInContainer.dx >= 0 && offsetInContainer.dx <= canvasWidth &&
                        offsetInContainer.dy >= 0 && offsetInContainer.dy <= canvasHeight) {
                      setState(() {
                        _points.add(offsetInContainer);
                      });
                    }
                  },
                  onPanUpdate: (details) {
                    final offsetInContainer = details.localPosition;
                    if (offsetInContainer.dx >= 0 && offsetInContainer.dx <= canvasWidth &&
                        offsetInContainer.dy >= 0 && offsetInContainer.dy <= canvasHeight) {
                      setState(() {
                        _points.add(offsetInContainer);
                      });
                    }
                  },
                  onPanEnd: (details) {
                    setState(() {
                      _points.add(null);
                    });
                  },
                  child: Stack(
                    children: [
                      if (_points.isEmpty)
                        Center(
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: const [
                              Icon(Icons.edit_rounded, color: Colors.grey, size: 32),
                              SizedBox(height: 8),
                              Text(
                                'Ký tên tại đây',
                                style: TextStyle(color: Colors.grey, fontSize: 13, fontWeight: FontWeight.w500),
                              ),
                            ],
                          ),
                        ),
                      Positioned.fill(
                        child: CustomPaint(
                          painter: SignaturePainter(_points),
                        ),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        ],
      ),
    );
  }

  Widget _buildTermsCheck() {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFFFFBEB),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFFDE68A)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Checkbox(
            value: _agreeTerms,
            activeColor: const Color(0xFF1C1917),
            onChanged: (val) {
              setState(() {
                _agreeTerms = val ?? false;
              });
            },
          ),
          const Expanded(
            child: Padding(
              padding: EdgeInsets.only(top: 8.0),
              child: Text(
                'Tôi xác nhận các thông tin định danh trên là đúng sự thật và hoàn toàn đồng ý chịu trách nhiệm pháp lý với các điều khoản nêu trong hợp đồng thuê phòng.',
                style: TextStyle(
                  fontSize: 12.5,
                  height: 1.45,
                  fontWeight: FontWeight.w500,
                  color: Color(0xFF92400E),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSubmitButton() {
    return LayoutBuilder(
      builder: (context, constraints) {
        final canvasWidth = constraints.maxWidth;
        const canvasHeight = 220.0;
        return ElevatedButton.icon(
          onPressed: _isSigning ? null : () => _submitSignature(canvasWidth, canvasHeight),
          icon: _isSigning
              ? const SizedBox(
                  width: 20,
                  height: 20,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                  ),
                )
              : const Icon(Icons.draw_rounded),
          label: Text(
            _isSigning ? 'ĐANG XỬ LÝ CHỮ KÝ...' : 'XÁC NHẬN KÝ HỢP ĐỒNG',
            style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold, letterSpacing: 0.5),
          ),
          style: ElevatedButton.styleFrom(
            backgroundColor: const Color(0xFF1C1917),
            foregroundColor: Colors.white,
            disabledBackgroundColor: Colors.grey,
            padding: const EdgeInsets.symmetric(vertical: 16),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(16),
            ),
            elevation: 2,
          ),
        );
      },
    );
  }
}

class SignaturePainter extends CustomPainter {
  final List<Offset?> points;

  SignaturePainter(this.points);

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = const Color(0xFF1C1917)
      ..strokeCap = StrokeCap.round
      ..strokeWidth = 4.5
      ..isAntiAlias = true;

    for (int i = 0; i < points.length - 1; i++) {
      if (points[i] != null && points[i + 1] != null) {
        canvas.drawLine(points[i]!, points[i + 1]!, paint);
      }
    }
  }

  @override
  bool shouldRepaint(SignaturePainter oldDelegate) => true;
}
