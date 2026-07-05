import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../controllers/notification_controller.dart';
import '../auth/login_screen.dart'; // import GridPainter

class TenantNotificationScreen extends StatefulWidget {
  const TenantNotificationScreen({super.key});

  @override
  State<TenantNotificationScreen> createState() =>
      _TenantNotificationScreenState();
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
            Text(
              'Thông Báo',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
            ),
          ],
        ),
        backgroundColor: const Color(0xFF1C1917),
        automaticallyImplyLeading: false,
        actions: [
          if (notifications.any((n) => !n.isRead))
            TextButton.icon(
              onPressed: () => notificationController.markAllAsRead(),
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
      ),
      body: Stack(
        children: [
          Positioned.fill(child: CustomPaint(painter: GridPainter())),
          notificationController.isLoading
              ? const Center(
                  child: CircularProgressIndicator(color: Color(0xFF1C1917)),
                )
              : notifications.isEmpty
              ? const Center(
                  child: Text(
                    'Không có thông báo nào.',
                    style: TextStyle(color: Colors.grey, fontSize: 14),
                  ),
                )
              : RefreshIndicator(
                  onRefresh: () =>
                      notificationController.fetchNotifications(isAdmin: false),
                  color: const Color(0xFF1C1917),
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: notifications.length,
                    itemBuilder: (context, index) {
                      final notif = notifications[index];
                      
                      // Format chat notifications if content is empty or contains room number prefix
                      String displayContent = notif.content;
                      if (notif.notificationType == 6) {
                        final managerName = notif.creatorName ?? 'Quản lý';
                        final regExp = RegExp(r'^Phòng\s+[^:]+:\s*');
                        if (regExp.hasMatch(displayContent)) {
                          final msgBody = displayContent.replaceFirst(regExp, '');
                          displayContent = '$managerName: ${msgBody.trim().isEmpty ? '[Hình ảnh]' : msgBody}';
                        } else {
                          if (!displayContent.startsWith(managerName)) {
                            displayContent = '$managerName: $displayContent';
                          }
                        }
                      }

                      // Format display date
                      String displayDate = notif.createdAt;
                      try {
                        final parsed = DateTime.parse(notif.createdAt).toLocal();
                        displayDate = '${parsed.hour.toString().padLeft(2, "0")}:${parsed.minute.toString().padLeft(2, "0")} ${parsed.day.toString().padLeft(2, "0")}/${parsed.month.toString().padLeft(2, "0")}/${parsed.year}';
                      } catch (_) {}

                      return Card(
                        color: notif.isRead
                            ? Colors.white
                            : const Color(0xFFFFFBEB), // Highlight unread
                        margin: const EdgeInsets.only(bottom: 16),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(16),
                          side: BorderSide(
                            color: notif.isRead
                                ? const Color(0xFFE4E2D7)
                                : const Color(0xFFFDE68A),
                            width: notif.isRead ? 1.0 : 1.5,
                          ),
                        ),
                        elevation: 0,
                        child: InkWell(
                          onTap: () {
                            if (!notif.isRead) {
                              notificationController.markAsRead(notif.id);
                            }
                            
                            // Check if utility notification (electricity/water price or usage)
                            final isUtility = notif.title.toLowerCase().contains('điện') ||
                                              notif.title.toLowerCase().contains('nước') ||
                                              notif.title.toLowerCase().contains('đơn giá') ||
                                              notif.content.toLowerCase().contains('điện') ||
                                              notif.content.toLowerCase().contains('nước') ||
                                              notif.content.toLowerCase().contains('đơn giá');
                                              
                            if (isUtility) {
                              Navigator.pushNamed(context, '/tenant/utility');
                            } else if (notif.notificationType == 1) {
                              Navigator.pushNamed(context, '/tenant/maintenance');
                            } else if (notif.notificationType == 2) {
                              Navigator.pushNamed(context, '/tenant/invoices');
                            } else if (notif.notificationType == 3) {
                              Navigator.pushNamed(context, '/tenant/contract');
                            } else if (notif.notificationType == 6) {
                              Navigator.pushNamed(context, '/tenant/chat');
                            } else {
                              // Show dialog with title and message content for warning/other
                              showDialog(
                                context: context,
                                builder: (context) => AlertDialog(
                                  backgroundColor: Colors.white,
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                                  title: Text(
                                    notif.title,
                                    style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                                  ),
                                  content: Text(
                                    displayContent,
                                    style: const TextStyle(color: Color(0xFF44403C), height: 1.4),
                                  ),
                                  actions: [
                                    TextButton(
                                      onPressed: () => Navigator.pop(context),
                                      child: const Text('Đóng', style: TextStyle(color: Color(0xFF8B5E34), fontWeight: FontWeight.bold)),
                                    ),
                                  ],
                                ),
                              );
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
                                        Builder(
                                          builder: (context) {
                                            IconData iconData;
                                            Color iconColor;
                                            
                                            // Check if utility notification
                                            final isUtility = notif.title.toLowerCase().contains('điện') ||
                                                              notif.title.toLowerCase().contains('nước') ||
                                                              notif.title.toLowerCase().contains('đơn giá') ||
                                                              notif.content.toLowerCase().contains('điện') ||
                                                              notif.content.toLowerCase().contains('nước') ||
                                                              notif.content.toLowerCase().contains('đơn giá');

                                            if (isUtility) {
                                              iconData = notif.isRead ? Icons.electric_meter_outlined : Icons.electric_meter_rounded;
                                              iconColor = Colors.amber.shade800;
                                            } else if (notif.notificationType == 1) {
                                              iconData = notif.isRead ? Icons.build_outlined : Icons.build_rounded;
                                              iconColor = Colors.orange.shade700;
                                            } else if (notif.notificationType == 2) {
                                              iconData = notif.isRead ? Icons.receipt_long_outlined : Icons.receipt_long_rounded;
                                              iconColor = Colors.blue.shade700;
                                            } else if (notif.notificationType == 3) {
                                              iconData = notif.isRead ? Icons.assignment_outlined : Icons.assignment_rounded;
                                              iconColor = Colors.green.shade700;
                                            } else if (notif.notificationType == 4) {
                                              iconData = notif.isRead ? Icons.warning_amber_rounded : Icons.warning_rounded;
                                              iconColor = Colors.red.shade700;
                                            } else if (notif.notificationType == 6) {
                                              iconData = notif.isRead ? Icons.chat_bubble_outline_rounded : Icons.chat_bubble_rounded;
                                              iconColor = const Color(0xFF8B5E34);
                                            } else {
                                              iconData = notif.isRead ? Icons.campaign_outlined : Icons.campaign_rounded;
                                              iconColor = Colors.grey;
                                            }
                                            return Icon(
                                              iconData,
                                              color: notif.isRead ? Colors.grey : iconColor,
                                              size: 24,
                                            );
                                          }
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
                                      displayDate,
                                      style: const TextStyle(
                                        fontSize: 10,
                                        color: Colors.grey,
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 8),
                                Text(
                                  notif.title,
                                  style: TextStyle(
                                    fontWeight: notif.isRead
                                        ? FontWeight.bold
                                        : FontWeight.w900,
                                    fontSize: 15,
                                    color: const Color(0xFF1C1917),
                                  ),
                                ),
                                const SizedBox(height: 8),
                                Text(
                                  displayContent,
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
                ),
        ],
      ),
    );
  }
}
