import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../controllers/maintenance_controller.dart';
import '../auth/login_screen.dart'; // import GridPainter

class MaintenanceScreen extends StatefulWidget {
  const MaintenanceScreen({super.key});

  @override
  State<MaintenanceScreen> createState() => _MaintenanceScreenState();
}

class _MaintenanceScreenState extends State<MaintenanceScreen> {
  void _updateRequestStatus(dynamic request) {
    int selectedStatus = request.status;
    bool hasAfterPhoto = request.afterImageUrl != null;

    showDialog(
      context: context,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setStateDialog) {
            return AlertDialog(
              title: Text('Xử lý Yêu cầu Phòng ${request.roomNumber}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
              content: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  DropdownButtonFormField<int>(
                    initialValue: selectedStatus,
                    decoration: const InputDecoration(labelText: 'Trạng thái xử lý', border: OutlineInputBorder()),
                    items: const [
                      DropdownMenuItem(value: 1, child: Text('Chưa xử lý')),
                      DropdownMenuItem(value: 2, child: Text('Đang sửa chữa')),
                      DropdownMenuItem(value: 3, child: Text('Đã hoàn thành')),
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
                  
                  // After Photo capture simulation (Required if resolved)
                  if (selectedStatus == 3) ...[
                    const Text('ẢNH MINH CHỨNG HOÀN THÀNH', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Colors.grey)),
                    const SizedBox(height: 8),
                    InkWell(
                      onTap: () async {
                        setStateDialog(() {
                          hasAfterPhoto = true;
                        });
                      },
                      child: Container(
                        height: 100,
                        decoration: BoxDecoration(
                          color: const Color(0xFFF9F8F6),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: const Color(0xFFE4E2D7)),
                        ),
                        child: hasAfterPhoto
                            ? ClipRRect(
                                borderRadius: BorderRadius.circular(12),
                                child: Image.network(
                                  'https://images.unsplash.com/photo-1550985616-10810253b84d?auto=format&fit=crop&q=80&w=200',
                                  fit: double.infinity.toString() == 'double.infinity' ? BoxFit.cover : BoxFit.fill,
                                ),
                              )
                            : const Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(Icons.camera_alt_outlined, size: 28, color: Colors.grey),
                                  SizedBox(height: 4),
                                  Text('Bấm chụp ảnh sau sửa chữa', style: TextStyle(color: Colors.grey, fontSize: 11)),
                                ],
                              ),
                      ),
                    ),
                  ]
                ],
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(context),
                  child: const Text('HỦY', style: TextStyle(color: Colors.grey)),
                ),
                TextButton(
                  onPressed: () async {
                    if (selectedStatus == 3 && !hasAfterPhoto) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Vui lòng chụp ảnh minh chứng hoàn thành!'), backgroundColor: Colors.redAccent),
                      );
                      return;
                    }

                    final afterImg = selectedStatus == 3 ? 'https://images.unsplash.com/photo-1550985616-10810253b84d?auto=format&fit=crop&q=80&w=300' : null;
                    final success = await context.read<MaintenanceController>().updateRequestStatus(
                      request.id,
                      selectedStatus,
                      afterImageUrl: afterImg,
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
                                          child: Image.network(request.beforeImageUrl!, height: 100, width: double.infinity, fit: double.infinity.toString() == 'double.infinity' ? BoxFit.cover : BoxFit.fill),
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
                                          child: Image.network(request.afterImageUrl!, height: 100, width: double.infinity, fit: double.infinity.toString() == 'double.infinity' ? BoxFit.cover : BoxFit.fill),
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
                            if (request.status != 3) ...[
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
    if (status == 1) color = Colors.redAccent;
    if (status == 2) color = const Color(0xFFEAB308);
    if (status == 3) color = Colors.green;

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
