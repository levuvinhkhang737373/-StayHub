import '../config/app_config.dart';

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
  final List<String> attachments;

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
    this.attachments = const [],
  });

  factory ChatMessage.fromJson(Map<String, dynamic> json) {
    final attachmentsJson = json['attachments'];
    List<String> attachmentsList = [];
    if (attachmentsJson is List) {
      attachmentsList = attachmentsJson
          .map((e) => _normalizeAssetUrl(e.toString()))
          .toList();
    }
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
      attachments: attachmentsList,
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
      attachments: attachments,
    );
  }

  bool isMineForAdmin(int? adminId) {
    return senderType == 'admin' && adminId != null && senderId == adminId;
  }
}

String _normalizeAssetUrl(String url) {
  if (url.startsWith('http://localhost:8080')) {
    return url.replaceFirst('http://localhost:8080', AppConfig.assetOrigin);
  }
  if (url.startsWith('http://127.0.0.1:8080')) {
    return url.replaceFirst('http://127.0.0.1:8080', AppConfig.assetOrigin);
  }
  if (url.startsWith('http://10.0.2.2:8080')) {
    return url.replaceFirst('http://10.0.2.2:8080', AppConfig.assetOrigin);
  }

  return url;
}

class ChatConversation {
  static const int typeTenantManager = 1;
  static const int typeSuperAdminManager = 2;

  final int id;
  final int conversationType;
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
  final String? managerUsername;
  final String? managerPhone;
  final String? managerEmail;
  final List<String> managerBuildingNames;
  final int? superAdminId;
  final String? superAdminName;
  final String? superAdminUsername;
  final String? superAdminAvatarUrl;
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
    this.conversationType = typeTenantManager,
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
    this.managerUsername,
    this.managerPhone,
    this.managerEmail,
    this.managerBuildingNames = const [],
    this.superAdminId,
    this.superAdminName,
    this.superAdminUsername,
    this.superAdminAvatarUrl,
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
    final managerBuildingNamesJson = json['manager_building_names'];
    return ChatConversation(
      id: json['id'] as int,
      conversationType: json['conversation_type'] as int? ?? typeTenantManager,
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
      managerUsername: json['manager_username'] as String?,
      managerPhone: json['manager_phone'] as String?,
      managerEmail: json['manager_email'] as String?,
      managerBuildingNames: managerBuildingNamesJson is List
          ? managerBuildingNamesJson.map((item) => item.toString()).toList()
          : const [],
      superAdminId: json['super_admin_id'] as int?,
      superAdminName: json['super_admin_name'] as String?,
      superAdminUsername: json['super_admin_username'] as String?,
      superAdminAvatarUrl: json['super_admin_avatar_url'] as String?,
      lastMessageId: json['last_message_id'] as int?,
      lastMessage: lastMessageJson is Map<String, dynamic>
          ? ChatMessage.fromJson(lastMessageJson)
          : null,
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

  bool get isDirect => conversationType == typeSuperAdminManager;

  bool isSuperAdminSide(int? adminId) {
    return adminId != null && superAdminId == adminId;
  }

  int unreadCountForAdmin(int? adminId) {
    if (!isDirect) return adminUnreadCount;
    if (adminId == null) return 0;

    return isSuperAdminSide(adminId) ? adminUnreadCount : tenantUnreadCount;
  }

  String displayTitleForAdmin(int? adminId) {
    if (!isDirect) return tenantName ?? 'Khách thuê';
    if (isSuperAdminSide(adminId)) {
      return managerName ?? managerUsername ?? 'Quản lý #$managerAdminId';
    }

    return superAdminName ?? superAdminUsername ?? 'Superadmin';
  }

  String displaySubtitleForAdmin(int? adminId) {
    if (!isDirect) {
      return '${buildingName ?? 'Tòa nhà'} · Phòng ${roomNumber ?? roomId}';
    }
    if (isSuperAdminSide(adminId)) {
      final buildings = managerBuildingNames.isNotEmpty
          ? managerBuildingNames.join(', ')
          : 'tòa nhà đang quản lý';
      return '${managerPhone ?? managerEmail ?? 'Chưa có liên hệ'} · $buildings';
    }

    return 'Ban quản trị StayHub';
  }

  String listLeadingTextForAdmin(int? adminId) {
    if (!isDirect) return roomNumber ?? 'P?';

    final name = displayTitleForAdmin(adminId).trim();
    if (name.isEmpty) return isSuperAdminSide(adminId) ? 'QL' : 'SA';
    final parts = name.split(RegExp(r'\s+')).where((part) => part.isNotEmpty);
    return parts
        .take(2)
        .map((part) => part.substring(0, 1))
        .join()
        .toUpperCase();
  }

  String listTitleForAdmin(int? adminId) {
    if (!isDirect) {
      return 'Phòng ${roomNumber ?? '—'} · ${tenantName ?? 'Khách thuê'}';
    }

    return displayTitleForAdmin(adminId);
  }

  String listSubtitleForAdmin(int? adminId) {
    final body = lastMessage?.body.trim();
    if (body != null && body.isNotEmpty) return body;
    if (lastMessage != null && lastMessage!.attachments.isNotEmpty) {
      return '[Hình ảnh]';
    }
    if (isDirect) return displaySubtitleForAdmin(adminId);

    return buildingName ?? 'Bắt đầu trò chuyện';
  }

  String get displayTitle {
    if (isDirect) {
      return superAdminName ?? superAdminUsername ?? 'Superadmin';
    }

    return tenantName ?? 'Khách thuê';
  }

  String get displaySubtitle {
    if (isDirect) {
      return 'Ban quản trị StayHub';
    }

    return '${buildingName ?? 'Tòa nhà'} · Phòng ${roomNumber ?? roomId}';
  }

  String get listLeadingText {
    if (isDirect) {
      final name = displayTitle.trim();
      if (name.isEmpty) return 'SA';
      final parts = name.split(RegExp(r'\s+')).where((part) => part.isNotEmpty);
      return parts
          .take(2)
          .map((part) => part.substring(0, 1))
          .join()
          .toUpperCase();
    }

    return roomNumber ?? 'P?';
  }

  String get listTitle {
    if (isDirect) {
      return displayTitle;
    }

    return 'Phòng ${roomNumber ?? '—'} · ${tenantName ?? 'Khách thuê'}';
  }

  String get listSubtitle {
    final body = lastMessage?.body.trim();
    if (body != null && body.isNotEmpty) {
      return body;
    }
    if (lastMessage != null && lastMessage!.attachments.isNotEmpty) {
      return '[Hình ảnh]';
    }
    if (isDirect) {
      return managerBuildingNames.isNotEmpty
          ? managerBuildingNames.join(', ')
          : 'Trao đổi trực tiếp với superadmin';
    }

    return buildingName ?? 'Bắt đầu trò chuyện';
  }
}
