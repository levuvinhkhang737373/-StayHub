import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../controllers/notification_controller.dart';
import '../auth/login_screen.dart'; // Import GridPainter

class AdminCustomerNotificationsScreen extends StatefulWidget {
  const AdminCustomerNotificationsScreen({super.key});

  @override
  State<AdminCustomerNotificationsScreen> createState() => _AdminCustomerNotificationsScreenState();
}

class _AdminCustomerNotificationsScreenState extends State<AdminCustomerNotificationsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<NotificationController>().fetchNotifications(isAdmin: true);
    });
  }

  @override
  Widget build(BuildContext context) {
    final notificationController = context.watch<NotificationController>();
    final notifications = notificationController.notifications;

    // Filter and format notifications
    final List<Map<String, dynamic>> items = [];
    for (final notif in notifications) {
      final key = 'db_notif_${notif.id}';
      final isRead = notif.isRead;

      IconData icon = Icons.notifications_none_rounded;
      Color color = Colors.blue;

      if (notif.notificationType == 1) {
        icon = Icons.handyman_outlined;
        color = Colors.deepOrange;
      } else if (notif.notificationType == 2) {
        icon = Icons.receipt_long_outlined;
        color = Colors.indigo;
      } else if (notif.notificationType == 3) {
        icon = Icons.campaign_rounded;
        color = const Color(0xFFEAB308);
      } else if (notif.notificationType == 4) {
        icon = Icons.warning_amber_rounded;
        color = Colors.redAccent;
      } else if (notif.notificationType == 6) {
        icon = Icons.chat_rounded;
        color = Colors.teal;
      }

      // Format date
      String displayDate = notif.createdAt;
      try {
        final parsed = DateTime.parse(notif.createdAt).toLocal();
        displayDate =
            '${parsed.hour.toString().padLeft(2, "0")}:${parsed.minute.toString().padLeft(2, "0")} ${parsed.day.toString().padLeft(2, "0")}/${parsed.month.toString().padLeft(2, "0")}/${parsed.year}';
      } catch (_) {}

      items.add({
        'key': key,
        'id': notif.id,
        'type': notif.notificationType == 1
            ? 'request'
            : notif.notificationType == 2
            ? 'invoice'
            : notif.notificationType == 6
            ? 'chat'
            : 'system',
        'title': notif.title,
        'subtitle': notif.content,
        'date': displayDate,
        'icon': icon,
        'color': color,
        'isRead': isRead,
        'tenant_id': notif.tenantId,
      });
    }

    final hasUnread = items.any((item) => !item['isRead']);

    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        leading: const BackButton(color: Colors.white),
        title: const Text(
          'Thông báo từ khách',
          style: TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.bold,
            fontSize: 18,
          ),
        ),
        actions: [
          if (hasUnread)
            TextButton.icon(
              onPressed: () async {
                await context.read<NotificationController>().markAllAsRead(isAdmin: true);
                if (mounted) {
                  // Reload notifications list
                  context.read<NotificationController>().fetchNotifications(isAdmin: true);
                }
              },
              icon: const Icon(
                Icons.done_all,
                color: Color(0xFFEAB308),
                size: 18,
              ),
              label: const Text(
                'Đọc tất cả',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 12,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
        ],
        backgroundColor: const Color(0xFF1C1917),
        elevation: 0,
      ),
      body: RefreshIndicator(
        color: const Color(0xFF1C1917),
        onRefresh: () => context
            .read<NotificationController>()
            .fetchNotifications(isAdmin: true),
        child: Stack(
          children: [
            Positioned.fill(child: CustomPaint(painter: GridPainter())),
            notificationController.isLoading && items.isEmpty
                ? const Center(
                    child: CircularProgressIndicator(color: Color(0xFF1C1917)),
                  )
                : items.isEmpty
                    ? const Center(
                        child: SingleChildScrollView(
                          physics: AlwaysScrollableScrollPhysics(),
                          child: SizedBox(
                            height: 300,
                            child: Center(
                              child: Text(
                                'Không có thông báo mới nào từ khách hàng.',
                                style: TextStyle(color: Colors.grey, fontSize: 14),
                              ),
                            ),
                          ),
                        ),
                      )
                    : ListView.builder(
                        padding: const EdgeInsets.all(16),
                        physics: const AlwaysScrollableScrollPhysics(),
                        itemCount: items.length,
                        itemBuilder: (context, index) {
                          final item = items[index];
                          final isRead = item['isRead'] as bool;
                          return Card(
                            color: isRead
                                ? Colors.white
                                : const Color(0xFFFFFBEB), // Highlight unread
                            margin: const EdgeInsets.only(bottom: 12),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(16),
                              side: BorderSide(
                                color: isRead
                                    ? const Color(0xFFE4E2D7)
                                    : const Color(0xFFFDE68A),
                                width: isRead ? 1.0 : 1.5,
                              ),
                            ),
                            elevation: 0,
                            child: InkWell(
                              onTap: () async {
                                if (!isRead) {
                                  await context
                                      .read<NotificationController>()
                                      .markAsRead(item['id'] as int, isAdmin: true);
                                }
                                if (!mounted) return;
                                if (item['type'] == 'request') {
                                  Navigator.pushNamed(context, '/admin/maintenance');
                                } else if (item['type'] == 'invoice') {
                                  Navigator.pushNamed(context, '/admin/invoices');
                                } else if (item['type'] == 'chat') {
                                  Navigator.pushNamed(
                                    context,
                                    '/admin/chat',
                                    arguments: item['tenant_id'],
                                  );
                                } else {
                                  Navigator.pushNamed(context, '/admin/contracts');
                                }
                              },
                              borderRadius: BorderRadius.circular(16),
                              child: Padding(
                                padding: const EdgeInsets.all(16.0),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Row(
                                      mainAxisAlignment:
                                          MainAxisAlignment.spaceBetween,
                                      children: [
                                        Row(
                                          children: [
                                            Icon(
                                              isRead
                                                  ? Icons.campaign_outlined
                                                  : Icons.campaign_rounded,
                                              color: isRead
                                                  ? Colors.grey
                                                  : const Color(0xFFEAB308),
                                              size: 24,
                                            ),
                                            const SizedBox(width: 8),
                                            if (!isRead)
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
                                          item['date'],
                                          style: const TextStyle(
                                            fontSize: 10,
                                            color: Colors.grey,
                                          ),
                                        ),
                                      ],
                                    ),
                                    const SizedBox(height: 8),
                                    Text(
                                      item['title'],
                                      style: TextStyle(
                                        fontWeight: isRead
                                            ? FontWeight.bold
                                            : FontWeight.w900,
                                        fontSize: 15,
                                        color: const Color(0xFF1C1917),
                                      ),
                                    ),
                                    const SizedBox(height: 8),
                                    Text(
                                      item['subtitle'],
                                      style: const TextStyle(
                                        fontSize: 13,
                                        color: Color(0xFF44403C),
                                        height: 1.4,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          );
                        },
                      ),
          ],
        ),
      ),
    );
  }
}
