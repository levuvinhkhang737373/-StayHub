import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../controllers/auth_controller.dart';
import '../../controllers/invoice_controller.dart';
import '../../controllers/maintenance_controller.dart';
import '../../controllers/notification_controller.dart';
import '../../controllers/chat_controller.dart';
import '../../services/websocket_service.dart';
import '../auth/login_screen.dart'; // import GridPainter
import '../settings/settings_screen.dart';
import 'tenant_chat_screen.dart';
import 'tenant_building_settings_screen.dart';

class TenantDashboardScreen extends StatefulWidget {
  const TenantDashboardScreen({super.key});

  @override
  State<TenantDashboardScreen> createState() => _TenantDashboardScreenState();
}

class _TenantDashboardScreenState extends State<TenantDashboardScreen> {
  int _currentIndex = 0;
  StreamSubscription? _chatNotificationSubscription;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<NotificationController>().fetchNotifications(isAdmin: false);
      context.read<MaintenanceController>().fetchRequests();

      // Lắng nghe thông báo thời gian thực từ WebSocket cho Tenant
      final authController = context.read<AuthController>();
      final tenantId = authController.currentTenant?.id;
      if (tenantId != null) {
        context.read<WebSocketService>().subscribeToTenantNotifications(tenantId, (notification) {
          if (mounted) {
            context.read<NotificationController>().fetchNotifications(isAdmin: false);
            // Đồng thời làm mới danh sách sửa chữa vì có thể có cập nhật trạng thái từ admin
            context.read<MaintenanceController>().fetchRequests();
            // Đồng thời làm mới hóa đơn
            context.read<InvoiceController>().fetchInvoices(isAdmin: false);
          }
        });
        context.read<WebSocketService>().subscribeToTenantChat(
          tenantId,
          onMessage: (payload) {
            if (!mounted) return;
            context.read<ChatController>().handleRealtimeMessage(payload);
          },
          onRead: (payload) {
            if (!mounted) return;
            context.read<ChatController>().handleRealtimeRead(payload);
          },
        );
        _chatNotificationSubscription ??= context.read<WebSocketService>().notificationsStream.listen((event) {
          if (!mounted) return;

          if (event['type'] == 'notification_sent') {
            final data = event['data'] as Map<String, dynamic>?;
            context.read<NotificationController>().fetchNotifications(isAdmin: false);

            if (_isTransferDateChangedNotification(data)) {
              final content = data?['content']?.toString() ?? 'Ngày chuyển phòng của bạn vừa được cập nhật.';
              _showRealtimeSnackBar(Icons.event_repeat_rounded, 'Lịch chuyển phòng: $content', const Color(0xFF92400E));
            }
            return;
          }

          if (event['type'] != 'chat_message_sent') return;

          final data = event['data'] as Map<String, dynamic>?;
          final message = data?['message'] as Map<String, dynamic>?;
          if (message == null || message['sender_role'] != 2) return;

          context.read<NotificationController>().fetchNotifications(isAdmin: false);
        });
      }
    });
  }

  bool _isTransferDateChangedNotification(Map<String, dynamic>? data) {
    if (data == null) return false;

    final title = data['title']?.toString().toLowerCase() ?? '';
    final content = data['content']?.toString().toLowerCase() ?? '';
    final text = '$title $content';

    return text.contains('ngày chuyển phòng') && (text.contains('thay đổi') || text.contains('đổi'));
  }

  void _showRealtimeSnackBar(IconData icon, String message, Color backgroundColor) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Row(
          children: [
            Icon(icon, color: Colors.white, size: 20),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                message,
                maxLines: 3,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13),
              ),
            ),
          ],
        ),
        duration: const Duration(seconds: 6),
        backgroundColor: backgroundColor,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      ),
    );
  }

  @override
  void dispose() {
    _chatNotificationSubscription?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final authController = context.watch<AuthController>();
    final tenant = authController.currentTenant;

    final invoiceController = context.watch<InvoiceController>();
    final maintenanceController = context.watch<MaintenanceController>();
    final notificationController = context.watch<NotificationController>();
    final unreadNotificationsCount = notificationController.unreadCount;

    // Get mock data bound to this tenant (Room 101)
    final roomNumber = tenant?.roomNumber ?? '101';
    final tenantInvoices = invoiceController.getInvoicesForRoom(roomNumber);
    final unpaidInvoices = tenantInvoices.where((i) => i.isUnpaid).toList();

    final tenantRequests = maintenanceController.getRequestsForRoom(roomNumber);
    final activeRequests = tenantRequests.where((r) => r.status != 4 && r.status != 5).toList();

    final List<Widget> tabs = [
      // Tab 0: Home / Ops Center
      Stack(
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
                  IntrinsicHeight(
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
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
                        title: 'Chỉ số Điện Nước',
                        subtitle: 'Theo dõi tiêu thụ',
                        icon: Icons.electric_meter_outlined,
                        color: Colors.blueAccent,
                        onTap: () => Navigator.pushNamed(context, '/tenant/utility'),
                      ),
                      _buildMenuShortcutCard(
                        title: 'Hợp đồng của tôi',
                        subtitle: 'Chi tiết & Thời hạn',
                        icon: Icons.assignment_outlined,
                        color: Colors.green,
                        onTap: () => Navigator.pushNamed(context, '/tenant/contract'),
                      ),
                      _buildMenuShortcutCard(
                        title: 'Quy định tòa nhà',
                        subtitle: 'Cài đặt & Hỗ trợ',
                        icon: Icons.domain_verification,
                        color: Colors.teal,
                        onTap: () {
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (context) => const TenantBuildingSettingsScreen(),
                            ),
                          );
                        },
                      ),
                      _buildMenuShortcutCard(
                        title: 'Chat quản lý',
                        subtitle: 'Hỗ trợ realtime',
                        icon: Icons.chat_bubble_rounded,
                        color: Colors.green,
                        onTap: () => Navigator.pushNamed(context, '/tenant/chat'),
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
      // Tab 1: Settings Screen
      const SettingsScreen(),
      // Tab 2: Notifications Feed
      const TenantNotificationScreen(),
    ];

    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: _currentIndex != 0
          ? null
          : AppBar(
              title: Row(
                children: const [
                  Icon(
                    Icons.home_work_rounded,
                    color: Color(0xFFEAB308),
                    size: 24,
                  ),
                  SizedBox(width: 8),
                  Text(
                    'StayHub Tenant Center',
                    style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 18),
                  ),
                ],
              ),
              backgroundColor: const Color(0xFF1C1917),
              automaticallyImplyLeading: false,
            ),
      body: IndexedStack(
        index: _currentIndex,
        children: tabs,
      ),
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _currentIndex,
        selectedItemColor: const Color(0xFFEAB308),
        unselectedItemColor: Colors.white.withOpacity(0.6),
        backgroundColor: const Color(0xFF1C1917),
        onTap: (index) {
          setState(() {
            _currentIndex = index;
          });
        },
        items: [
          const BottomNavigationBarItem(
            icon: Icon(Icons.dashboard_outlined),
            activeIcon: Icon(Icons.dashboard),
            label: 'Chức năng',
          ),
          const BottomNavigationBarItem(
            icon: Icon(Icons.person_outline),
            activeIcon: Icon(Icons.person),
            label: 'Tài khoản',
          ),
          BottomNavigationBarItem(
            icon: unreadNotificationsCount > 0
                ? Badge(
                    label: Text('$unreadNotificationsCount'),
                    backgroundColor: Colors.redAccent,
                    textColor: Colors.white,
                    child: const Icon(Icons.notifications_outlined),
                  )
                : const Icon(Icons.notifications_outlined),
            activeIcon: unreadNotificationsCount > 0
                ? Badge(
                    label: Text('$unreadNotificationsCount'),
                    backgroundColor: Colors.redAccent,
                    textColor: Colors.white,
                    child: const Icon(Icons.notifications),
                  )
                : const Icon(Icons.notifications),
            label: 'Thông báo',
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
