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
  final String? paymentQrUrl;
  final List<InvoiceItem>? items;

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
    this.paymentQrUrl,
    this.items,
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
      previousDebtAmount: json['previous_debt_amount'] != null ? (double.tryParse(json['previous_debt_amount'].toString()) ?? 0.0) : 0.0,
      totalAmount: json['total_amount'] != null ? (double.tryParse(json['total_amount'].toString()) ?? 0.0) : 0.0,
      paidAmount: json['paid_amount'] != null ? (double.tryParse(json['paid_amount'].toString()) ?? 0.0) : 0.0,
      remainingAmount: json['remaining_amount'] != null ? (double.tryParse(json['remaining_amount'].toString()) ?? 0.0) : 0.0,
      dueDate: json['due_date'] as String? ?? '',
      status: json['status'] as int? ?? 2,
      issuedAt: json['issued_at'] as String?,
      createdBy: json['created_by'] as int?,
      paymentQrUrl: json['payment_qr_url'] as String?,
      items: json['items'] != null
          ? (json['items'] as List).map((i) => InvoiceItem.fromJson(i as Map<String, dynamic>)).toList()
          : null,
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
      'payment_qr_url': paymentQrUrl,
      'items': items?.map((i) => i.toJson()).toList(),
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

class InvoiceItem {
  final int id;
  final int invoiceId;
  final int? serviceId;
  final int? meterReadingId;
  final int itemType; // 1: Room Price, 2: Service, 3: Debt, 4: Discount, 5: Other
  final String description;
  final double quantity;
  final double unitPrice;
  final double amount;
  final String? createdAt;
  final String? updatedAt;

  InvoiceItem({
    required this.id,
    required this.invoiceId,
    this.serviceId,
    this.meterReadingId,
    required this.itemType,
    required this.description,
    required this.quantity,
    required this.unitPrice,
    required this.amount,
    this.createdAt,
    this.updatedAt,
  });

  factory InvoiceItem.fromJson(Map<String, dynamic> json) {
    return InvoiceItem(
      id: json['id'] as int,
      invoiceId: json['invoice_id'] as int? ?? 0,
      serviceId: json['service_id'] as int?,
      meterReadingId: json['meter_reading_id'] as int?,
      itemType: json['item_type'] as int? ?? 1,
      description: json['description'] as String? ?? '',
      quantity: json['quantity'] != null ? double.tryParse(json['quantity'].toString()) ?? 0.0 : 0.0,
      unitPrice: json['unit_price'] != null ? double.tryParse(json['unit_price'].toString()) ?? 0.0 : 0.0,
      amount: json['amount'] != null ? double.tryParse(json['amount'].toString()) ?? 0.0 : 0.0,
      createdAt: json['created_at'] as String?,
      updatedAt: json['updated_at'] as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'invoice_id': invoiceId,
      'service_id': serviceId,
      'meter_reading_id': meterReadingId,
      'item_type': itemType,
      'description': description,
      'quantity': quantity,
      'unit_price': unitPrice,
      'amount': amount,
      'created_at': createdAt,
      'updated_at': updatedAt,
    };
  }
}
