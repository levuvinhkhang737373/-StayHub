import 'package:flutter/material.dart';
import '../auth/login_screen.dart'; // import GridPainter

class TenantNotificationScreen extends StatefulWidget {
  const TenantNotificationScreen({super.key});

  @override
  State<TenantNotificationScreen> createState() => _TenantNotificationScreenState();
}

class _TenantNotificationScreenState extends State<TenantNotificationScreen> {
  // Mock Notifications list
  final List<Map<String, String>> _mockNotifications = [
    {
      'title': 'Thông báo đóng tiền phòng tháng 5/2026',
      'body': 'Hóa đơn tiền phòng tháng 5/2026 đã được cập nhật trên hệ thống. Hạn thanh toán đến hết ngày 05/06/2026. Quý khách vui lòng thanh toán đúng hạn.',
      'time': '28/05/2026 08:00',
    },
    {
      'title': 'Bảo trì hệ thống máy bơm nước tòa nhà Q1',
      'body': 'Kỹ thuật viên sẽ tiến hành bảo trì định kỳ hệ thống máy bơm nước từ 13h00 - 15h00 ngày 30/05/2026. Trong thời gian này, nguồn nước có thể bị ngắt quãng. Mong quý khách thông cảm.',
      'time': '27/05/2026 15:30',
    },
    {
      'title': 'Nhắc nhở phân loại rác thải tại hành lang',
      'body': 'Để đảm bảo vệ sinh chung, vui lòng phân loại rác hữu cơ và vô cơ trước khi bỏ vào thùng rác chung tại khu vực hành lang. Xin cảm ơn sự hợp tác của quý khách.',
      'time': '20/05/2026 10:00',
    }
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        title: Row(
          children: const [
            Icon(
              Icons.home_work_rounded,
              color: Color(0xFFEAB308),
              size: 24,
            ),
            SizedBox(width: 8),
            Text('Thông Báo', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
          ],
        ),
        backgroundColor: const Color(0xFF1C1917),
        automaticallyImplyLeading: false,
      ),
      body: Stack(
        children: [
          Positioned.fill(child: CustomPaint(painter: GridPainter())),
          ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: _mockNotifications.length,
            itemBuilder: (context, index) {
              final notif = _mockNotifications[index];
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
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Icon(Icons.campaign_rounded, color: Color(0xFFEAB308), size: 24),
                          Text(
                            notif['time']!,
                            style: const TextStyle(fontSize: 10, color: Colors.grey),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Text(
                        notif['title']!,
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Color(0xFF1C1917)),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        notif['body']!,
                        style: const TextStyle(fontSize: 13, color: Colors.grey, height: 1.4),
                      ),
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
}
