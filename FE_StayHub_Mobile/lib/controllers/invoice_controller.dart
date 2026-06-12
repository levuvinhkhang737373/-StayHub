import 'package:flutter/material.dart';
import '../models/invoice.dart';

class InvoiceController extends ChangeNotifier {
  final List<Invoice> _mockInvoices = [
    Invoice(
      id: 1,
      invoiceCode: 'HD-202605-101',
      contractId: 1,
      roomId: 1,
      roomNumber: '101',
      billingMonth: 5,
      billingYear: 2026,
      periodStart: '2026-05-01',
      periodEnd: '2026-05-31',
      previousDebtAmount: 0.0,
      totalAmount: 3850000,
      paidAmount: 0,
      remainingAmount: 3850000,
      dueDate: '2026-06-05',
      status: 2, // UNPAID
      issuedAt: '2026-05-28',
    ),
    Invoice(
      id: 2,
      invoiceCode: 'HD-202605-102',
      contractId: 2,
      roomId: 2,
      roomNumber: '102',
      billingMonth: 5,
      billingYear: 2026,
      periodStart: '2026-05-01',
      periodEnd: '2026-05-31',
      previousDebtAmount: 0.0,
      totalAmount: 4120000,
      paidAmount: 4120000,
      remainingAmount: 0,
      dueDate: '2026-06-05',
      status: 4, // PAID
      issuedAt: '2026-05-28',
    ),
    Invoice(
      id: 3,
      invoiceCode: 'HD-202605-201',
      contractId: 3,
      roomId: 5,
      roomNumber: '201',
      billingMonth: 5,
      billingYear: 2026,
      periodStart: '2026-05-01',
      periodEnd: '2026-05-31',
      previousDebtAmount: 0.0,
      totalAmount: 4500000,
      paidAmount: 1500000,
      remainingAmount: 3000000,
      dueDate: '2026-06-05',
      status: 3, // PARTIALLY PAID
      issuedAt: '2026-05-28',
    ),
    Invoice(
      id: 4,
      invoiceCode: 'HD-202604-101',
      contractId: 1,
      roomId: 1,
      roomNumber: '101',
      billingMonth: 4,
      billingYear: 2026,
      periodStart: '2026-04-01',
      periodEnd: '2026-04-30',
      previousDebtAmount: 0.0,
      totalAmount: 3700000,
      paidAmount: 3700000,
      remainingAmount: 0,
      dueDate: '2026-05-05',
      status: 4, // PAID
      issuedAt: '2026-04-28',
    ),
    Invoice(
      id: 5,
      invoiceCode: 'HD-202604-201',
      contractId: 3,
      roomId: 5,
      roomNumber: '201',
      billingMonth: 4,
      billingYear: 2026,
      periodStart: '2026-04-01',
      periodEnd: '2026-04-30',
      previousDebtAmount: 0.0,
      totalAmount: 4400000,
      paidAmount: 0,
      remainingAmount: 4400000,
      dueDate: '2026-05-05',
      status: 5, // OVERDUE
      issuedAt: '2026-04-28',
    ),
  ];

  final List<Invoice> _filteredInvoices = [];
  bool _isLoading = false;

  List<Invoice> get invoices => _filteredInvoices.isEmpty ? _mockInvoices : _filteredInvoices;
  bool get isLoading => _isLoading;

  /// Get count of unpaid invoices
  int get unpaidInvoicesCount {
    return _mockInvoices.where((i) => i.isUnpaid).length;
  }

  /// Fetch invoices for specific room (for Tenant view)
  List<Invoice> getInvoicesForRoom(String roomNumber) {
    return _mockInvoices.where((i) => i.roomNumber == roomNumber).toList();
  }

  /// Confirm payment of an invoice (Admin action)
  Future<bool> confirmPayment(int id) async {
    _isLoading = true;
    notifyListeners();

    await Future.delayed(const Duration(milliseconds: 500));

    final index = _mockInvoices.indexWhere((i) => i.id == id);
    if (index != -1) {
      final old = _mockInvoices[index];
      _mockInvoices[index] = Invoice(
        id: old.id,
        invoiceCode: old.invoiceCode,
        contractId: old.contractId,
        roomId: old.roomId,
        roomNumber: old.roomNumber,
        billingMonth: old.billingMonth,
        billingYear: old.billingYear,
        periodStart: old.periodStart,
        periodEnd: old.periodEnd,
        previousDebtAmount: old.previousDebtAmount,
        totalAmount: old.totalAmount,
        paidAmount: old.totalAmount,
        remainingAmount: 0,
        dueDate: old.dueDate,
        status: 4, // PAID
        issuedAt: old.issuedAt,
      );
      _isLoading = false;
      notifyListeners();
      return true;
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  /// Mock payment action (Tenant action)
  Future<bool> payInvoice(int id) async {
    return confirmPayment(id);
  }

  /// Send Debt Reminder notification mock
  Future<bool> sendDebtReminder(int id) async {
    _isLoading = true;
    notifyListeners();
    await Future.delayed(const Duration(milliseconds: 600));
    _isLoading = false;
    notifyListeners();
    return true;
  }
}
