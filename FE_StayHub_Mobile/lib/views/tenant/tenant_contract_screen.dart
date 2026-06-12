import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../controllers/auth_controller.dart';
import '../../controllers/contract_controller.dart';
import '../auth/login_screen.dart'; // import GridPainter

class TenantContractScreen extends StatelessWidget {
  const TenantContractScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final authController = context.watch<AuthController>();
    final tenant = authController.currentTenant;
    final roomNumber = tenant?.roomNumber ?? '101';

    final contractController = context.watch<ContractController>();
    final contractList = contractController.contracts.where((c) => c.roomNumber == roomNumber).toList();
    final contract = contractList.isNotEmpty ? contractList.first : null;

    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        title: const Text('Hợp đồng của tôi', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: const Color(0xFF1C1917),
      ),
      body: Stack(
        children: [
          Positioned.fill(child: CustomPaint(painter: GridPainter())),
          contract == null
              ? const Center(child: Text('Không tìm thấy thông tin hợp đồng.', style: TextStyle(color: Colors.grey)))
              : SingleChildScrollView(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      // Header Card
                      Container(
                        padding: const EdgeInsets.all(20),
                        decoration: BoxDecoration(
                          color: const Color(0xFF1C1917),
                          borderRadius: BorderRadius.circular(20),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.05),
                              blurRadius: 10,
                              offset: const Offset(0, 4),
                            ),
                          ],
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(
                                  contract.contractCode,
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontSize: 20,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                _buildStatusBadge(contract.status, contract.statusLabel),
                              ],
                            ),
                            const SizedBox(height: 12),
                            Text(
                              'Phòng ${contract.roomNumber} • ${tenant?.buildingName ?? "StayHub Sài Gòn"}',
                              style: TextStyle(
                                color: Colors.white.withValues(alpha: 0.8),
                                fontSize: 14,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 20),

                      // General Info
                      const Text(
                        'THÔNG TIN CHI TIẾT',
                        style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF78716C), letterSpacing: 1.0),
                      ),
                      const SizedBox(height: 12),
                      Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: const Color(0xFFE4E2D7)),
                        ),
                        child: Column(
                          children: [
                            _buildInfoRow('Người đại diện:', contract.tenantName, isBold: true),
                            _buildInfoRow('Số điện thoại:', tenant?.phone ?? ''),
                            _buildInfoRow('Email:', tenant?.email ?? ''),
                            _buildInfoRow('Ngày bắt đầu:', contract.startDate),
                            _buildInfoRow('Ngày hết hạn:', contract.endDate ?? 'Vô thời hạn'),
                            _buildInfoRow('Chu kỳ đóng tiền:', 'Ngày ${contract.billingCycleDay} hàng tháng'),
                          ],
                        ),
                      ),
                      const SizedBox(height: 20),

                      // Financial Details
                      const Text(
                        'THÔNG TIN TÀI CHÍNH',
                        style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF78716C), letterSpacing: 1.0),
                      ),
                      const SizedBox(height: 12),
                      Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: const Color(0xFFE4E2D7)),
                        ),
                        child: Column(
                          children: [
                            _buildInfoRow(
                              'Giá thuê phòng:',
                              '${contract.roomPrice.toStringAsFixed(0)}đ/tháng',
                              valueColor: const Color(0xFF1C1917),
                              isBold: true,
                            ),
                            _buildInfoRow(
                              'Tiền đặt cọc:',
                              '${contract.depositAmount.toStringAsFixed(0)}đ',
                              valueColor: Colors.green,
                              isBold: true,
                            ),
                            _buildInfoRow(
                              'Trạng thái cọc:',
                              'Đã nhận cọc',
                              valueColor: Colors.green,
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 20),

                      // Terms Card
                      Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFFFBEB),
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: const Color(0xFFFDE68A)),
                        ),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: const [
                            Icon(Icons.gavel_rounded, color: Color(0xFFD97706), size: 20),
                            SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    'Lưu ý điều khoản hợp đồng',
                                    style: TextStyle(
                                      fontWeight: FontWeight.bold,
                                      fontSize: 14,
                                      color: Color(0xFF92400E),
                                    ),
                                  ),
                                  SizedBox(height: 4),
                                  Text(
                                    'Vui lòng thông báo cho Ban quản lý ít nhất 30 ngày trước khi trả phòng hoặc muốn gia hạn thêm hợp đồng thuê.',
                                    style: TextStyle(
                                      fontSize: 12,
                                      color: Color(0xFFB45309),
                                      height: 1.4,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 24),

                      // Download Contract PDF
                      ElevatedButton.icon(
                        onPressed: () {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(
                              content: Text('Đang tải tệp đính kèm hợp đồng PDF...'),
                              backgroundColor: Color(0xFF1C1917),
                            ),
                          );
                        },
                        icon: const Icon(Icons.download_rounded, color: Colors.white),
                        label: const Text('TẢI FILE HỢP ĐỒNG (PDF)', style: TextStyle(fontWeight: FontWeight.bold)),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFF1C1917),
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(vertical: 16),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        ),
                      ),
                      const SizedBox(height: 40),
                    ],
                  ),
                ),
        ],
      ),
    );
  }

  Widget _buildInfoRow(String label, String value, {Color? valueColor, bool isBold = false}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12.0),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: Colors.grey, fontSize: 13)),
          Text(
            value,
            style: TextStyle(
              fontWeight: isBold ? FontWeight.bold : FontWeight.w600,
              color: valueColor ?? const Color(0xFF1C1917),
              fontSize: 13,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStatusBadge(int status, String label) {
    Color color = Colors.grey;
    if (status == 2) color = Colors.green; // Active
    if (status == 3) color = const Color(0xFFEAB308); // Expired
    if (status == 4) color = Colors.blue; // Liquidated
    if (status == 5) color = Colors.redAccent; // Cancelled

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        label,
        style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.bold),
      ),
    );
  }
}
