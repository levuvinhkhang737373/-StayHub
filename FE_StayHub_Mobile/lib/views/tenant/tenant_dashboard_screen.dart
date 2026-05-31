import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../controllers/auth_controller.dart';
import '../../controllers/invoice_controller.dart';
import '../../controllers/maintenance_controller.dart';
import '../auth/login_screen.dart'; // import GridPainter

class TenantDashboardScreen extends StatefulWidget {
  const TenantDashboardScreen({super.key});

  @override
  State<TenantDashboardScreen> createState() => _TenantDashboardScreenState();
}

class _TenantDashboardScreenState extends State<TenantDashboardScreen> {
  Future<void> _handleLogout() async {
    final success = await context.read<AuthController>().logout();
    if (success && mounted) {
      Navigator.pushReplacementNamed(context, '/login');
    }
  }

  @override
  Widget build(BuildContext context) {
    final authController = context.watch<AuthController>();
    final tenant = authController.currentTenant;

    final invoiceController = context.watch<InvoiceController>();
    final maintenanceController = context.watch<MaintenanceController>();

    // Get mock data bound to this tenant (Room 101)
    final roomNumber = tenant?.roomNumber ?? '101';
    final tenantInvoices = invoiceController.getInvoicesForRoom(roomNumber);
    final unpaidInvoices = tenantInvoices.where((i) => i.isUnpaid).toList();

    final tenantRequests = maintenanceController.getRequestsForRoom(roomNumber);
    final activeRequests = tenantRequests.where((r) => r.status != 3).toList();

    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        title: const Text(
          'StayHub Tenant Center',
          style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 18),
        ),
        backgroundColor: const Color(0xFF1C1917),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout, color: Colors.white),
            onPressed: _handleLogout,
            tooltip: 'Đăng xuất',
          )
        ],
      ),
      body: Stack(
        children: [
          Positioned.fill(child: CustomPaint(painter: GridPainter())),
          RefreshIndicator(
            onRefresh: () async {
              await Future.delayed(const Duration(milliseconds: 500));
            },
            color: const Color(0xFF1C1917),
            child: SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(16.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // Welcome Header Card
                  Container(
                    padding: const EdgeInsets.all(24),
                    decoration: BoxDecoration(
                      color: const Color(0xFF1C1917),
                      borderRadius: BorderRadius.circular(20),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.1),
                          blurRadius: 12,
                          offset: const Offset(0, 4),
                        )
                      ],
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'STAYHUB TENANT — CỔNG THÔNG TIN KHÁCH THUÊ',
                          style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.bold,
                            color: Color(0xFFEAB308),
                            letterSpacing: 1.5,
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          tenant?.fullName ?? 'Khách thuê',
                          style: const TextStyle(
                            fontSize: 26,
                            fontWeight: FontWeight.bold,
                            color: Colors.white,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '${tenant?.buildingName ?? "StayHub Sài Gòn Q1"} • Phòng ${tenant?.roomNumber ?? "101"}',
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.bold,
                            color: Colors.white.withValues(alpha: 0.8),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),

                  // Billing alert banner if there are unpaid invoices
                  if (unpaidInvoices.isNotEmpty) ...[
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFEF08A), // Light yellow warning background
                        border: Border.all(color: const Color(0xFFEAB308), width: 1.5),
                        borderRadius: BorderRadius.circular(16),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.warning_amber_rounded, color: Color(0xFF854D0E), size: 28),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text(
                                  'Hóa đơn chưa thanh toán!',
                                  style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF854D0E), fontSize: 14),
                                ),
                                Text(
                                  'Bạn có ${unpaidInvoices.length} hóa đơn chưa hoàn tất thanh toán.',
                                  style: const TextStyle(color: Color(0xFF854D0E), fontSize: 12),
                                ),
                              ],
                            ),
                          ),
                          TextButton(
                            onPressed: () => Navigator.pushNamed(context, '/tenant/invoices'),
                            style: TextButton.styleFrom(
                              backgroundColor: const Color(0xFF1C1917),
                              foregroundColor: Colors.white,
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                            ),
                            child: const Text('Xem ngay', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                          )
                        ],
                      ),
                    ),
                    const SizedBox(height: 20),
                  ],

                  // Quick Stats cards or info
                  const Text(
                    'TRẠNG THÁI CÁ NHÂN',
                    style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF78716C), letterSpacing: 1.0),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: _buildDashboardStatCard(
                          title: 'HÓA ĐƠN CHƯA CHI',
                          value: '${unpaidInvoices.length}',
                          icon: Icons.receipt_long_outlined,
                          color: unpaidInvoices.isNotEmpty ? const Color(0xFFEAB308) : Colors.green,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: _buildDashboardStatCard(
                          title: 'YÊU CẦU ĐANG XỬ LÝ',
                          value: '${activeRequests.length}',
                          icon: Icons.build_circle_outlined,
                          color: activeRequests.isNotEmpty ? Colors.blueAccent : Colors.grey,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 20),

                  // Menu Shortcuts
                  const Text(
                    'DỊCH VỤ TIỆN ÍCH KHÁCH THUÊ',
                    style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF78716C), letterSpacing: 1.0),
                  ),
                  const SizedBox(height: 12),
                  GridView.count(
                    crossAxisCount: 2,
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    crossAxisSpacing: 16,
                    mainAxisSpacing: 16,
                    childAspectRatio: 1.15,
                    children: [
                      _buildMenuShortcutCard(
                        title: 'Xem hóa đơn',
                        subtitle: 'Thanh toán & Lịch sử',
                        icon: Icons.receipt_long,
                        color: const Color(0xFF1C1917),
                        onTap: () => Navigator.pushNamed(context, '/tenant/invoices'),
                      ),
                      _buildMenuShortcutCard(
                        title: 'Báo cáo sự cố',
                        subtitle: 'Yêu cầu sửa chữa',
                        icon: Icons.build,
                        color: const Color(0xFFEAB308),
                        onTap: () => Navigator.pushNamed(context, '/tenant/maintenance'),
                      ),
                      _buildMenuShortcutCard(
                        title: 'Thông báo',
                        subtitle: 'Xem thông báo mới',
                        icon: Icons.campaign_rounded,
                        color: const Color(0xFF78716C),
                        onTap: () => Navigator.pushNamed(context, '/tenant/notifications'),
                      ),
                      _buildMenuShortcutCard(
                        title: 'Thông tin cá nhân',
                        subtitle: 'Đổi mật khẩu & Profile',
                        icon: Icons.person,
                        color: Colors.blueAccent,
                        onTap: () => Navigator.pushNamed(context, '/settings'),
                      ),
                    ],
                  ),
                  const SizedBox(height: 24),

                  // Room detail summary block
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: const Color(0xFFE4E2D7)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'THÔNG TIN HỢP ĐỒNG PHÒNG',
                          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF1C1917)),
                        ),
                        const Divider(height: 20, color: Color(0xFFE4E2D7)),
                        _buildDetailRow('Địa chỉ hiện tại:', tenant?.currentAddress ?? 'Phòng 101'),
                        _buildDetailRow('Số điện thoại đăng kí:', tenant?.phone ?? ''),
                        _buildDetailRow('Email liên hệ:', tenant?.email ?? ''),
                        _buildDetailRow('Nơi thường trú:', tenant?.permanentAddress ?? 'Chưa cập nhật'),
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

  Widget _buildDashboardStatCard({
    required String title,
    required String value,
    required IconData icon,
    required Color color,
  }) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE4E2D7)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Icon(icon, color: color, size: 24),
              Container(
                width: 8,
                height: 8,
                decoration: BoxDecoration(color: color, shape: BoxShape.circle),
              )
            ],
          ),
          const SizedBox(height: 12),
          Text(
            value,
            style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w900, color: Color(0xFF1C1917)),
          ),
          const SizedBox(height: 2),
          Text(
            title,
            style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Colors.grey),
          ),
        ],
      ),
    );
  }

  Widget _buildMenuShortcutCard({
    required String title,
    required String subtitle,
    required IconData icon,
    required Color color,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: const Color(0xFFE4E2D7)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.08),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(icon, color: color, size: 24),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: const TextStyle(fontSize: 10, color: Colors.grey),
                ),
              ],
            )
          ],
        ),
      ),
    );
  }

  Widget _buildDetailRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6.0),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: Colors.grey, fontSize: 13)),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              value,
              textAlign: TextAlign.end,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(fontWeight: FontWeight.w600, color: Color(0xFF1C1917), fontSize: 13),
            ),
          ),
        ],
      ),
    );
  }
}
