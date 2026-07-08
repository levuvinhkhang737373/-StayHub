import 'tenant.dart';
import 'room.dart';

double _toDouble(dynamic value) =>
    double.tryParse(value?.toString() ?? '0') ?? 0.0;
int? _toNullableInt(dynamic value) =>
    value == null ? null : int.tryParse(value.toString());

class TransferSettlement {
  final String transferCode;
  final double depositDueAmount;
  final double depositPaidAmount;
  final double depositRemainingAmount;
  final double extraChargeAmount;
  final double extraPaidAmount;
  final double extraRemainingAmount;
  final double transferFee;
  final double deductionAmount;
  final double depositTransferAmount;
  final double depositRefundAmount;
  final double manualRefundAmount;
  final double settlementDueAmount;
  final double settlementPaidAmount;
  final double settlementRemainingAmount;
  final int? settlementPaymentStatus;
  final String? settlementPaymentStatusLabel;
  final String? settlementQrUrl;

  const TransferSettlement({
    required this.transferCode,
    required this.depositDueAmount,
    required this.depositPaidAmount,
    required this.depositRemainingAmount,
    required this.extraChargeAmount,
    required this.extraPaidAmount,
    required this.extraRemainingAmount,
    required this.transferFee,
    required this.deductionAmount,
    required this.depositTransferAmount,
    required this.depositRefundAmount,
    required this.manualRefundAmount,
    required this.settlementDueAmount,
    required this.settlementPaidAmount,
    required this.settlementRemainingAmount,
    this.settlementPaymentStatus,
    this.settlementPaymentStatusLabel,
    this.settlementQrUrl,
  });

