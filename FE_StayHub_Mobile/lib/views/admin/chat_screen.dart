import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../controllers/tenant_controller.dart';
import '../../controllers/notification_controller.dart';
import '../../controllers/facility_controller.dart';
import '../../controllers/room_controller.dart';
import '../auth/login_screen.dart'; // import GridPainter

class AdminNotificationScreen extends StatefulWidget {
  const AdminNotificationScreen({super.key});

  @override
  State<AdminNotificationScreen> createState() =>
      _AdminNotificationScreenState();
}

class _AdminNotificationScreenState extends State<AdminNotificationScreen> {
  final _notifyFormKey = GlobalKey<FormState>();
  final _titleController = TextEditingController();
  final _messageController = TextEditingController();
  String _targetCategory = 'room'; // 'room' or 'tenant'
  String? _selectedBuildingId;
  String _selectedRoomTarget = 'all_rooms'; // 'all_rooms' or room id
  String _selectedTenantTarget = 'all_tenants'; // 'all_tenants' or tenant id
  int _selectedNotificationType = 3; // Default: 3 (Hệ thống)

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<TenantController>().fetchTenants();
      context.read<RoomController>().fetchRooms();
      context.read<FacilityController>().fetchBuildings().then((_) {
        final buildings = context.read<FacilityController>().buildings;
        if (buildings.isNotEmpty && mounted) {
          setState(() {
            _selectedBuildingId = buildings.first.id.toString();
          });
        }
      });
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
    int? buildingId;
    int? roomId;

    if (_targetCategory == 'room') {
      buildingId = int.tryParse(_selectedBuildingId ?? '');
      if (buildingId == null) {
        Navigator.pop(context); // Đóng loading dialog
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Vui lòng chọn tòa nhà mục tiêu!'), backgroundColor: Colors.redAccent),
        );
        return;
      }

