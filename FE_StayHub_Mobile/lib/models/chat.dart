class ChatMessage {
  final int id;
  final int conversationId;
  final String senderType;
  final int senderId;
  final int senderRole;
  final String? senderRoleLabel;
  final String? senderName;
  final String? senderAvatarUrl;
  final String body;
  final String? queuedAt;
  final String? sentAt;
  final String? readAt;
  final String? createdAt;
  final bool optimistic;

  ChatMessage({
    required this.id,
    required this.conversationId,
    required this.senderType,
    required this.senderId,
    required this.senderRole,
    this.senderRoleLabel,
    this.senderName,
    this.senderAvatarUrl,
    required this.body,
    this.queuedAt,
    this.sentAt,
    this.readAt,
    this.createdAt,
    this.optimistic = false,
  });

  factory ChatMessage.fromJson(Map<String, dynamic> json) {
    return ChatMessage(
      id: json['id'] as int,
      conversationId: json['conversation_id'] as int? ?? 0,
      senderType: json['sender_type'] as String? ?? '',
      senderId: json['sender_id'] as int? ?? 0,
      senderRole: json['sender_role'] as int? ?? 0,
      senderRoleLabel: json['sender_role_label'] as String?,
      senderName: json['sender_name'] as String?,
      senderAvatarUrl: json['sender_avatar_url'] as String?,
      body: json['body'] as String? ?? '',
      queuedAt: json['queued_at'] as String?,
      sentAt: json['sent_at'] as String?,
      readAt: json['read_at'] as String?,
      createdAt: json['created_at'] as String?,
    );
  }

  ChatMessage copyWith({bool? optimistic}) {
    return ChatMessage(
      id: id,
      conversationId: conversationId,
      senderType: senderType,
      senderId: senderId,
      senderRole: senderRole,
      senderRoleLabel: senderRoleLabel,
      senderName: senderName,
      senderAvatarUrl: senderAvatarUrl,
      body: body,
      queuedAt: queuedAt,
      sentAt: sentAt,
      readAt: readAt,
      createdAt: createdAt,
      optimistic: optimistic ?? this.optimistic,
    );
  }
}

class ChatConversation {
  final int id;
  final int buildingId;
  final String? buildingName;
  final int roomId;
  final String? roomNumber;
  final int tenantId;
  final String? tenantName;
  final String? tenantPhone;
  final String? tenantAvatarUrl;
  final int managerAdminId;
  final String? managerName;
  final int? lastMessageId;
  final ChatMessage? lastMessage;
  final String? lastMessageAt;
  final int tenantUnreadCount;
  final int adminUnreadCount;
  final String? tenantLastReadAt;
  final String? adminLastReadAt;
  final int status;
  final String? statusLabel;
  final String? createdAt;
  final String? updatedAt;

  ChatConversation({
    required this.id,
    required this.buildingId,
    this.buildingName,
    required this.roomId,
    this.roomNumber,
    required this.tenantId,
    this.tenantName,
    this.tenantPhone,
    this.tenantAvatarUrl,
    required this.managerAdminId,
    this.managerName,
    this.lastMessageId,
    this.lastMessage,
    this.lastMessageAt,
    required this.tenantUnreadCount,
    required this.adminUnreadCount,
    this.tenantLastReadAt,
    this.adminLastReadAt,
    required this.status,
    this.statusLabel,
    this.createdAt,
    this.updatedAt,
  });

  factory ChatConversation.fromJson(Map<String, dynamic> json) {
    final lastMessageJson = json['last_message'];
    return ChatConversation(
      id: json['id'] as int,
      buildingId: json['building_id'] as int? ?? 0,
      buildingName: json['building_name'] as String?,
      roomId: json['room_id'] as int? ?? 0,
      roomNumber: json['room_number'] as String?,
      tenantId: json['tenant_id'] as int? ?? 0,
      tenantName: json['tenant_name'] as String?,
      tenantPhone: json['tenant_phone'] as String?,
      tenantAvatarUrl: json['tenant_avatar_url'] as String?,
      managerAdminId: json['manager_admin_id'] as int? ?? 0,
      managerName: json['manager_name'] as String?,
      lastMessageId: json['last_message_id'] as int?,
      lastMessage: lastMessageJson is Map<String, dynamic> ? ChatMessage.fromJson(lastMessageJson) : null,
      lastMessageAt: json['last_message_at'] as String?,
      tenantUnreadCount: json['tenant_unread_count'] as int? ?? 0,
      adminUnreadCount: json['admin_unread_count'] as int? ?? 0,
      tenantLastReadAt: json['tenant_last_read_at'] as String?,
      adminLastReadAt: json['admin_last_read_at'] as String?,
      status: json['status'] as int? ?? 1,
      statusLabel: json['status_label'] as String?,
      createdAt: json['created_at'] as String?,
      updatedAt: json['updated_at'] as String?,
    );
  }
}
