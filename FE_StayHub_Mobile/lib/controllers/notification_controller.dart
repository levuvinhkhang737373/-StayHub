import 'package:flutter/material.dart';
import '../models/notification.dart';
import '../services/api_service.dart';

class NotificationController extends ChangeNotifier {
  final ApiService _apiService = ApiService();

  List<NotificationModel> _notifications = [];
  bool _isLoading = false;
  String? _errorMessage;
  int? _serverUnreadCount;

  List<NotificationModel> get notifications => _notifications;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;

  /// Returns the server-provided unread count when available (accurate across
  /// all pages), otherwise falls back to counting unread items in the loaded
  /// page.
  int get unreadCount => _serverUnreadCount ?? _notifications.where((n) => !n.isRead).length;

  /// Tải danh sách thông báo
  Future<void> fetchNotifications({required bool isAdmin}) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final path = isAdmin ? '/admin/notifications' : '/tenant/notifications';
      final response = await _apiService.get<Map<String, dynamic>>(
        path,
        fromJsonT: (json) => json as Map<String, dynamic>,
      );

      if (response.status && response.result != null) {
        final result = response.result!;
        final data = result['data'] as List<dynamic>? ?? [];
        _notifications = data
            .map((item) => NotificationModel.fromJson(item as Map<String, dynamic>))
            .toList();

        // Use server-side unread count when available (admin endpoint provides
        // stats.unread; tenant endpoint does not).
        final stats = result['stats'] as Map<String, dynamic>?;
        if (stats != null && stats.containsKey('unread')) {
          _serverUnreadCount = (stats['unread'] as num?)?.toInt();
        } else {
          _serverUnreadCount = null;
        }
      } else {
        _errorMessage = response.message;
      }
    } catch (e) {
      _errorMessage = 'Lỗi tải danh sách thông báo: $e';
    }

    _isLoading = false;
    notifyListeners();
  }

  /// Đánh dấu một thông báo đã đọc
  Future<bool> markAsRead(int id, {bool isAdmin = false}) async {
    try {
      final path = isAdmin ? '/admin/notifications/$id/read' : '/tenant/notifications/$id/read';
      final response = await _apiService.post<dynamic>(
        path,
        fromJsonT: (json) => json,
      );

      if (response.status) {
        // Cập nhật cục bộ để UI phản ứng ngay
        final index = _notifications.indexWhere((n) => n.id == id);
        if (index != -1) {
          final old = _notifications[index];
          _notifications[index] = NotificationModel(
            id: old.id,
            title: old.title,
            content: old.content,
            notificationType: old.notificationType,
            notificationTypeLabel: old.notificationTypeLabel,
            targetType: old.targetType,
            publishedAt: old.publishedAt,
            isRead: true,
            createdAt: old.createdAt,
          );
          notifyListeners();
        }
        return true;
      }
    } catch (e) {
      debugPrint('Lỗi đánh dấu đọc thông báo: $e');
    }
    return false;
  }

  /// Đánh dấu đã đọc toàn bộ thông báo
  Future<bool> markAllAsRead({bool isAdmin = false}) async {
    try {
      final path = isAdmin ? '/admin/notifications/read-all' : '/tenant/notifications/read-all';
      final response = await _apiService.post<dynamic>(
        path,
        fromJsonT: (json) => json,
      );

      if (response.status) {
        // Cập nhật cục bộ
        _notifications = _notifications.map((n) {
          return NotificationModel(
            id: n.id,
            title: n.title,
            content: n.content,
            notificationType: n.notificationType,
            notificationTypeLabel: n.notificationTypeLabel,
            targetType: n.targetType,
            publishedAt: n.publishedAt,
            isRead: true,
            createdAt: n.createdAt,
          );
        }).toList();
        notifyListeners();
        return true;
      }
    } catch (e) {
      debugPrint('Lỗi đánh dấu đọc toàn bộ thông báo: $e');
    }
    return false;
  }

  /// Thêm thông báo mới nhận được qua WebSocket
  void addRealtimeNotification(NotificationModel notification) {
    // Đưa lên đầu danh sách
    _notifications.insert(0, notification);
    notifyListeners();
  }

  /// Gửi thông báo từ phía Admin/Super Admin
  Future<bool> sendNotification({
    required String title,
    required String content,
    required int notificationType,
    required int targetType,
    int? tenantId,
    int? buildingId,
    int? roomId,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final Map<String, dynamic> payload = {
        'title': title,
        'content': content,
        'notification_type': notificationType,
        'target_type': targetType,
        'status': 2, // 2: SENT (Gửi ngay)
      };

      if (tenantId != null) payload['tenant_id'] = tenantId;
      if (buildingId != null) payload['building_id'] = buildingId;
      if (roomId != null) payload['room_id'] = roomId;

      final response = await _apiService.post<dynamic>(
        '/admin/notifications',
        data: payload,
        fromJsonT: (json) => json,
      );

      if (response.status) {
        _isLoading = false;
        notifyListeners();
        return true;
      } else {
        _errorMessage = response.message;
      }
    } catch (e) {
      if (e is ApiException) {
        _errorMessage = e.message;
      } else {
        _errorMessage = 'Lỗi gửi thông báo: $e';
      }
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }
}
