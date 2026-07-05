class NotificationModel {
  final int id;
  final String title;
  final String content;
  final int notificationType;
  final String notificationTypeLabel;
  final int targetType;
  final String? publishedAt;
  final bool isRead;
  final String createdAt;

  final int? tenantId;

  NotificationModel({
    required this.id,
    required this.title,
    required this.content,
    required this.notificationType,
    required this.notificationTypeLabel,
    required this.targetType,
    this.publishedAt,
    required this.isRead,
    required this.createdAt,
    this.tenantId,
  });

  factory NotificationModel.fromJson(Map<String, dynamic> json) {
    return NotificationModel(
      id: json['id'] as int,
      title: json['title'] as String? ?? '',
      content: json['content'] as String? ?? '',
      notificationType: json['notification_type'] as int? ?? 1,
      notificationTypeLabel: json['notification_type_label'] as String? ?? 'Khác',
      targetType: json['target_type'] as int? ?? 1,
      publishedAt: json['published_at'] as String?,
      isRead: json['is_read'] as bool? ?? false,
      createdAt: json['created_at'] as String? ?? '',
      tenantId: json['tenant_id'] as int?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'title': title,
      'content': content,
      'notification_type': notificationType,
      'notification_type_label': notificationTypeLabel,
      'target_type': targetType,
      'published_at': publishedAt,
      'is_read': isRead,
      'created_at': createdAt,
      'tenant_id': tenantId,
    };
  }
}
