import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../controllers/auth_controller.dart';
import '../../controllers/dashboard_controller.dart';
import '../../controllers/room_controller.dart';
import '../../controllers/invoice_controller.dart';
import '../../controllers/contract_controller.dart';
import '../../controllers/maintenance_controller.dart';
import '../../controllers/notification_controller.dart';
import '../../controllers/chat_controller.dart';
import '../../services/websocket_service.dart';
import '../auth/login_screen.dart'; // import GridPainter
import '../settings/settings_screen.dart';
import '../admin/admin_realtime_chat_screen.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  int _currentIndex = 0;
  StreamSubscription? _debugSubscription;
  StreamSubscription? _wsAdminEventsSubscription;


  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<DashboardController>().fetchDashboardStats();
      context.read<MaintenanceController>().fetchAdminRequests();
      context.read<ContractController>().fetchContracts('admin');
      context.read<NotificationController>().fetchNotifications(isAdmin: true);
      context.read<RoomController>().fetchRooms();
      context.read<ChatController>().fetchAdminTenantConversations();
      context.read<ChatController>().fetchAdminDirectConversations();

      final wsService = context.read<WebSocketService>();
      final currentAdmin = context.read<AuthController>().currentAdmin;
      final adminId = currentAdmin?.id;
      if (adminId != null) {
        wsService.subscribeToAdminChat(
          adminId,
          onMessage: (payload) {
            if (!mounted) return;
            context.read<ChatController>().handleRealtimeMessage(
              payload,
              currentAdminId: currentAdmin?.id,
              currentAdminRole: currentAdmin?.role,
            );
          },
          onRead: (payload) {
            if (!mounted) return;
            context.read<ChatController>().handleRealtimeRead(
              payload,
              currentAdminId: currentAdmin?.id,
              currentAdminRole: currentAdmin?.role,
            );
          },
        );

        _subscribeAdminBuildingContractExpirations(wsService);
      }

      // Lắng nghe thông điệp debug để hiện SnackBar lên màn hình (chỉ hiển thị khi có lỗi)
      _debugSubscription = wsService.debugStream.listen((logMessage) {
        if (mounted && _isUserVisibleRealtimeError(logMessage)) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(logMessage),
              duration: const Duration(seconds: 4),
              backgroundColor: Colors.redAccent,
              behavior: SnackBarBehavior.floating,
            ),
          );
        }
      });

      // Lắng nghe yêu cầu sửa chữa mới thời gian thực để cập nhật giao diện
      wsService.subscribeToAdminMaintenance(() {
        if (mounted) {
          context.read<MaintenanceController>().fetchAdminRequests();
          context.read<NotificationController>().fetchNotifications(
            isAdmin: true,
          );
        }
      });

      // Lắng nghe sự kiện đóng tiền cọc thành công thời gian thực
      _wsAdminEventsSubscription = wsService.notificationsStream.listen((
        event,
      ) {
        if (event['type'] == 'admin_contract_deposit_paid') {
          final data = event['data'] as Map<String, dynamic>?;
          final contract = data != null
              ? data['contract'] as Map<String, dynamic>?
              : null;

          if (contract != null && mounted) {
            // Làm mới các chỉ số vận hành và hợp đồng của admin
            context.read<DashboardController>().fetchDashboardStats();
            context.read<ContractController>().fetchContracts('admin');
            context.read<NotificationController>().fetchNotifications(
              isAdmin: true,
            );

            final contractCode = contract['contract_code'] ?? '';
            final roomNumber = contract['room_number'] ?? '';
            final amountVal = contract['deposit_amount'] != null
                ? double.tryParse(contract['deposit_amount'].toString()) ?? 0.0
                : 0.0;

            final amountFormatted = amountVal > 0
                ? '${amountVal.toStringAsFixed(0).replaceAllMapped(RegExp(r"(\d{1,3})(?=(\d{3})+(?!\d))"), (Match m) => "${m[1]}.")}đ'
                : 'tiền cọc';

            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Row(
                  children: [
                    const Icon(
                      Icons.check_circle_rounded,
                      color: Colors.white,
                      size: 20,
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        'Phòng $roomNumber đã đóng cọc thành công $amountFormatted (HĐ: $contractCode)',
                        style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.bold,
                          fontSize: 13,
                        ),
                      ),
                    ),
                  ],
                ),
                duration: const Duration(seconds: 5),
                backgroundColor: const Color(0xFF16A34A), // Premium green color
                behavior: SnackBarBehavior.floating,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                ),
              ),
            );
          }
        } else if (event['type'] == 'admin_notification_sent') {
          if (mounted) {
            final data = event['data'] as Map<String, dynamic>?;
            context.read<NotificationController>().fetchNotifications(
              isAdmin: true,
            );
            context.read<DashboardController>().fetchDashboardStats();

            if (_isTransferDateChangedNotification(data)) {
              final content =
                  data?['content']?.toString() ??
                  'Một lịch chuyển phòng vừa đổi ngày.';
              _showAdminRealtimeSnackBar(
                Icons.event_repeat_rounded,
                content,
                const Color(0xFF92400E),
              );
            }
          }
        } else if (event['type'] == 'contract_expired') {
          if (mounted) {
            final data = event['data'] as Map<String, dynamic>?;
            context.read<ContractController>().fetchContracts('admin');
            context.read<NotificationController>().fetchNotifications(
              isAdmin: true,
            );

            final contractCode = data?['contract_code']?.toString() ?? '';
            final roomNumber = data?['room_number']?.toString() ?? '?';
            _showAdminRealtimeSnackBar(
              Icons.warning_amber_rounded,
              'Hợp đồng $contractCode phòng $roomNumber đã hết hạn.',
              const Color(0xFFB45309),
            );
          }
        } else if (event['type'] == 'chat_message_sent') {
          final data = event['data'] as Map<String, dynamic>?;
          final message = data?['message'] as Map<String, dynamic>?;
          final conversation = data?['conversation'] as Map<String, dynamic>?;
          if (message != null &&
              conversation != null &&
              mounted &&
              message['sender_role'] == 1) {
            context.read<NotificationController>().fetchNotifications(
              isAdmin: true,
            );
          }
        }
      });
    });
  }

  void _subscribeAdminBuildingContractExpirations(WebSocketService wsService) {
    final admin = context.read<AuthController>().currentAdmin;
    if (admin == null) return;

    wsService.subscribeToAdminBuildingContractExpirations(
      admin.managedBuildingIds,
      onContractExpired: (contract) {
        if (!mounted) return;
        context.read<ContractController>().fetchContracts('admin');
        context.read<NotificationController>().fetchNotifications(
          isAdmin: true,
        );
      },
    );
  }

  bool _isTransferDateChangedNotification(Map<String, dynamic>? data) {
    if (data == null) return false;

    final title = data['title']?.toString().toLowerCase() ?? '';
    final content = data['content']?.toString().toLowerCase() ?? '';
    final text = '$title $content';

    return text.contains('chuyển phòng') &&
        text.contains('đổi') &&
        text.contains('ngày');
  }

  bool _isUserVisibleRealtimeError(String logMessage) {
    if (!logMessage.contains('Lỗi')) return false;

    final normalized = logMessage.toLowerCase();
    const technicalRealtimeKeywords = [
      'websocket',
      'kênh',
      'channel',
      'auth',
      'xác thực',
      'broadcasting/auth',
      'dioexception',
      'socket',
    ];

    return !technicalRealtimeKeywords.any(normalized.contains);
  }

  void _showAdminRealtimeSnackBar(
    IconData icon,
    String message,
    Color backgroundColor,
  ) {
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
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.bold,
                  fontSize: 13,
                ),
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
    _debugSubscription?.cancel();
    _wsAdminEventsSubscription?.cancel();
    super.dispose();
  }



  @override
  Widget build(BuildContext context) {
    final authController = context.watch<AuthController>();
    final dashboardController = context.watch<DashboardController>();
    final roomController = context.watch<RoomController>();
    final invoiceController = context.watch<InvoiceController>();
    final contractController = context.watch<ContractController>();
    final notificationController = context.watch<NotificationController>();

    final admin = authController.currentAdmin;



    int notificationCount = notificationController.unreadCount;

    final List<Widget> tabs = [
      // Tab 0: Operations grid
      Stack(
        children: [
          // Background grid pattern
          Positioned.fill(child: CustomPaint(painter: GridPainter())),

          dashboardController.isLoading
              ? const Center(
                  child: CircularProgressIndicator(color: Color(0xFF1C1917)),
                )
              : RefreshIndicator(
                  onRefresh: () => dashboardController.fetchDashboardStats(),
                  color: const Color(0xFF1C1917),
                  child: SingleChildScrollView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.all(16.0),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        // Command Header Card (matches screenshot banner)
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
                              ),
                            ],
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'STAYHUB OPS — FACILITIES COMMAND',
                                style: TextStyle(
                                  fontSize: 10,
                                  fontWeight: FontWeight.bold,
                                  color: Color(0xFFEAB308),
                                  letterSpacing: 1.5,
                                ),
                              ),
                              const SizedBox(height: 8),
                              Text(
                                admin?.fullName ?? 'Bảng Điều Khiển',
                                style: const TextStyle(
                                  fontSize: 28,
                                  fontWeight: FontWeight.bold,
                                  color: Colors.white,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                'Hệ thống quản lý cơ sở vật chất và vận hành StayHub.',
                                style: TextStyle(
                                  fontSize: 13,
                                  color: Colors.white.withValues(alpha: 0.7),
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 24),

                        // Stats counters (Horizontal strip like screenshot A)
                        const Text(
                          'CHỈ SỐ VẬN HÀNH',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.bold,
                            color: Color(0xFF78716C),
                            letterSpacing: 1.0,
                          ),
                        ),
                        const SizedBox(height: 12),

                        IntrinsicHeight(
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              Expanded(
                                child: _buildStatCard(
                                  title: 'PHÒNG TRỐNG',
                                  value: '${roomController.emptyRoomsCount}',
                                  icon: Icons.meeting_room_outlined,
                                  color: Colors.green,
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: _buildStatCard(
                                  title: 'HÓA ĐƠN NỢ',
                                  value:
                                      '${invoiceController.unpaidInvoicesCount}',
                                  icon: Icons.receipt_long_outlined,
                                  color: const Color(0xFFEAB308),
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: _buildStatCard(
                                  title: 'HĐ SẮP HẾT HẠN',
                                  value:
                                      '${contractController.expiringContractsCount}',
                                  icon: Icons.gavel_outlined,
                                  color: Colors.redAccent,
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 24),

                        // Main Menu Action Cards
                        const Text(
                          'QUẢN LÝ VẬN HÀNH',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.bold,
                            color: Color(0xFF78716C),
                            letterSpacing: 1.0,
                          ),
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
                            _buildMenuCard(
                              title: 'Khách thuê',
                              subtitle: 'Xem & Tra cứu danh sách',
                              icon: Icons.people,
                              color: Colors.teal,
                              onTap: () => Navigator.pushNamed(
                                context,
                                '/admin/tenants',
                              ),
                            ),
                            _buildMenuCard(
                              title: 'Danh sách Phòng',
                              subtitle: 'Xem trạng thái phòng',
                              icon: Icons.meeting_room,
                              color: Colors.blueAccent,
                              onTap: () =>
                                  Navigator.pushNamed(context, '/admin/rooms'),
                            ),
                            _buildMenuCard(
                              title: 'Ghi số Điện Nước',
                              subtitle: 'Chụp ảnh minh chứng',
                              icon: Icons.electric_meter,
                              color: Colors.amber,
                              onTap: () =>
                                  Navigator.pushNamed(context, '/admin/meters'),
                            ),
                            _buildMenuCard(
                              title: 'Hóa đơn',
                              subtitle: 'Xác nhận & Nhắc nợ',
                              icon: Icons.receipt,
                              color: Colors.indigo,
                              onTap: () => Navigator.pushNamed(
                                context,
                                '/admin/invoices',
                              ),
                            ),
                            _buildMenuCard(
                              title: 'Sửa chữa sự cố',
                              subtitle: 'Tiếp nhận & Cập nhật',
                              icon: Icons.handyman,
                              color: Colors.deepOrange,
                              onTap: () => Navigator.pushNamed(
                                context,
                                '/admin/maintenance',
                              ),
                            ),
                            _buildMenuCard(
                              title: 'Hợp đồng thuê',
                              subtitle: 'Thêm, sửa, gia hạn',
                              icon: Icons.description,
                              color: Colors.purple,
                              onTap: () => Navigator.pushNamed(
                                context,
                                '/admin/contracts',
                              ),
                            ),
                            _buildMenuCard(
                              title: 'Thông báo',
                              subtitle: 'Phát thông báo đẩy',
                              icon: Icons.campaign,
                              color: Colors.pink,
                              onTap: () => Navigator.pushNamed(
                                context,
                                '/admin/notifications',
                              ),
                            ),
                            _buildMenuCard(
                              title: 'Chuyển phòng',
                              subtitle: 'Lên lịch chuyển cư dân',
                              icon: Icons.swap_horiz_rounded,
                              color: Colors.brown,
                              onTap: () => Navigator.pushNamed(
                                context,
                                '/admin/room-transfer',
                              ),
                            ),

                          ],
                        ),
                        const SizedBox(height: 24),

                        // System User Card Info (matches screenshot bottom-left widget)
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 16,
                            vertical: 12,
                          ),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(color: const Color(0xFFE4E2D7)),
                          ),
                          child: Row(
                            children: [
                              Container(
                                padding: const EdgeInsets.all(8),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFF7F6F0),
                                  shape: BoxShape.circle,
                                ),
                                child: const Icon(
                                  Icons.person,
                                  color: Color(0xFF1C1917),
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      admin?.roleLabel ?? 'Quản lý tòa nhà',
                                      style: const TextStyle(
                                        fontWeight: FontWeight.bold,
                                        fontSize: 14,
                                        color: Color(0xFF1C1917),
                                      ),
                                    ),
                                    Text(
                                      admin?.email ?? 'stayhub@example.com',
                                      style: const TextStyle(
                                        fontSize: 12,
                                        color: Colors.grey,
                                      ),
                                    ),
                                  ],
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
      // Tab 1: Realtime Chat
      const AdminChatScreen(isEmbedded: true),
      // Tab 2: Settings Screen (embedded directly)
      const SettingsScreen(),
    ];

    final chatController = context.watch<ChatController>();
    final int unreadChatCount = chatController.tenantConversations.fold(0, (sum, item) => sum + item.unreadCountForAdmin(admin?.id)) +
        chatController.directConversations.fold(0, (sum, item) => sum + item.unreadCountForAdmin(admin?.id));

    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: _currentIndex != 0
          ? null
          : AppBar(
              actions: [
                Padding(
                  padding: const EdgeInsets.only(right: 16),
                  child: notificationCount > 0
                      ? Badge(
                          label: Text('$notificationCount'),
                          backgroundColor: Colors.redAccent,
                          textColor: Colors.white,
                          child: IconButton(
                            icon: const Icon(Icons.notifications_none_rounded, color: Colors.white),
                            onPressed: () => Navigator.pushNamed(context, '/admin/customer-notifications'),
                          ),
                        )
                      : IconButton(
                          icon: const Icon(Icons.notifications_none_rounded, color: Colors.white),
                          onPressed: () => Navigator.pushNamed(context, '/admin/customer-notifications'),
                        ),
                ),
              ],
              title: Row(
                children: [
                  const Icon(
                    Icons.home_work_rounded,
                    color: Color(0xFFEAB308),
                    size: 24,
                  ),
                  const SizedBox(width: 8),
                  const Text(
                    'StayHub Command Center',
                    style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.bold,
                      fontSize: 18,
                    ),
                  ),
                ],
              ),
              backgroundColor: const Color(0xFF1C1917),
              elevation: 0,
              automaticallyImplyLeading: false,
            ),
      body: IndexedStack(index: _currentIndex, children: tabs),
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _currentIndex,
        selectedItemColor: const Color(0xFFEAB308),
        unselectedItemColor: Colors.white.withValues(alpha: 0.6),
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
          BottomNavigationBarItem(
            icon: unreadChatCount > 0
                ? Badge(
                    label: Text('$unreadChatCount'),
                    backgroundColor: Colors.redAccent,
                    textColor: Colors.white,
                    child: const Icon(Icons.chat_bubble_outline_rounded),
                  )
                : const Icon(Icons.chat_bubble_outline_rounded),
            activeIcon: unreadChatCount > 0
                ? Badge(
                    label: Text('$unreadChatCount'),
                    backgroundColor: Colors.redAccent,
                    textColor: Colors.white,
                    child: const Icon(Icons.chat_bubble_rounded),
                  )
                : const Icon(Icons.chat_bubble_rounded),
            label: 'Đoạn chat',
          ),
          const BottomNavigationBarItem(
            icon: Icon(Icons.person_outline),
            activeIcon: Icon(Icons.person),
            label: 'Tài khoản',
          ),
        ],
      ),
    );
  }

  Widget _buildStatCard({
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
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 4,
            offset: const Offset(0, 2),
          ),
        ],
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
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            value,
            style: const TextStyle(
              fontSize: 24,
              fontWeight: FontWeight.w900,
              color: Color(0xFF1C1917),
            ),
          ),
          const SizedBox(height: 2),
          Text(
            title,
            style: const TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.bold,
              color: Colors.grey,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildMenuCard({
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
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.02),
              blurRadius: 4,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.08),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(icon, color: color, size: 48),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFF1C1917),
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: const TextStyle(fontSize: 10, color: Colors.grey),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
