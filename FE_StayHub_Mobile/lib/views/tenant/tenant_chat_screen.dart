import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../controllers/notification_controller.dart';
import '../auth/login_screen.dart'; // import GridPainter

class TenantNotificationScreen extends StatefulWidget {
  const TenantNotificationScreen({super.key});

  @override
  State<TenantNotificationScreen> createState() => _TenantNotificationScreenState();
}

class _TenantNotificationScreenState extends State<TenantNotificationScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<NotificationController>().fetchNotifications(isAdmin: false);
    });
  }

  @override
  Widget build(BuildContext context) {
    final notificationController = context.watch<NotificationController>();
    final notifications = notificationController.notifications;

    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        title: Row(
          children: const [
            Icon(
              Icons.notifications_rounded,
              color: Color(0xFFEAB308),
              size: 24,
            ),
            SizedBox(width: 8),
            Text('Thông Báo', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
          ],
        ),
        backgroundColor: const Color(0xFF1C1917),
        automaticallyImplyLeading: false,
        actions: [
          if (notifications.any((n) => !n.isRead))
            TextButton.icon(
              onPressed: () => notificationController.markAllAsRead(),
              icon: const Icon(Icons.done_all, color: Color(0xFFEAB308), size: 18),
              label: const Text(
                'Đọc tất cả',
                style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold),
              ),
            ),
        ],
      ),
      body: Stack(
        children: [
          Positioned.fill(child: CustomPaint(painter: GridPainter())),
          notificationController.isLoading
              ? const Center(child: CircularProgressIndicator(color: Color(0xFF1C1917)))
              : notifications.isEmpty
                  ? const Center(
                      child: Text(
                        'Không có thông báo nào.',
                        style: TextStyle(color: Colors.grey, fontSize: 14),
                      ),
                    )
                  : RefreshIndicator(
                      onRefresh: () => notificationController.fetchNotifications(isAdmin: false),
                      color: const Color(0xFF1C1917),
                      child: ListView.builder(
                        padding: const EdgeInsets.all(16),
                        itemCount: notifications.length,
                        itemBuilder: (context, index) {
                          final notif = notifications[index];
                          return Card(
                            color: notif.isRead ? Colors.white : const Color(0xFFFFFBEB), // Highlight unread
                            margin: const EdgeInsets.only(bottom: 16),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(16),
                              side: BorderSide(
                                color: notif.isRead ? const Color(0xFFE4E2D7) : const Color(0xFFFDE68A),
                                width: notif.isRead ? 1.0 : 1.5,
                              ),
                            ),
                            elevation: 0,
                            child: InkWell(
                              onTap: () {
                                if (!notif.isRead) {
                                  notificationController.markAsRead(notif.id);
                                }
                              },
                              borderRadius: BorderRadius.circular(16),
                              child: Padding(
                                padding: const EdgeInsets.all(16.0),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Row(
                                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                      children: [
                                        Row(
                                          children: [
                                            Icon(
                                              notif.isRead ? Icons.campaign_outlined : Icons.campaign_rounded,
                                              color: notif.isRead ? Colors.grey : const Color(0xFFEAB308),
                                              size: 24,
                                            ),
                                            const SizedBox(width: 8),
                                            if (!notif.isRead)
                                              Container(
                                                width: 8,
                                                height: 8,
                                                decoration: const BoxDecoration(
                                                  color: Colors.redAccent,
                                                  shape: BoxShape.circle,
                                                ),
                                              ),
                                          ],
                                        ),
                                        Text(
                                          notif.createdAt,
                                          style: const TextStyle(fontSize: 10, color: Colors.grey),
                                        ),
                                      ],
                                    ),
                                    const SizedBox(height: 8),
                                    Text(
                                      notif.title,
                                      style: TextStyle(
                                        fontWeight: notif.isRead ? FontWeight.bold : FontWeight.w900,
                                        fontSize: 15,
                                        color: const Color(0xFF1C1917),
                                      ),
                                    ),
                                    const SizedBox(height: 8),
                                    Text(
                                      notif.content,
                                      style: const TextStyle(fontSize: 13, color: Color(0xFF44403C), height: 1.4),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          );
                        },
                      ),
                    ),
        ],
      ),
    );
  }
}
