import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../controllers/auth_controller.dart';
import '../../controllers/dashboard_controller.dart';
import '../../controllers/room_controller.dart';
import '../../controllers/invoice_controller.dart';
import '../../controllers/contract_controller.dart';
import '../../controllers/maintenance_controller.dart';
import '../auth/login_screen.dart'; // import GridPainter
import '../settings/settings_screen.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  int _currentIndex = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<DashboardController>().fetchDashboardStats();
    });
  }

  Future<void> _handleLogout() async {
    final success = await context.read<AuthController>().logout();
    if (success && mounted) {
      Navigator.pushReplacementNamed(context, '/login');
    }
  }

  Widget _buildNotificationsTab() {
    final maintenanceController = context.watch<MaintenanceController>();
    final requests = maintenanceController.requests;

    // Filter notification items:
    // 1. New maintenance requests (status == 1)
    // 2. Completed requests with feedback
    final List<Map<String, dynamic>> items = [];

    for (final req in requests) {
      if (req.status == 1) {
        items.add({
          'type': 'request',
          'title': 'Yêu cầu sửa chữa mới — Phòng ${req.roomNumber}',
          'subtitle': '${req.title}: ${req.description}',
          'date': req.createdAt,
          'icon': Icons.handyman_outlined,
          'color': Colors.orange,
        });
      }
      if (req.feedback != null && req.feedback!.isNotEmpty) {
        items.add({
          'type': 'feedback',
          'title': 'Phản hồi mới — Phòng ${req.roomNumber}',
          'subtitle': 'Khách ${req.tenantName}: "${req.feedback}"',
          'date': req.createdAt,
          'icon': Icons.rate_review_outlined,
          'color': Colors.green,
        });
      }
    }

    return Stack(
      children: [
        Positioned.fill(child: CustomPaint(painter: GridPainter())),
        items.isEmpty
            ? const Center(
                child: Text(
                  'Không có thông báo mới nào từ khách hàng.',
                  style: TextStyle(color: Colors.grey, fontSize: 14),
                ),
              )
            : ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: items.length,
                itemBuilder: (context, index) {
                  final item = items[index];
                  return Card(
                    color: Colors.white,
                    margin: const EdgeInsets.only(bottom: 12),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(16),
                      side: const BorderSide(color: Color(0xFFE4E2D7)),
                    ),
                    elevation: 0,
                    child: ListTile(
                      contentPadding: const EdgeInsets.all(16),
                      leading: Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: item['color'].withValues(alpha: 0.1),
                          shape: BoxShape.circle,
                        ),
                        child: Icon(item['icon'], color: item['color']),
                      ),
                      title: Text(
                        item['title'],
                        style: const TextStyle(
                          fontWeight: FontWeight.bold,
                          color: Color(0xFF1C1917),
                          fontSize: 14,
                        ),
                      ),
                      subtitle: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const SizedBox(height: 6),
                          Text(
                            item['subtitle'],
                            style: const TextStyle(
                              color: Color(0xFF44403C),
                              fontSize: 13,
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            'Thời gian: ${item['date']}',
                            style: const TextStyle(
                              color: Colors.grey,
                              fontSize: 11,
                            ),
                          ),
                        ],
                      ),
                      onTap: () {
                        Navigator.pushNamed(context, '/admin/maintenance');
                      },
                    ),
                  );
                },
              ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    final authController = context.watch<AuthController>();
    final dashboardController = context.watch<DashboardController>();
    final roomController = context.watch<RoomController>();
    final invoiceController = context.watch<InvoiceController>();
    final contractController = context.watch<ContractController>();
    final maintenanceController = context.watch<MaintenanceController>();
    
    final admin = authController.currentAdmin;

    // Calculate unhandled notifications count:
    // 1. Created requests (status == 1)
    // 2. Feedback comments
    int notificationCount = 0;
    for (final req in maintenanceController.requests) {
      if (req.status == 1) notificationCount++;
      if (req.feedback != null && req.feedback!.isNotEmpty) notificationCount++;
    }

    final List<Widget> tabs = [
      // Tab 0: Operations grid
      Stack(
        children: [
          // Background grid pattern
          Positioned.fill(
            child: CustomPaint(
              painter: GridPainter(),
            ),
          ),
          
          dashboardController.isLoading
              ? const Center(child: CircularProgressIndicator(color: Color(0xFF1C1917)))
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
                              )
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
                                  value: '${invoiceController.unpaidInvoicesCount}',
                                  icon: Icons.receipt_long_outlined,
                                  color: const Color(0xFFEAB308),
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: _buildStatCard(
                                  title: 'HĐ SẮP HẾT HẠN',
                                  value: '${contractController.expiringContractsCount}',
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
                              onTap: () => Navigator.pushNamed(context, '/admin/tenants'),
                            ),
                            _buildMenuCard(
                              title: 'Danh sách Phòng',
                              subtitle: 'Xem trạng thái phòng',
                              icon: Icons.meeting_room,
                              color: Colors.blueAccent,
                              onTap: () => Navigator.pushNamed(context, '/admin/rooms'),
                            ),
                            _buildMenuCard(
                              title: 'Ghi số Điện Nước',
                              subtitle: 'Chụp ảnh minh chứng',
                              icon: Icons.electric_meter,
                              color: Colors.amber,
                              onTap: () => Navigator.pushNamed(context, '/admin/meters'),
                            ),
                            _buildMenuCard(
                              title: 'Hóa đơn',
                              subtitle: 'Xác nhận & Nhắc nợ',
                              icon: Icons.receipt,
                              color: Colors.indigo,
                              onTap: () => Navigator.pushNamed(context, '/admin/invoices'),
                            ),
                            _buildMenuCard(
                              title: 'Sửa chữa sự cố',
                              subtitle: 'Tiếp nhận & Cập nhật',
                              icon: Icons.handyman,
                              color: Colors.deepOrange,
                              onTap: () => Navigator.pushNamed(context, '/admin/maintenance'),
                            ),
                            _buildMenuCard(
                              title: 'Hợp đồng thuê',
                              subtitle: 'Thêm, sửa, gia hạn',
                              icon: Icons.description,
                              color: Colors.purple,
                              onTap: () => Navigator.pushNamed(context, '/admin/contracts'),
                            ),
                            _buildMenuCard(
                              title: 'Thông báo',
                              subtitle: 'Phát thông báo đẩy',
                              icon: Icons.campaign,
                              color: Colors.pink,
                              onTap: () => Navigator.pushNamed(context, '/admin/notifications'),
                            ),
                          ],
                        ),
                        const SizedBox(height: 24),

                        // System User Card Info (matches screenshot bottom-left widget)
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
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
                                child: const Icon(Icons.person, color: Color(0xFF1C1917)),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      admin?.roleLabel ?? 'Quản lý tòa nhà',
                                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF1C1917)),
                                    ),
                                    Text(
                                      admin?.email ?? 'stayhub@example.com',
                                      style: const TextStyle(fontSize: 12, color: Colors.grey),
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
      // Tab 1: Settings Screen (embedded directly)
      const SettingsScreen(),
      // Tab 2: Customer Notifications
      _buildNotificationsTab(),
    ];

    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: _currentIndex == 1
          ? null
          : AppBar(
              title: Row(
                children: [
                  const Icon(
                    Icons.home_work_rounded,
                    color: Color(0xFFEAB308),
                    size: 24,
                  ),
                  const SizedBox(width: 8),
                  Text(
                    _currentIndex == 0 ? 'StayHub Command Center' : 'Thông báo từ khách',
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 18),
                  ),
                ],
              ),
              backgroundColor: const Color(0xFF1C1917),
              elevation: 0,
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
            icon: notificationCount > 0
                ? Badge(
                    label: Text('$notificationCount'),
                    backgroundColor: Colors.redAccent,
                    textColor: Colors.white,
                    child: const Icon(Icons.notifications_outlined),
                  )
                : const Icon(Icons.notifications_outlined),
            activeIcon: notificationCount > 0
                ? Badge(
                    label: Text('$notificationCount'),
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
          )
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
                decoration: BoxDecoration(
                  color: color,
                  shape: BoxShape.circle,
                ),
              )
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
            )
          ],
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
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFF1C1917),
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: const TextStyle(
                    fontSize: 10,
                    color: Colors.grey,
                  ),
                ),
              ],
            )
          ],
        ),
      ),
    );
  }
}
