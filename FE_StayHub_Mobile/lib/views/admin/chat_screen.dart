import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../controllers/tenant_controller.dart';
import '../../controllers/notification_controller.dart';
import '../auth/login_screen.dart'; // import GridPainter

class AdminNotificationScreen extends StatefulWidget {
  const AdminNotificationScreen({super.key});

  @override
  State<AdminNotificationScreen> createState() => _AdminNotificationScreenState();
}

class _AdminNotificationScreenState extends State<AdminNotificationScreen> {
  final _notifyFormKey = GlobalKey<FormState>();
  final _titleController = TextEditingController();
  final _messageController = TextEditingController();
  String _selectedTarget = 'all'; // 'all' or tenant username/id
  int _selectedNotificationType = 3; // Default: 3 (Hệ thống)

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<TenantController>().fetchTenants();
    });
  }

  @override
  void dispose() {
    _titleController.dispose();
    _messageController.dispose();
    super.dispose();
  }

  void _sendNotification() async {
    if (!_notifyFormKey.currentState!.validate()) return;

    // Hiển thị loading dialog
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => const Center(
        child: CircularProgressIndicator(
          valueColor: AlwaysStoppedAnimation<Color>(Color(0xFFEAB308)),
        ),
      ),
    );

    final notificationController = context.read<NotificationController>();
    
    int targetType = 1; // Tất cả
    int? tenantId;
    
    if (_selectedTarget != 'all') {
      targetType = 4; // Khách thuê
      tenantId = int.tryParse(_selectedTarget);
    }

    final success = await notificationController.sendNotification(
      title: _titleController.text.trim(),
      content: _messageController.text.trim(),
      notificationType: _selectedNotificationType,
      targetType: targetType,
      tenantId: tenantId,
    );

    if (mounted) {
      Navigator.pop(context); // Đóng loading dialog

      if (success) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(targetType == 1
                ? 'Đã gửi thông báo đẩy tới tất cả khách thuê!'
                : 'Đã gửi thông báo đẩy tới khách hàng được chọn!'),
            backgroundColor: Colors.green,
          ),
        );
        _titleController.clear();
        _messageController.clear();
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(notificationController.errorMessage ?? 'Gửi thông báo thất bại. Vui lòng thử lại.'),
            backgroundColor: Colors.redAccent,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final tenantController = context.watch<TenantController>();
    final tenants = tenantController.tenants;

    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        title: const Text('Phát Thông Báo', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: const Color(0xFF1C1917),
      ),
      body: Stack(
        children: [
          Positioned.fill(child: CustomPaint(painter: GridPainter())),
          SingleChildScrollView(
            padding: const EdgeInsets.all(24.0),
            child: Form(
              key: _notifyFormKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'PHÁT THÔNG BÁO PUSH',
                    style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF78716C), letterSpacing: 1.0),
                  ),
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                       color: Colors.white,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: const Color(0xFFE4E2D7)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        DropdownButtonFormField<String>(
                          value: _selectedTarget,
                          dropdownColor: Colors.white,
                          decoration: InputDecoration(
                            labelText: 'Đối tượng nhận',
                            filled: true,
                            fillColor: const Color(0xFFF9F8F6),
                            enabledBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: const BorderSide(color: Color(0xFFE4E2D7)),
                            ),
                            focusedBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: const BorderSide(color: Color(0xFF1C1917)),
                            ),
                          ),
                          items: [
                            const DropdownMenuItem(value: 'all', child: Text('Tất cả khách thuê')),
                            ...tenants.map((t) => DropdownMenuItem(
                                  value: t.id.toString(),
                                  child: Text('${t.fullName} (Phòng ${t.roomNumber ?? 'Chưa có phòng'})'),
                                )),
                          ],
                          onChanged: (val) {
                            if (val != null) {
                              setState(() {
                                _selectedTarget = val;
                              });
                            }
                          },
                        ),
                        const SizedBox(height: 16),
                        DropdownButtonFormField<int>(
                          value: _selectedNotificationType,
                          dropdownColor: Colors.white,
                          decoration: InputDecoration(
                            labelText: 'Loại thông báo',
                            filled: true,
                            fillColor: const Color(0xFFF9F8F6),
                            enabledBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: const BorderSide(color: Color(0xFFE4E2D7)),
                            ),
                            focusedBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: const BorderSide(color: Color(0xFF1C1917)),
                            ),
                          ),
                          items: const [
                            DropdownMenuItem(value: 3, child: Text('Hệ thống')),
                            DropdownMenuItem(value: 1, child: Text('Sửa chữa')),
                            DropdownMenuItem(value: 2, child: Text('Hóa đơn')),
                            DropdownMenuItem(value: 4, child: Text('Cảnh báo')),
                            DropdownMenuItem(value: 5, child: Text('Khác')),
                          ],
                          onChanged: (val) {
                            if (val != null) {
                              setState(() {
                                _selectedNotificationType = val;
                              });
                            }
                          },
                        ),
                        const SizedBox(height: 16),
                        TextFormField(
                          controller: _titleController,
                          style: const TextStyle(color: Color(0xFF1C1917)),
                          decoration: InputDecoration(
                            labelText: 'Tiêu đề thông báo',
                            filled: true,
                            fillColor: const Color(0xFFF9F8F6),
                            enabledBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: const BorderSide(color: Color(0xFFE4E2D7)),
                            ),
                            focusedBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: const BorderSide(color: Color(0xFF1C1917)),
                            ),
                          ),
                          validator: (val) => val == null || val.isEmpty ? 'Vui lòng nhập tiêu đề' : null,
                        ),
                        const SizedBox(height: 16),
                        TextFormField(
                          controller: _messageController,
                          maxLines: 4,
                          style: const TextStyle(color: Color(0xFF1C1917)),
                          decoration: InputDecoration(
                            labelText: 'Nội dung chi tiết',
                            alignLabelWithHint: true,
                            filled: true,
                            fillColor: const Color(0xFFF9F8F6),
                            enabledBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: const BorderSide(color: Color(0xFFE4E2D7)),
                            ),
                            focusedBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: const BorderSide(color: Color(0xFF1C1917)),
                            ),
                          ),
                          validator: (val) => val == null || val.isEmpty ? 'Vui lòng nhập nội dung' : null,
                        ),
                        const SizedBox(height: 24),
                        ElevatedButton.icon(
                          onPressed: _sendNotification,
                          icon: const Icon(Icons.send, color: Colors.white),
                          label: const Text('GỬI THÔNG BÁO PUSH', style: TextStyle(fontWeight: FontWeight.bold)),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFF1C1917),
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 16),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
