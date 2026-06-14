import 'tenant.dart';
import 'room.dart';

class Contract {
  // Status constants matching backend:
  static const int STATUS_DRAFT = 0;
  static const int STATUS_ACTIVE = 1;
  static const int STATUS_EXPIRED = 2;
  static const int STATUS_LIQUIDATED = 3;
  static const int STATUS_CANCELLED = 4;

  final int id;
  final String contractCode;
  final int roomId;
  final String roomNumber;
  final int representativeTenantId;
  final String tenantName;
  final String startDate;
  final String? endDate;
  final String? actualEndDate;
  final int billingCycleDay;
  final double roomPrice;
  final double depositAmount;
  final int status; // 1: Active, 2: Expired, 3: Liquidated, 4: Cancelled, 0: Draft
  final List<String>? contractFiles;
  final String? note;
  final int? createdBy;
  final String? roomCode;
  final String? representativeName;
  final Tenant? representativeTenant;
  final Room? room;
  
  // Payment fields
  final bool isDepositPaid;
  final int? paymentStatus;
  final String? paymentStatusLabel;
  final String? depositQrUrl;

  Contract({
    required this.id,
    required this.contractCode,
    required this.roomId,
    required this.roomNumber,
    required this.representativeTenantId,
    required this.tenantName,
    required this.startDate,
    this.endDate,
    this.actualEndDate,
    required this.billingCycleDay,
    required this.roomPrice,
    required this.depositAmount,
    required this.status,
    this.contractFiles,
    this.note,
    this.createdBy,
    this.roomCode,
    this.representativeName,
    this.representativeTenant,
    this.room,
    this.isDepositPaid = true,
    this.paymentStatus,
    this.paymentStatusLabel,
    this.depositQrUrl,
  });

  // Alias fields for backward compatibility
  int get tenantId => representativeTenantId;
  double get rentalPrice => roomPrice;

  factory Contract.fromJson(Map<String, dynamic> json) {
    // Deserialize contract files if present (handling Map list from API)
    List<String>? files;
    if (json['contract_files'] != null && json['contract_files'] is List) {
      files = (json['contract_files'] as List)
          .map((f) {
            if (f is Map) {
              return f['url']?.toString() ?? f['path']?.toString() ?? '';
            }
            return f.toString();
          })
          .where((url) => url.isNotEmpty)
          .toList();
    }

    return Contract(
      id: json['id'] as int,
      contractCode: json['contract_code'] as String? ?? '',
      roomId: json['room_id'] as int? ?? 0,
      roomNumber: json['room_number'] as String? ?? '',
      representativeTenantId: json['representative_tenant_id'] as int? ?? json['tenant_id'] as int? ?? 0,
      tenantName: json['tenant_name'] as String? ?? '',
      startDate: json['start_date'] as String? ?? '',
      endDate: json['end_date'] as String?,
      actualEndDate: json['actual_end_date'] as String?,
      billingCycleDay: json['billing_cycle_day'] as int? ?? 1,
      roomPrice: json['room_price'] != null
          ? (double.tryParse(json['room_price'].toString()) ?? 0.0)
          : (json['rental_price'] != null ? (double.tryParse(json['rental_price'].toString()) ?? 0.0) : 0.0),
      depositAmount: json['deposit_amount'] != null ? (double.tryParse(json['deposit_amount'].toString()) ?? 0.0) : 0.0,
      status: json['status'] as int? ?? STATUS_DRAFT,
      contractFiles: files,
      note: json['note'] as String?,
      createdBy: json['created_by'] as int?,
      roomCode: json['room_code'] as String?,
      representativeName: json['representative_name'] as String?,
      representativeTenant: json['representative_tenant'] != null
          ? Tenant.fromJson(json['representative_tenant'] as Map<String, dynamic>)
          : null,
      room: json['room'] != null ? Room.fromJson(json['room'] as Map<String, dynamic>) : null,
      isDepositPaid: json['is_deposit_paid'] == true || json['is_deposit_paid'] == 1,
      paymentStatus: json['payment_status'] as int?,
      paymentStatusLabel: json['payment_status_label'] as String?,
      depositQrUrl: json['deposit_qr_url'] as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'contract_code': contractCode,
      'room_id': roomId,
      'room_number': roomNumber,
      'representative_tenant_id': representativeTenantId,
      'tenant_id': representativeTenantId,
      'tenant_name': tenantName,
      'start_date': startDate,
      'end_date': endDate,
      'actual_end_date': actualEndDate,
      'billing_cycle_day': billingCycleDay,
      'room_price': roomPrice,
      'rental_price': roomPrice,
      'deposit_amount': depositAmount,
      'status': status,
      'contract_files': contractFiles,
      'note': note,
      'created_by': createdBy,
      'room_code': roomCode,
      'representative_name': representativeName,
      'representative_tenant': representativeTenant?.toJson(),
      'room': room?.toJson(),
      'is_deposit_paid': isDepositPaid,
      'payment_status': paymentStatus,
      'payment_status_label': paymentStatusLabel,
      'deposit_qr_url': depositQrUrl,
    };
  }

  String get statusLabel {
    switch (status) {
      case STATUS_DRAFT:
        return 'Bản nháp';
      case STATUS_ACTIVE:
        return 'Hiệu lực';
      case STATUS_EXPIRED:
        return 'Hết hạn';
      case STATUS_LIQUIDATED:
        return 'Đã thanh lý';
      case STATUS_CANCELLED:
        return 'Đã hủy';
      default:
        return 'Không xác định';
    }
  }

  bool get isActive => status == STATUS_ACTIVE;
}
