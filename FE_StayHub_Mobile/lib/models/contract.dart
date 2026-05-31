class Contract {
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
  final int status; // 1: Active, 2: Expired, 3: Terminated, 4: Draft
  final List<String>? contractFiles;
  final String? note;
  final int? createdBy;

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
  });

  // Alias fields for backward compatibility
  int get tenantId => representativeTenantId;
  double get rentalPrice => roomPrice;

  factory Contract.fromJson(Map<String, dynamic> json) {
    // Deserialize contract files if present
    List<String>? files;
    if (json['contract_files'] != null) {
      if (json['contract_files'] is List) {
        files = (json['contract_files'] as List).map((f) => f.toString()).toList();
      }
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
      roomPrice: (json['room_price'] as num? ?? json['rental_price'] as num? ?? 0.0).toDouble(),
      depositAmount: (json['deposit_amount'] as num? ?? 0.0).toDouble(),
      status: json['status'] as int? ?? 1,
      contractFiles: files,
      note: json['note'] as String?,
      createdBy: json['created_by'] as int?,
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
    };
  }

  String get statusLabel {
    switch (status) {
      case 1:
        return 'Hiệu lực';
      case 2:
        return 'Hết hạn';
      case 3:
        return 'Đã kết thúc';
      case 4:
      default:
        return 'Bản nháp';
    }
  }

  bool get isActive => status == 1;
}
