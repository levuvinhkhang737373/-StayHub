import 'tenant.dart';
import 'room.dart';

double _toDouble(dynamic value) => double.tryParse(value?.toString() ?? '0') ?? 0.0;

class TransferSettlement {
  final String transferCode;
  final double settlementDueAmount;
  final double settlementPaidAmount;
  final double settlementRemainingAmount;

  const TransferSettlement({
    required this.transferCode,
    required this.settlementDueAmount,
    required this.settlementPaidAmount,
    required this.settlementRemainingAmount,
  });

  factory TransferSettlement.fromJson(Map<String, dynamic> json) {
    return TransferSettlement(
      transferCode: json['transfer_code'] as String? ?? '',
      settlementDueAmount: _toDouble(json['settlement_due_amount']),
      settlementPaidAmount: _toDouble(json['settlement_paid_amount']),
      settlementRemainingAmount: _toDouble(json['settlement_remaining_amount']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'transfer_code': transferCode,
      'settlement_due_amount': settlementDueAmount,
      'settlement_paid_amount': settlementPaidAmount,
      'settlement_remaining_amount': settlementRemainingAmount,
    };
  }
}

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
  final double depositDueAmount;
  final int status; // 1: Active, 2: Expired, 3: Liquidated, 4: Cancelled, 0: Draft/Pending
  final List<String>? contractFiles;
  final String? note;
  final int? createdBy;
  final String? roomCode;
  final String? representativeName;
  final Tenant? representativeTenant;
  final Room? room;
  final String? tenantSignedAt;
  final String? tenantSignatureUrl;
  
  // Negotiation fields
  final int? negotiationStatus;
  final double? proposedRoomPrice;
  final List<dynamic>? proposedServices;
  final List<dynamic>? roomServices;
  
  // Payment fields
  final bool isDepositPaid;
  final int? paymentStatus;
  final String? paymentStatusLabel;
  final String? depositQrUrl;
  final TransferSettlement? transferSettlement;
  final bool? isStaying;

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
    this.depositDueAmount = 0.0,
    required this.status,
    this.contractFiles,
    this.note,
    this.createdBy,
    this.roomCode,
    this.representativeName,
    this.representativeTenant,
    this.room,
    this.tenantSignedAt,
    this.tenantSignatureUrl,
    this.negotiationStatus,
    this.proposedRoomPrice,
    this.proposedServices,
    this.roomServices,
    this.isDepositPaid = true,
    this.paymentStatus,
    this.paymentStatusLabel,
    this.depositQrUrl,
    this.transferSettlement,
    this.isStaying = true,
  });

  // Alias fields for backward compatibility
  int get tenantId => representativeTenantId;
  double get rentalPrice => roomPrice;
  bool get hasTransferSettlementDue => transferSettlement != null && transferSettlement!.settlementRemainingAmount > 0;
  double get paymentDueAmount => hasTransferSettlementDue ? transferSettlement!.settlementRemainingAmount : depositDueAmount;
  String get paymentReferenceCode => hasTransferSettlementDue && transferSettlement!.transferCode.isNotEmpty
      ? transferSettlement!.transferCode
      : 'COC $contractCode';

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

    final depositAmountValue = _toDouble(json['deposit_amount']);
    final isDepositPaidValue = json['is_deposit_paid'] == true || json['is_deposit_paid'] == 1;
    final transferSettlement = json['transfer_settlement'] is Map<String, dynamic>
        ? TransferSettlement.fromJson(json['transfer_settlement'] as Map<String, dynamic>)
        : null;

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
          ? _toDouble(json['room_price'])
          : (json['rental_price'] != null ? _toDouble(json['rental_price']) : 0.0),
      depositAmount: depositAmountValue,
      depositDueAmount: json['deposit_due_amount'] != null
          ? _toDouble(json['deposit_due_amount'])
          : (isDepositPaidValue ? 0.0 : depositAmountValue),
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
      tenantSignedAt: json['tenant_signed_at'] as String?,
      tenantSignatureUrl: json['tenant_signature_url'] as String?,
      negotiationStatus: json['negotiation_status'] as int?,
      proposedRoomPrice: json['proposed_room_price'] != null ? _toDouble(json['proposed_room_price']) : null,
      proposedServices: json['proposed_services'] as List<dynamic>?,
      roomServices: json['room_services'] as List<dynamic>?,
      isDepositPaid: isDepositPaidValue,
      paymentStatus: json['payment_status'] as int?,
      paymentStatusLabel: json['payment_status_label'] as String?,
      depositQrUrl: json['deposit_qr_url'] as String?,
      transferSettlement: transferSettlement,
      isStaying: json['is_staying'] != false,
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
      'deposit_due_amount': depositDueAmount,
      'status': status,
      'contract_files': contractFiles,
      'note': note,
      'created_by': createdBy,
      'room_code': roomCode,
      'representative_name': representativeName,
      'representative_tenant': representativeTenant?.toJson(),
      'room': room?.toJson(),
      'tenant_signed_at': tenantSignedAt,
      'tenant_signature_url': tenantSignatureUrl,
      'is_deposit_paid': isDepositPaid,
      'payment_status': paymentStatus,
      'payment_status_label': paymentStatusLabel,
      'deposit_qr_url': depositQrUrl,
      'transfer_settlement': transferSettlement?.toJson(),
      'is_staying': isStaying,
    };
  }

  String get statusLabel {
    if (status == STATUS_ACTIVE && isStaying == false) {
      return 'Đã thanh lý';
    }
    switch (status) {
      case STATUS_DRAFT:
        return 'Chờ ký';
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
