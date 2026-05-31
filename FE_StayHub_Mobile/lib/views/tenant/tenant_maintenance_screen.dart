import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../controllers/auth_controller.dart';
import '../../controllers/maintenance_controller.dart';
import '../auth/login_screen.dart'; // import GridPainter

class TenantMaintenanceScreen extends StatefulWidget {
  const TenantMaintenanceScreen({super.key});

  @override
  State<TenantMaintenanceScreen> createState() => _TenantMaintenanceScreenState();
}

class _TenantMaintenanceScreenState extends State<TenantMaintenanceScreen> {
  void _showAddRequestDialog() {
    final formKey = GlobalKey<FormState>();
    final titleController = TextEditingController();
    final descController = TextEditingController();

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (context) {
        return Padding(
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(context).viewInsets.bottom,
            top: 24,
            left: 24,
            right: 24,
          ),
          child: SingleChildScrollView(
            child: Form(
              key: formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Text(
                    'Báo cáo Sự cố Sửa chữa',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                  ),
                  const SizedBox(height: 16),
                  
                  TextFormField(
                    controller: titleController,
                    style: const TextStyle(color: Color(0xFF1C1917)),
                    decoration: const InputDecoration(
                      labelText: 'Tiêu đề / Tên sự cố',
                      border: OutlineInputBorder(),
                      hintText: 'Ví dụ: Hỏng vòi nước, Điều hòa chảy nước',
                    ),
                    validator: (val) => val == null || val.isEmpty ? 'Vui lòng nhập tiêu đề' : null,
                  ),
                  const SizedBox(height: 16),
                  
                  TextFormField(
                    controller: descController,
                    maxLines: 4,
                    style: const TextStyle(color: Color(0xFF1C1917)),
                    decoration: const InputDecoration(
                      labelText: 'Mô tả chi tiết',
                      border: OutlineInputBorder(),
                      alignLabelWithHint: true,
                      hintText: 'Mô tả rõ hiện tượng hư hỏng để kỹ thuật viên chuẩn bị dụng cụ.',
                    ),
                    validator: (val) => val == null || val.isEmpty ? 'Vui lòng nhập mô tả' : null,
                  ),
                  const SizedBox(height: 16),
                  
                  // Mock camera upload widget (FaceID and camera dependencies removed)
                  Container(
                    padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 16),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF7F6F0),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: const Color(0xFFE4E2D7)),
                    ),
                    child: const Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.photo_camera, color: Color(0xFF1C1917)),
                        SizedBox(width: 12),
                        Text(
                          'Đã đính kèm ảnh minh chứng sự cố',
                          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF1C1917)),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 24),
                  
                  ElevatedButton(
                    onPressed: () async {
                      if (!formKey.currentState!.validate()) return;
                      
                      final authController = context.read<AuthController>();
                      final roomNumber = authController.currentTenant?.roomNumber ?? '101';
                      
                      final success = await context.read<MaintenanceController>().createRequest(
                            roomNumber: roomNumber,
                            title: titleController.text.trim(),
                            description: descController.text.trim(),
                          );

                      if (success && mounted) {
                        Navigator.pop(context);
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Gửi yêu cầu sửa chữa thành công!'), backgroundColor: Colors.green),
                        );
                      }
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF1C1917),
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    child: const Text('GỬI YÊU CẦU', style: TextStyle(fontWeight: FontWeight.bold)),
                  ),
                  const SizedBox(height: 32),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  void _showFeedbackDialog(int requestId) {
    final controller = TextEditingController();
    showDialog(
      context: context,
      builder: (context) {
        return AlertDialog(
          title: const Text('Gửi phản hồi chất lượng', style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917))),
          content: TextField(
            controller: controller,
            maxLines: 3,
            decoration: const InputDecoration(
              hintText: 'Nhập đánh giá hoặc phản hồi của bạn sau khi kỹ thuật viên sửa chữa xong...',
              border: OutlineInputBorder(),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('HỦY'),
            ),
            TextButton(
              onPressed: () async {
                if (controller.text.trim().isNotEmpty) {
                  final success = await context.read<MaintenanceController>().addFeedback(
                        requestId,
                        controller.text.trim(),
                      );
                  if (success && mounted) {
                    Navigator.pop(context);
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Cảm ơn bạn đã phản hồi!'), backgroundColor: Colors.green),
                    );
                  }
                }
              },
              child: const Text('GỬI', style: TextStyle(color: Color(0xFF1C1917), fontWeight: FontWeight.bold)),
            ),
          ],
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final authController = context.watch<AuthController>();
    final tenant = authController.currentTenant;

    final maintenanceController = context.watch<MaintenanceController>();
    final roomNumber = tenant?.roomNumber ?? '101';
    final requests = maintenanceController.getRequestsForRoom(roomNumber);

    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        title: const Text('Yêu cầu sửa chữa', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: const Color(0xFF1C1917),
      ),
      body: Stack(
        children: [
          Positioned.fill(child: CustomPaint(painter: GridPainter())),
          requests.isEmpty
              ? const Center(child: Text('Chưa có yêu cầu sửa chữa nào.', style: TextStyle(color: Colors.grey)))
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: requests.length,
                  itemBuilder: (context, index) {
                    final req = requests[index];
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
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Expanded(
                                  child: Text(
                                    req.title,
                                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Color(0xFF1C1917)),
                                  ),
                                ),
                                _buildStatusBadge(req.status, req.statusLabel),
                              ],
                            ),
                            const SizedBox(height: 4),
                            Text('Ngày tạo: ${req.createdAt}', style: const TextStyle(fontSize: 11, color: Colors.grey)),
                            const SizedBox(height: 12),
                            Text(req.description, style: const TextStyle(fontSize: 13, color: Color(0xFF1C1917))),
                            const SizedBox(height: 12),
                            
                            // Images comparison section
                            Row(
                              children: [
                                if (req.beforeImageUrl != null)
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        const Text('Trước khi sửa:', style: TextStyle(fontSize: 10, color: Colors.grey, fontWeight: FontWeight.bold)),
                                        const SizedBox(height: 4),
                                        ClipRRect(
                                          borderRadius: BorderRadius.circular(8),
                                          child: Image.network(req.beforeImageUrl!, height: 100, fit: BoxFit.cover),
                                        ),
                                      ],
                                    ),
                                  ),
                                if (req.afterImageUrl != null) ...[
                                  const SizedBox(width: 12),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        const Text('Sau khi sửa:', style: TextStyle(fontSize: 10, color: Colors.grey, fontWeight: FontWeight.bold)),
                                        const SizedBox(height: 4),
                                        ClipRRect(
                                          borderRadius: BorderRadius.circular(8),
                                          child: Image.network(req.afterImageUrl!, height: 100, fit: BoxFit.cover),
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                              ],
                            ),
                            
                            if (req.feedback != null) ...[
                              const Divider(height: 24, color: Color(0xFFE4E2D7)),
                              Container(
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFF7F6F0),
                                  borderRadius: BorderRadius.circular(8),
                                  border: Border.all(color: const Color(0xFFE4E2D7)),
                                ),
                                child: Text(
                                  'Phản hồi của bạn: ${req.feedback}',
                                  style: const TextStyle(fontSize: 12, fontStyle: FontStyle.italic, color: Color(0xFF1C1917)),
                                ),
                              ),
                            ] else if (req.status == 3) ...[
                              const Divider(height: 24, color: Color(0xFFE4E2D7)),
                              OutlinedButton.icon(
                                onPressed: () => _showFeedbackDialog(req.id),
                                icon: const Icon(Icons.rate_review, size: 16),
                                label: const Text('Gửi phản hồi / đánh giá', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                                style: OutlinedButton.styleFrom(
                                  foregroundColor: const Color(0xFF1C1917),
                                  side: const BorderSide(color: Color(0xFF1C1917)),
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                ),
                              ),
                            ]
                          ],
                        ),
                      ),
                    );
                  },
                ),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: _showAddRequestDialog,
        backgroundColor: const Color(0xFF1C1917),
        foregroundColor: const Color(0xFFEAB308),
        child: const Icon(Icons.add_comment_rounded),
      ),
    );
  }

  Widget _buildStatusBadge(int status, String label) {
    Color color = Colors.grey;
    if (status == 1) color = const Color(0xFFEAB308); // Pending
    if (status == 2) color = Colors.blueAccent; // In Progress
    if (status == 3) color = Colors.green; // Resolved

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
