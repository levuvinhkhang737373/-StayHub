import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:image_picker/image_picker.dart';
import 'dart:io';
import 'dart:typed_data';
import 'package:flutter/foundation.dart';
import '../../controllers/maintenance_controller.dart';
import '../auth/login_screen.dart'; // import GridPainter

class MaintenanceScreen extends StatefulWidget {
  const MaintenanceScreen({super.key});

  @override
  State<MaintenanceScreen> createState() => _MaintenanceScreenState();
}

class _MaintenanceScreenState extends State<MaintenanceScreen> {
  XFile? _pickedAfterImage;
  Uint8List? _pickedAfterImageBytes;

  void _selectAfterImage(StateSetter setStateDialog) async {
    final ImagePicker picker = ImagePicker();
    try {
      final XFile? img = await picker.pickImage(source: ImageSource.gallery, imageQuality: 85);
      if (img != null) {
        final bytes = await img.readAsBytes();
        setStateDialog(() {
          _pickedAfterImage = img;
          _pickedAfterImageBytes = bytes;
        });
      }
    } catch (e) {
      debugPrint('Lỗi chọn ảnh: $e');
    }
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<MaintenanceController>().fetchAdminRequests();
    });
  }

  void _updateRequestStatus(dynamic request) {
    int selectedStatus = request.status == 2 ? 3 : request.status;
    _pickedAfterImage = null;
    _pickedAfterImageBytes = null;

    showDialog(
      context: context,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setStateDialog) {
            return AlertDialog(
              title: Text('Xử lý Yêu cầu Phòng ${request.roomNumber}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
              content: SizedBox(
                width: 320,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                      DropdownButtonFormField<int>(
                        isExpanded: true,
                        value: selectedStatus,
                        decoration: const InputDecoration(labelText: 'Trạng thái xử lý', border: OutlineInputBorder()),
                        items: const [
                          DropdownMenuItem(value: 1, child: Text('Mới tạo')),
                          DropdownMenuItem(value: 3, child: Text('Đang xử lý')),
                          DropdownMenuItem(value: 4, child: Text('Đã hoàn thành')),
                          DropdownMenuItem(value: 5, child: Text('Đã hủy')),
                        ],
                        onChanged: (val) {
                          if (val != null) {
                            setStateDialog(() {
                              selectedStatus = val;
                            });
                          }
                        },
                      ),
                    const SizedBox(height: 16),
                    
                    // After Photo capture (Required if resolved)
                    if (selectedStatus == 4) ...[
                      const Text('ẢNH MINH CHỨNG HOÀN THÀNH', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Colors.grey)),
                      const SizedBox(height: 8),
                      InkWell(
                        onTap: () => _selectAfterImage(setStateDialog),
                        child: Container(
                          height: 100,
                          decoration: BoxDecoration(
                            color: const Color(0xFFF9F8F6),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: const Color(0xFFE4E2D7)),
                          ),
                          child: _pickedAfterImageBytes != null
                              ? ClipRRect(
                                  borderRadius: BorderRadius.circular(12),
                                  child: Image.memory(
                                    _pickedAfterImageBytes!,
                                    fit: BoxFit.cover,
                                  ),
                                )
                              : (request.afterImageUrl != null)
                                  ? ClipRRect(
                                      borderRadius: BorderRadius.circular(12),
                                      child: Image.network(
                                        request.afterImageUrl!,
                                        fit: BoxFit.cover,
                                        errorBuilder: (context, error, stackTrace) => Container(
                                          color: const Color(0xFFF9F8F6),
                                          child: const Center(child: Icon(Icons.broken_image, color: Colors.grey)),
                                        ),
                                      ),
                                    )
                                  : const Column(
                                      mainAxisAlignment: MainAxisAlignment.center,
                                      children: [
                                        Icon(Icons.camera_alt_outlined, size: 28, color: Colors.grey),
                                        SizedBox(height: 4),
                                        Text('Bấm để chọn/chụp ảnh thực tế', style: TextStyle(color: Colors.grey, fontSize: 11)),
                                      ],
                                    ),
                        ),
                      ),
                    ]
                  ],
                ),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(context),
                  child: const Text('HỦY', style: TextStyle(color: Colors.grey)),
                ),
                TextButton(
                  onPressed: () async {
                    if (selectedStatus == 4 && _pickedAfterImage == null && request.afterImageUrl == null) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Vui lòng chọn ảnh minh chứng hoàn thành!'), backgroundColor: Colors.redAccent),
                      );
                      return;
                    }

                    final success = await context.read<MaintenanceController>().updateRequestStatus(
                      request.id,
                      selectedStatus,
                      afterImageFile: _pickedAfterImage,
                    );

                    if (success && mounted) {
                      Navigator.pop(context);
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Cập nhật trạng thái thành công'), backgroundColor: Colors.green),
                      );
                    }
                  },
                  child: const Text('CẬP NHẬT', style: TextStyle(color: Color(0xFF1C1917), fontWeight: FontWeight.bold)),
                ),
              ],
            );
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final controller = context.watch<MaintenanceController>();
    final requests = controller.requests;

    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        title: const Text('Tiếp nhận sửa chữa', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: const Color(0xFF1C1917),
      ),
      body: Stack(
        children: [
          Positioned.fill(child: CustomPaint(painter: GridPainter())),
          requests.isEmpty
              ? const Center(child: Text('Không có yêu cầu sửa chữa nào.', style: TextStyle(color: Colors.grey)))
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: requests.length,
                  itemBuilder: (context, index) {
                    final request = requests[index];
                    return Card(
                      color: Colors.white,
                      margin: const EdgeInsets.only(bottom: 16),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(16),
                        side: const BorderSide(color: Color(0xFFE4E2D7)),
                      ),
                      elevation: 0,
                      child: Padding(
                        padding: const EdgeInsets.all(16.0),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            // Header Room & Badge
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(
                                  'Phòng ${request.roomNumber} — ${request.tenantName}',
                                  style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917), fontSize: 14),
                                ),
                                _buildStatusBadge(request.status, request.statusLabel),
                              ],
                            ),
                            const SizedBox(height: 8),

                            // Title & Description
                            Text(
                              request.title,
                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Color(0xFF1C1917)),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              request.description,
                              style: const TextStyle(fontSize: 13, color: Colors.grey),
                            ),
                            const SizedBox(height: 12),

                            // Before / After Photos (Side by Side)
                            Row(
                              children: [
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      const Text('Ảnh Trước', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.grey)),
                                      const SizedBox(height: 4),
                                      if (request.beforeImageUrl != null)
                                        ClipRRect(
                                          borderRadius: BorderRadius.circular(8),
                                          child: Image.network(
                                            request.beforeImageUrl!,
                                            height: 100,
                                            width: double.infinity,
                                            fit: BoxFit.cover,
                                            errorBuilder: (context, error, stackTrace) => Container(
                                              height: 100,
                                              color: const Color(0xFFF9F8F6),
                                              child: const Center(child: Icon(Icons.broken_image, color: Colors.grey)),
                                            ),
                                          ),
                                        )
                                      else
                                        const Text('Không có ảnh', style: TextStyle(fontSize: 12, color: Colors.grey)),
                                    ],
                                  ),
                                ),
                                const SizedBox(width: 16),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      const Text('Ảnh Sau', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.grey)),
                                      const SizedBox(height: 4),
                                      if (request.afterImageUrl != null)
                                        ClipRRect(
                                          borderRadius: BorderRadius.circular(8),
                                          child: Image.network(
                                            request.afterImageUrl!,
                                            height: 100,
                                            width: double.infinity,
                                            fit: BoxFit.cover,
                                            errorBuilder: (context, error, stackTrace) => Container(
                                              height: 100,
                                              color: const Color(0xFFF9F8F6),
                                              child: const Center(child: Icon(Icons.broken_image, color: Colors.grey)),
                                            ),
                                          ),
                                        )
                                      else
                                        Container(
                                          height: 100,
                                          decoration: BoxDecoration(color: const Color(0xFFF9F8F6), borderRadius: BorderRadius.circular(8), border: Border.all(color: const Color(0xFFE4E2D7))),
                                          child: const Center(child: Text('Chưa sửa xong', style: TextStyle(fontSize: 11, color: Colors.grey))),
                                        ),
                                    ],
                                  ),
                                ),
                              ],
                            ),

                            // Tenant feedback display
                            if (request.feedback != null && request.feedback!.isNotEmpty) ...[
                              const Divider(height: 24, color: Color(0xFFE4E2D7)),
                              Container(
                                padding: const EdgeInsets.all(10),
                                decoration: BoxDecoration(
                                  color: Colors.green.withValues(alpha: 0.05),
                                  borderRadius: BorderRadius.circular(8),
                                  border: Border.all(color: Colors.green.withValues(alpha: 0.15)),
                                ),
                                child: Text(
                                  'Phản hồi khách: "${request.feedback}"',
                                  style: const TextStyle(fontSize: 12, color: Colors.green, fontStyle: FontStyle.italic),
                                ),
                              )
                            ],

                            // Actions
                            if (request.status != 4 && request.status != 5) ...[
                              const Divider(height: 24, color: Color(0xFFE4E2D7)),
                              ElevatedButton.icon(
                                onPressed: () => _updateRequestStatus(request),
                                icon: const Icon(Icons.settings_outlined, size: 18),
                                label: const Text('CẬP NHẬT TRẠNG THÁI'),
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: const Color(0xFF1C1917),
                                  foregroundColor: Colors.white,
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                  elevation: 0,
                                ),
                              ),
                            ],
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

  Widget _buildStatusBadge(int status, String label) {
    Color color = Colors.grey;
    if (status == 1) color = Colors.redAccent;          // Mới tạo
    if (status == 3) color = const Color(0xFFEAB308);   // Đang xử lý
    if (status == 4) color = Colors.green;              // Đã hoàn thành
    if (status == 5) color = Colors.grey;               // Đã hủy

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        label,
        style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.bold),
      ),
    );
  }
}
