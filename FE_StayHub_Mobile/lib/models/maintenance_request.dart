import '../config/app_config.dart';

class MaintenanceRequest {
  // Status constants matching backend:
  static const int STATUS_CREATED = 1;
  static const int STATUS_RECEIVED = 2;
  static const int STATUS_PROCESSING = 3;
  static const int STATUS_COMPLETED = 4;
  static const int STATUS_CANCELLED = 5;

  final int id;
  final String requestCode;
  final int roomId;
  final String roomNumber;
  final int? tenantId;
  final String? tenantName;
  final String title;
  final String description;
  final int status; // 1: Created, 2: Received, 3: Processing, 4: Completed, 5: Cancelled
  final List<String>? images;
  final int? assignedTo;
  final String? receivedAt;
  final String? completedAt;
  final String? feedback; // Mapped from embedded relation/feedback comment if any
  final String createdAt;

  MaintenanceRequest({
    required this.id,
    required this.requestCode,
    required this.roomId,
    required this.roomNumber,
    this.tenantId,
    this.tenantName,
    required this.title,
    required this.description,
    required this.status,
    this.images,
    this.assignedTo,
    this.receivedAt,
    this.completedAt,
    this.feedback,
    required this.createdAt,
  });

  // Getters for backward compatibility
  String? get beforeImageUrl {
    final url = (images != null && images!.isNotEmpty) ? images!.first : null;
    return _mapLocalUrl(url);
  }

  String? get afterImageUrl {
    final url = (images != null && images!.length > 1) ? images![1] : null;
    return _mapLocalUrl(url);
  }

  static String? _mapLocalUrl(String? url) {
    if (url == null) return null;
    if (url.startsWith('http://localhost:8080')) {
      return url.replaceFirst('http://localhost:8080', AppConfig.apiOrigin);
    }
    if (url.startsWith('http://127.0.0.1:8080')) {
      return url.replaceFirst('http://127.0.0.1:8080', AppConfig.apiOrigin);
    }
    return url;
  }

  factory MaintenanceRequest.fromJson(Map<String, dynamic> json) {
    // Deserialize images list if present
    List<String>? imagesList;
    if (json['images'] != null) {
      if (json['images'] is List) {
        imagesList = (json['images'] as List).map((i) => i.toString()).toList();
      }
    }

    return MaintenanceRequest(
      id: json['id'] as int,
      requestCode: json['request_code'] as String? ?? '',
      roomId: json['room_id'] as int? ?? 0,
      roomNumber: json['room_number'] as String? ?? '',
      tenantId: json['tenant_id'] as int?,
      tenantName: json['tenant_name'] as String?,
      title: json['title'] as String? ?? '',
      description: json['description'] as String? ?? '',
      status: json['status'] as int? ?? STATUS_CREATED,
      images: imagesList,
      assignedTo: json['assigned_to'] as int?,
      receivedAt: json['received_at'] as String?,
      completedAt: json['completed_at'] as String?,
      feedback: json['feedback'] as String?,
      createdAt: json['created_at'] as String? ?? '',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'request_code': requestCode,
      'room_id': roomId,
      'room_number': roomNumber,
      'tenant_id': tenantId,
      'tenant_name': tenantName,
      'title': title,
      'description': description,
      'status': status,
      'images': images,
      'assigned_to': assignedTo,
      'received_at': receivedAt,
      'completed_at': completedAt,
      'feedback': feedback,
      'created_at': createdAt,
    };
  }

  String get statusLabel {
    switch (status) {
      case STATUS_CREATED:
        return 'Mới tạo';
      case STATUS_RECEIVED:
        return 'Đã tiếp nhận';
      case STATUS_PROCESSING:
        return 'Đang xử lý';
      case STATUS_COMPLETED:
        return 'Đã hoàn thành';
      case STATUS_CANCELLED:
        return 'Đã hủy';
      default:
        return 'Không xác định';
    }
  }
}
