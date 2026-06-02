import 'package:flutter/material.dart';
import '../models/contract.dart';

class ContractController extends ChangeNotifier {
  final List<Contract> _mockContracts = [
    InvoiceContract(
      id: 1,
      contractCode: 'HD-101-2026',
      roomId: 1,
      roomNumber: '101',
      representativeTenantId: 1,
      tenantName: 'Nguyễn Văn An',
      startDate: '2026-01-01',
      endDate: '2026-06-30',
      billingCycleDay: 5,
      roomPrice: 3500000,
      depositAmount: 3500000,
      status: 2, // ACTIVE (Expiring soon)
    ),
    InvoiceContract(
      id: 2,
      contractCode: 'HD-102-2026',
      roomId: 2,
      roomNumber: '102',
      representativeTenantId: 2,
      tenantName: 'Trần Thị Bình',
      startDate: '2026-02-01',
      endDate: '2026-08-01',
      billingCycleDay: 5,
      roomPrice: 3500000,
      depositAmount: 3500000,
      status: 2, // ACTIVE
    ),
    InvoiceContract(
      id: 3,
      contractCode: 'HD-201-2025',
      roomId: 5,
      roomNumber: '201',
      representativeTenantId: 3,
      tenantName: 'Lê Hoàng Cường',
      startDate: '2025-05-01',
      endDate: '2026-05-01',
      billingCycleDay: 5,
      roomPrice: 3800000,
      depositAmount: 3800000,
      status: 3, // EXPIRED
    ),
  ];

  bool _isLoading = false;

  List<Contract> get contracts => _mockContracts;
  bool get isLoading => _isLoading;

  /// Get count of contracts expiring within 30 days
  int get expiringContractsCount {
    return 1;
  }

  /// Create a new contract (Admin action)
  Future<bool> createContract({
    required String contractCode,
    required String roomNumber,
    required String tenantName,
    required String startDate,
    required String endDate,
    required double rentalPrice,
    required double depositAmount,
  }) async {
    _isLoading = true;
    notifyListeners();

    await Future.delayed(const Duration(milliseconds: 500));

    final newContract = InvoiceContract(
      id: _mockContracts.length + 1,
      contractCode: contractCode,
      roomId: 1,
      roomNumber: roomNumber,
      representativeTenantId: 1,
      tenantName: tenantName,
      startDate: startDate,
      endDate: endDate,
      billingCycleDay: 5,
      roomPrice: rentalPrice,
      depositAmount: depositAmount,
      status: 2, // ACTIVE
    );

    _mockContracts.insert(0, newContract);
    _isLoading = false;
    notifyListeners();
    return true;
  }

  /// Update contract details (Admin action)
  Future<bool> updateContract(Contract contract) async {
    _isLoading = true;
    notifyListeners();

    await Future.delayed(const Duration(milliseconds: 500));

    final index = _mockContracts.indexWhere((c) => c.id == contract.id);
    if (index != -1) {
      _mockContracts[index] = contract;
      _isLoading = false;
      notifyListeners();
      return true;
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  /// Extend a contract (Gia hạn)
  Future<bool> extendContract(int id, String newEndDate) async {
    _isLoading = true;
    notifyListeners();
    await Future.delayed(const Duration(milliseconds: 500));

    final index = _mockContracts.indexWhere((c) => c.id == id);
    if (index != -1) {
      final old = _mockContracts[index];
      _mockContracts[index] = InvoiceContract(
        id: old.id,
        contractCode: old.contractCode,
        roomId: old.roomId,
        roomNumber: old.roomNumber,
        representativeTenantId: old.representativeTenantId,
        tenantName: old.tenantName,
        startDate: old.startDate,
        endDate: newEndDate,
        billingCycleDay: old.billingCycleDay,
        roomPrice: old.roomPrice,
        depositAmount: old.depositAmount,
        status: 2, // Reset to ACTIVE
      );
      _isLoading = false;
      notifyListeners();
      return true;
    }
    _isLoading = false;
    notifyListeners();
    return false;
  }

  /// Terminate a contract (Kết thúc)
  Future<bool> terminateContract(int id) async {
    _isLoading = true;
    notifyListeners();
    await Future.delayed(const Duration(milliseconds: 500));

    final index = _mockContracts.indexWhere((c) => c.id == id);
    if (index != -1) {
      final old = _mockContracts[index];
      _mockContracts[index] = InvoiceContract(
        id: old.id,
        contractCode: old.contractCode,
        roomId: old.roomId,
        roomNumber: old.roomNumber,
        representativeTenantId: old.representativeTenantId,
        tenantName: old.tenantName,
        startDate: old.startDate,
        endDate: old.endDate,
        actualEndDate: old.endDate,
        billingCycleDay: old.billingCycleDay,
        roomPrice: old.roomPrice,
        depositAmount: old.depositAmount,
        status: 4, // TERMINATED / LIQUIDATED
      );
      _isLoading = false;
      notifyListeners();
      return true;
    }
    _isLoading = false;
    notifyListeners();
    return false;
  }

  /// Change room (Chuyển phòng)
  Future<bool> changeRoom(int id, String newRoomNumber, double newRentalPrice) async {
    _isLoading = true;
    notifyListeners();
    await Future.delayed(const Duration(milliseconds: 500));

    final index = _mockContracts.indexWhere((c) => c.id == id);
    if (index != -1) {
      final old = _mockContracts[index];
      _mockContracts[index] = InvoiceContract(
        id: old.id,
        contractCode: old.contractCode,
        roomId: old.roomId,
        roomNumber: newRoomNumber,
        representativeTenantId: old.representativeTenantId,
        tenantName: old.tenantName,
        startDate: old.startDate,
        endDate: old.endDate,
        billingCycleDay: old.billingCycleDay,
        roomPrice: newRentalPrice,
        depositAmount: old.depositAmount,
        status: old.status,
      );
      _isLoading = false;
      notifyListeners();
      return true;
    }
    _isLoading = false;
    notifyListeners();
    return false;
  }
}

// Rename class internal helper so we avoid compiler warnings about duplicate declarations
class InvoiceContract extends Contract {
  InvoiceContract({
    required super.id,
    required super.contractCode,
    required super.roomId,
    required super.roomNumber,
    required super.representativeTenantId,
    required super.tenantName,
    required super.startDate,
    super.endDate,
    super.actualEndDate,
    required super.billingCycleDay,
    required super.roomPrice,
    required super.depositAmount,
    required super.status,
    super.contractFiles,
    super.note,
    super.createdBy,
  });
}
