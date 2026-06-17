import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import '../models/invoice.dart';
import '../services/api_service.dart';

class InvoiceController extends ChangeNotifier {
  final ApiService _apiService = ApiService();

  List<Invoice> _invoices = [];
  bool _isLoading = false;
  String? _errorMessage;

  List<Invoice> get invoices => _invoices;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;

  /// Get count of unpaid invoices
  int get unpaidInvoicesCount {
    return _invoices.where((i) => i.isUnpaid).length;
  }

  /// Fetch invoices for specific room (for Tenant view)
  List<Invoice> getInvoicesForRoom(String roomNumber) {
    // Both Admin and Tenant search can filter by room, but
    // for tenant we fetch their room's invoices from `/tenant/invoices` anyway.
    return _invoices;
  }

  /// Clear the error message
  void clearError() {
    _errorMessage = null;
    notifyListeners();
  }

  /// Fetch invoices from the API
  Future<void> fetchInvoices({required bool isAdmin}) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final path = isAdmin ? '/admin/invoices' : '/tenant/invoices';
      final response = await _apiService.get<List<dynamic>>(
        path,
        fromJsonT: (json) {
          if (json is Map && json.containsKey('data')) {
            return json['data'] as List<dynamic>;
          }
          return json as List<dynamic>;
        },
      );

      if (response.status && response.result != null) {
        _invoices = response.result!
            .map((item) => Invoice.fromJson(item as Map<String, dynamic>))
            .toList();
      } else {
        _errorMessage = response.message;
      }
    } catch (e) {
      _errorMessage = 'Lỗi tải danh sách hóa đơn: $e';
    }

    _isLoading = false;
    notifyListeners();
  }

  /// Confirm payment of an invoice (Admin action)
  Future<bool> confirmPayment(int id) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final invoice = _invoices.firstWhere((i) => i.id == id);
      final response = await _apiService.post<dynamic>(
        '/admin/invoices/$id/payments',
        data: {
          'amount': invoice.remainingAmount.toString(),
          'payment_method': 1, // Cash
          'note': 'Ghi nhận thanh toán thủ công bằng tiền mặt từ Mobile App',
        },
        fromJsonT: (json) => json,
      );

      if (response.status) {
        // Refresh invoices list
        await fetchInvoices(isAdmin: true);
        return true;
      } else {
        _errorMessage = response.message;
      }
    } catch (e) {
      if (e is ApiException) {
        _errorMessage = e.message;
      } else {
        _errorMessage = 'Lỗi xác nhận thanh toán: $e';
      }
    } finally {
      _isLoading = false;
      notifyListeners();
    }
    return false;
  }

  /// Upload payment proof image (Tenant action)
  Future<bool> uploadPaymentProof({
    required int invoiceId,
    required double amount,
    String? transactionReference,
    String? note,
    required String imagePath,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final fileName = imagePath.split('/').last;
      final formData = FormData.fromMap({
        'amount': amount.toString(),
        if (transactionReference != null && transactionReference.isNotEmpty)
          'transaction_reference': transactionReference,
        if (note != null && note.isNotEmpty)
          'note': note,
        'proof_image': await MultipartFile.fromFile(
          imagePath,
          filename: fileName,
        ),
      });

      final response = await _apiService.post<dynamic>(
        '/tenant/invoices/$invoiceId/payment-proof',
        data: formData,
        fromJsonT: (json) => json,
      );

      if (response.status) {
        // Refresh invoices list
        await fetchInvoices(isAdmin: false);
        return true;
      } else {
        _errorMessage = response.message;
      }
    } catch (e) {
      if (e is ApiException) {
        _errorMessage = e.message;
      } else {
        _errorMessage = 'Lỗi tải ảnh minh chứng: $e';
      }
    } finally {
      _isLoading = false;
      notifyListeners();
    }
    return false;
  }

  /// Pay invoice directly (Tenant action, if we just mock or handle it)
  Future<bool> payInvoice(int id) async {
    // For tenant, they must upload a proof, they don't just "confirm payment"
    // So we return false here; they should use uploadPaymentProof.
    return false;
  }

  /// Send Debt Reminder notification (Admin action)
  Future<bool> sendDebtReminder(int id) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final invoice = _invoices.firstWhere((i) => i.id == id);
      final response = await _apiService.post<dynamic>(
        '/admin/notifications',
        data: {
          'title': 'Nhắc nhở thanh toán hóa đơn',
          'content': 'StayHub nhắc nhở thanh toán hóa đơn ${invoice.invoiceCode}. Số tiền cần đóng: ${invoice.remainingAmount.toStringAsFixed(0)} VNĐ. Hạn chót: ${invoice.dueDate}.',
          'notification_type': 1, // 1: Invoice Notification
          'target_type': 3, // 3: Single Tenant
          'room_id': invoice.roomId,
          'status': 2, // SENT
        },
        fromJsonT: (json) => json,
      );

      if (response.status) {
        _isLoading = false;
        notifyListeners();
        return true;
      } else {
        _errorMessage = response.message;
      }
    } catch (e) {
      if (e is ApiException) {
        _errorMessage = e.message;
      } else {
        _errorMessage = 'Lỗi nhắc nợ: $e';
      }
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  /// Handle WebSocket updates: updates or inserts an invoice
  void updateInvoiceRealtime(Map<String, dynamic> invoiceJson) {
    try {
      final updatedInvoice = Invoice.fromJson(invoiceJson);
      final index = _invoices.indexWhere((i) => i.id == updatedInvoice.id);
      if (index != -1) {
        _invoices[index] = updatedInvoice;
      } else {
        _invoices.insert(0, updatedInvoice);
      }
      notifyListeners();
    } catch (e) {
      debugPrint('Error updating invoice realtime: $e');
    }
  }
}
