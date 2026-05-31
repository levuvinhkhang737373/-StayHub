class Invoice {
  final int id;
  final String invoiceCode;
  final int contractId;
  final int roomId;
  final String roomNumber;
  final int billingMonth;
  final int billingYear;
  final String periodStart;
  final String periodEnd;
  final double previousDebtAmount;
  final double totalAmount;
  final double paidAmount;
  final double remainingAmount;
  final String dueDate;
  final int status; // 1: Draft, 2: Unpaid, 3: Partially Paid, 4: Paid, 5: Overdue, 6: Cancelled
  final String? issuedAt;
  final int? createdBy;

  Invoice({
    required this.id,
    required this.invoiceCode,
    required this.contractId,
    required this.roomId,
    required this.roomNumber,
    required this.billingMonth,
    required this.billingYear,
    required this.periodStart,
    required this.periodEnd,
    required this.previousDebtAmount,
    required this.totalAmount,
    required this.paidAmount,
    required this.remainingAmount,
    required this.dueDate,
    required this.status,
    this.issuedAt,
    this.createdBy,
  });

  factory Invoice.fromJson(Map<String, dynamic> json) {
    return Invoice(
      id: json['id'] as int,
      invoiceCode: json['invoice_code'] as String? ?? '',
      contractId: json['contract_id'] as int? ?? 0,
      roomId: json['room_id'] as int? ?? 0,
      roomNumber: json['room_number'] as String? ?? '',
      billingMonth: json['billing_month'] as int? ?? 1,
      billingYear: json['billing_year'] as int? ?? 2026,
      periodStart: json['period_start'] as String? ?? '',
      periodEnd: json['period_end'] as String? ?? '',
      previousDebtAmount: (json['previous_debt_amount'] as num? ?? 0.0).toDouble(),
      totalAmount: (json['total_amount'] as num? ?? 0.0).toDouble(),
      paidAmount: (json['paid_amount'] as num? ?? 0.0).toDouble(),
      remainingAmount: (json['remaining_amount'] as num? ?? 0.0).toDouble(),
      dueDate: json['due_date'] as String? ?? '',
      status: json['status'] as int? ?? 2,
      issuedAt: json['issued_at'] as String?,
      createdBy: json['created_by'] as int?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'invoice_code': invoiceCode,
      'contract_id': contractId,
      'room_id': roomId,
      'room_number': roomNumber,
      'billing_month': billingMonth,
      'billing_year': billingYear,
      'period_start': periodStart,
      'period_end': periodEnd,
      'previous_debt_amount': previousDebtAmount,
      'total_amount': totalAmount,
      'paid_amount': paidAmount,
      'remaining_amount': remainingAmount,
      'due_date': dueDate,
      'status': status,
      'issued_at': issuedAt,
      'created_by': createdBy,
    };
  }

  String get statusLabel {
    switch (status) {
      case 1:
        return 'Nháp';
      case 2:
        return 'Chưa thanh toán';
      case 3:
        return 'Thanh toán 1 phần';
      case 4:
        return 'Đã thanh toán';
      case 5:
        return 'Quá hạn';
      case 6:
      default:
        return 'Đã hủy';
    }
  }

  bool get isUnpaid => status == 2 || status == 3 || status == 5;
  bool get isOverdue => status == 5;
  bool get isPaid => status == 4;
}