  factory TransferSettlement.fromJson(Map<String, dynamic> json) {
    final settlementDueAmount = _toDouble(json['settlement_due_amount']);
    final settlementPaidAmount = _toDouble(json['settlement_paid_amount']);
    final settlementRemainingAmount = _toDouble(
      json['settlement_remaining_amount'],
    );
    final depositDueAmount = _toDouble(json['deposit_due_amount']);
    final depositPaidAmount = _toDouble(json['deposit_paid_amount']);
    final explicitDepositRemainingAmount =
        json.containsKey('deposit_remaining_amount')
        ? _toDouble(json['deposit_remaining_amount'])
        : (depositDueAmount - depositPaidAmount)
              .clamp(0.0, double.infinity)
              .toDouble();
    final extraChargeAmount = _toDouble(json['extra_charge_amount']);
    final extraPaidAmount = _toDouble(json['extra_paid_amount']);
    final explicitExtraRemainingAmount =
        json.containsKey('extra_remaining_amount')
        ? _toDouble(json['extra_remaining_amount'])
        : (extraChargeAmount - extraPaidAmount)
              .clamp(0.0, double.infinity)
              .toDouble();

    return TransferSettlement(
      transferCode: json['transfer_code'] as String? ?? '',
      depositDueAmount: depositDueAmount,
      depositPaidAmount: depositPaidAmount,
      depositRemainingAmount: explicitDepositRemainingAmount,
      extraChargeAmount: extraChargeAmount,
      extraPaidAmount: extraPaidAmount,
      extraRemainingAmount: explicitExtraRemainingAmount,
      transferFee: _toDouble(json['transfer_fee']),
      deductionAmount: _toDouble(json['deduction_amount']),
      depositTransferAmount: _toDouble(json['deposit_transfer_amount']),
      depositRefundAmount: _toDouble(json['deposit_refund_amount']),
      manualRefundAmount: _toDouble(json['manual_refund_amount']),
      settlementDueAmount: settlementDueAmount,
      settlementPaidAmount: settlementPaidAmount,
      settlementRemainingAmount: settlementRemainingAmount,
      settlementPaymentStatus: _toNullableInt(
        json['settlement_payment_status'],
      ),
      settlementPaymentStatusLabel:
          json['settlement_payment_status_label'] as String?,
      settlementQrUrl: json['settlement_qr_url'] as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'transfer_code': transferCode,
      'deposit_due_amount': depositDueAmount,
      'deposit_paid_amount': depositPaidAmount,
      'deposit_remaining_amount': depositRemainingAmount,
      'extra_charge_amount': extraChargeAmount,
      'extra_paid_amount': extraPaidAmount,
      'extra_remaining_amount': extraRemainingAmount,
      'transfer_fee': transferFee,
      'deduction_amount': deductionAmount,
      'deposit_transfer_amount': depositTransferAmount,
      'deposit_refund_amount': depositRefundAmount,
      'manual_refund_amount': manualRefundAmount,
      'settlement_due_amount': settlementDueAmount,
      'settlement_paid_amount': settlementPaidAmount,
      'settlement_remaining_amount': settlementRemainingAmount,
      'settlement_payment_status': settlementPaymentStatus,
      'settlement_payment_status_label': settlementPaymentStatusLabel,
      'settlement_qr_url': settlementQrUrl,
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
  final double roomPrice;
  final double depositAmount;
  final double depositDueAmount;
  final int
  status; // 1: Active, 2: Expired, 3: Liquidated, 4: Cancelled, 0: Draft/Pending
  final List<String>? contractFiles;
  final String? note;
  final int? createdBy;
  final String? roomCode;
  final String? representativeName;
  final Tenant? representativeTenant;
  final Room? room;
  final String? tenantSignedAt;
  final String? tenantSignatureUrl;

  final List<dynamic>? roomServices;
  final List<dynamic>? contractVehicles;

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

    this.roomServices,
    this.contractVehicles,
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
  bool get hasTransferSettlementDue =>
      transferSettlement != null &&
      transferSettlement!.settlementRemainingAmount > 0;
  double get paymentDueAmount => hasTransferSettlementDue
      ? transferSettlement!.settlementRemainingAmount
      : depositDueAmount;
  String get paymentReferenceCode =>
      hasTransferSettlementDue && transferSettlement!.transferCode.isNotEmpty
      ? transferSettlement!.transferCode
      : 'COC $contractCode';
  String? get paymentQrUrl =>
      hasTransferSettlementDue &&
          transferSettlement?.settlementQrUrl?.isNotEmpty == true
      ? transferSettlement!.settlementQrUrl
      : depositQrUrl;

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
    final isDepositPaidValue =
        json['is_deposit_paid'] == true || json['is_deposit_paid'] == 1;
    final transferSettlement =
        json['transfer_settlement'] is Map<String, dynamic>
        ? TransferSettlement.fromJson(
            json['transfer_settlement'] as Map<String, dynamic>,
          )
        : null;

    return Contract(
      id: json['id'] as int,
      contractCode: json['contract_code'] as String? ?? '',
      roomId: json['room_id'] as int? ?? 0,
      roomNumber: json['room_number'] as String? ?? '',
      representativeTenantId:
          json['representative_tenant_id'] as int? ??
          json['tenant_id'] as int? ??
          0,
      tenantName: json['tenant_name'] as String? ?? '',
      startDate: json['start_date'] as String? ?? '',
      endDate: json['end_date'] as String?,
      actualEndDate: json['actual_end_date'] as String?,
      roomPrice: json['room_price'] != null
          ? _toDouble(json['room_price'])
          : (json['rental_price'] != null
                ? _toDouble(json['rental_price'])
                : 0.0),
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
          ? Tenant.fromJson(
              json['representative_tenant'] as Map<String, dynamic>,
            )
          : null,
      room: json['room'] != null
          ? Room.fromJson(json['room'] as Map<String, dynamic>)
          : null,
      tenantSignedAt: json['tenant_signed_at'] as String?,
      tenantSignatureUrl: json['tenant_signature_url'] as String?,

      roomServices: json['room_services'] as List<dynamic>?,
      contractVehicles: json['contract_vehicles'] as List<dynamic>?,
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
      'contract_vehicles': contractVehicles,
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