      if (_selectedRoomTarget == 'all_rooms') {
        targetType = 2; // Theo tòa nhà
      } else {
        targetType = 3; // Theo phòng
        roomId = int.tryParse(_selectedRoomTarget);
      }
    } else {
      if (_selectedTenantTarget == 'all_tenants') {
        targetType = 1; // Tất cả khách thuê
      } else {
        targetType = 4; // Theo khách thuê
        tenantId = int.tryParse(_selectedTenantTarget);
      }
    }

    final success = await notificationController.sendNotification(
      title: _titleController.text.trim(),
      content: _messageController.text.trim(),
      notificationType: _selectedNotificationType,
      targetType: targetType,
      tenantId: tenantId,
      buildingId: buildingId,
      roomId: roomId,
    );

    if (mounted) {
      Navigator.pop(context); // Đóng loading dialog

      if (success) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Đã gửi thông báo đẩy thành công!'),
            backgroundColor: Colors.green,
          ),
        );
        _titleController.clear();
        _messageController.clear();
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              notificationController.errorMessage ??
                  'Gửi thông báo thất bại. Vui lòng thử lại.',
            ),
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
    final roomController = context.watch<RoomController>();
    final facilityController = context.watch<FacilityController>();
    final buildings = facilityController.buildings;

    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        title: const Text(
          'Phát Thông Báo',
          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
        ),
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
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                      color: Color(0xFF78716C),
                      letterSpacing: 1.0,
                    ),
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
                          value: _targetCategory,
                          dropdownColor: Colors.white,
                          decoration: InputDecoration(
                            labelText: 'Hình thức gửi',
                            filled: true,
                            fillColor: const Color(0xFFF9F8F6),
                            enabledBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: const BorderSide(
                                color: Color(0xFFE4E2D7),
                              ),
                            ),
                            focusedBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: const BorderSide(
                                color: Color(0xFF1C1917),
                              ),
                            ),
                          ),
                          items: const [
                            DropdownMenuItem(value: 'room', child: Text('Gửi theo tòa nhà / phòng')),
                            DropdownMenuItem(value: 'tenant', child: Text('Gửi theo khách thuê')),
                          ],
                          onChanged: (val) {
                            if (val != null) {
                              setState(() {
                                _targetCategory = val;
                              });
                            }
                          },
                        ),
                        const SizedBox(height: 16),
                        if (_targetCategory == 'room') ...[
                          if (buildings.isEmpty)
                            const Padding(
                              padding: EdgeInsets.symmetric(vertical: 8.0),
                              child: Center(
                                child: SizedBox(
                                  width: 24,
                                  height: 24,
                                  child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFFEAB308)),
                                ),
                              ),
                            )
                          else ...[
                            DropdownButtonFormField<String>(
                              value: _selectedBuildingId,
                              dropdownColor: Colors.white,
                              decoration: InputDecoration(
                                labelText: 'Chọn Tòa nhà',
                                filled: true,
                                fillColor: const Color(0xFFF9F8F6),
                                enabledBorder: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(12),
                                  borderSide: const BorderSide(
                                    color: Color(0xFFE4E2D7),
                                  ),
                                ),
                                focusedBorder: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(12),
                                  borderSide: const BorderSide(
                                    color: Color(0xFF1C1917),
                                  ),
                                ),
                              ),
                              items: buildings.map((b) => DropdownMenuItem(
                                value: b.id.toString(),
                                child: Text(b.name),
                              )).toList(),
                              onChanged: (val) {
                                if (val != null) {
                                  setState(() {
                                    _selectedBuildingId = val;
                                    _selectedRoomTarget = 'all_rooms';
                                  });
                                }
                              },
                            ),
                            const SizedBox(height: 16),
                            DropdownButtonFormField<String>(
                              value: _selectedRoomTarget,
                              dropdownColor: Colors.white,
                              decoration: InputDecoration(
                                labelText: 'Chọn Phòng nhận',
                                filled: true,
                                fillColor: const Color(0xFFF9F8F6),
                                enabledBorder: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(12),
                                  borderSide: const BorderSide(
                                    color: Color(0xFFE4E2D7),
                                  ),
                                ),
                                focusedBorder: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(12),
                                  borderSide: const BorderSide(
                                    color: Color(0xFF1C1917),
                                  ),
                                ),
                              ),
                              items: [
                                const DropdownMenuItem(value: 'all_rooms', child: Text('Tất cả phòng (Toàn tòa nhà)')),
                                ...roomController.rooms
                                    .where((r) => r.buildingId.toString() == _selectedBuildingId)
                                    .map((r) => DropdownMenuItem(
                                          value: r.id.toString(),
                                          child: Text('Phòng ${r.roomNumber}'),
                                        )),
                              ],
                              onChanged: (val) {
                                if (val != null) {
                                  setState(() {
                                    _selectedRoomTarget = val;
                                  });
                                }
                              },
                            ),
                            const SizedBox(height: 16),
                          ],
                        ],
                        if (_targetCategory == 'tenant') ...[
                          DropdownButtonFormField<String>(
                            value: _selectedTenantTarget,
                            dropdownColor: Colors.white,
                            decoration: InputDecoration(
                              labelText: 'Chọn Khách thuê nhận',
                              filled: true,
                              fillColor: const Color(0xFFF9F8F6),
                              enabledBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(12),
                                borderSide: const BorderSide(
                                  color: Color(0xFFE4E2D7),
                                ),
                              ),
                              focusedBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(12),
                                borderSide: const BorderSide(
                                  color: Color(0xFF1C1917),
                                ),
                              ),
                            ),
                            items: [
                              const DropdownMenuItem(value: 'all_tenants', child: Text('Tất cả khách thuê')),
                              ...tenants.map((t) => DropdownMenuItem(
                                    value: t.id.toString(),
                                    child: Text('${t.fullName} (Phòng ${t.roomNumber ?? 'Chưa có phòng'})'),
                                  )),
                            ],
                            onChanged: (val) {
                              if (val != null) {
                                setState(() {
                                  _selectedTenantTarget = val;
                                });
                              }
                            },
                          ),
                          const SizedBox(height: 16),
                        ],
                        DropdownButtonFormField<int>(
                          value: _selectedNotificationType,
                          dropdownColor: Colors.white,
                          decoration: InputDecoration(
                            labelText: 'Loại thông báo',
                            filled: true,
                            fillColor: const Color(0xFFF9F8F6),
                            enabledBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: const BorderSide(
                                color: Color(0xFFE4E2D7),
                              ),
                            ),
                            focusedBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: const BorderSide(
                                color: Color(0xFF1C1917),
                              ),
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
                              borderSide: const BorderSide(
                                color: Color(0xFFE4E2D7),
                              ),
                            ),
                            focusedBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: const BorderSide(
                                color: Color(0xFF1C1917),
                              ),
                            ),
                          ),
                          validator: (val) => val == null || val.isEmpty
                              ? 'Vui lòng nhập tiêu đề'
                              : null,
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
                              borderSide: const BorderSide(
                                color: Color(0xFFE4E2D7),
                              ),
                            ),
                            focusedBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: const BorderSide(
                                color: Color(0xFF1C1917),
                              ),
                            ),
                          ),
                          validator: (val) => val == null || val.isEmpty
                              ? 'Vui lòng nhập nội dung'
                              : null,
                        ),
                        const SizedBox(height: 24),
                        ElevatedButton.icon(
                          onPressed: _sendNotification,
                          icon: const Icon(Icons.send, color: Colors.white),
                          label: const Text(
                            'GỬI THÔNG BÁO PUSH',
                            style: TextStyle(fontWeight: FontWeight.bold),
                          ),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFF1C1917),
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 16),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
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
