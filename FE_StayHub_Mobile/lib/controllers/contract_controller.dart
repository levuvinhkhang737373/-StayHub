import 'package:flutter/material.dart';
import 'package:dio/dio.dart' as dio;
import '../models/contract.dart';
import '../services/api_service.dart';

class ContractController extends ChangeNotifier {
  final ApiService _apiService = ApiService();

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
      status: Contract.STATUS_ACTIVE,
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
      status: Contract.STATUS_ACTIVE,
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
      status: Contract.STATUS_EXPIRED,
    ),
  ];

  List<Contract>? _realContracts;
  bool _isLoading = false;
  String? _errorMessage;

  List<Contract> get contracts => _realContracts ?? [];
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;

  /// Clear the error message
  void clearError() {
    _errorMessage = null;
    notifyListeners();
  }

  /// Get count of contracts expiring within 30 days
  int get expiringContractsCount {
    final activeContracts = contracts.where((c) => c.status == Contract.STATUS_ACTIVE).toList();
    int count = 0;
    final now = DateTime.now();
    final thirtyDaysLater = now.add(const Duration(days: 30));
    for (var c in activeContracts) {
      if (c.endDate != null) {
        try {
          final end = DateTime.parse(c.endDate!);
          if (end.isAfter(now) && end.isBefore(thirtyDaysLater)) {
            count++;
          }
        } catch (_) {}
      }
    }
    return count;
  }

  /// Fetch contracts from real API
  Future<void> fetchContracts(String role) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      await _apiService.init();
      if (role == 'admin') {
        final response = await _apiService.get<List<Contract>>(
          '/admin/contracts',
          fromJsonT: (json) {
            final dataList = json['data'] as List<dynamic>;
            return dataList.map((item) => Contract.fromJson(item as Map<String, dynamic>)).toList();
          },
        );

        if (response.status && response.result != null) {
          _realContracts = response.result;
        } else {
          _errorMessage = response.message;
        }
      } else if (role == 'tenant') {
        final response = await _apiService.get<List<Contract>>(
          '/tenant/contracts',
          fromJsonT: (json) {
            final dataList = json as List<dynamic>;
            return dataList.map((item) => Contract.fromJson(item as Map<String, dynamic>)).toList();
          },
        );

        if (response.status && response.result != null) {
          _realContracts = response.result;
        } else {
          _errorMessage = response.message;
          _realContracts = [];
        }
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (e) {
      _errorMessage = 'Lỗi kết nối API: $e';
    }

    _isLoading = false;
    notifyListeners();
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
    _errorMessage = null;
    notifyListeners();

    try {
      await _apiService.init();

      // 1. Fetch room to find corresponding room_id
      final roomResponse = await _apiService.get<List<dynamic>>(
        '/admin/rooms',
        fromJsonT: (json) => json as List<dynamic>,
      );

      int? roomId;
      if (roomResponse.status && roomResponse.result != null) {
        for (var r in roomResponse.result!) {
          if (r['room_number']?.toString() == roomNumber) {
            roomId = r['id'] as int?;
            break;
          }
        }
      }

      if (roomId == null) {
        _errorMessage = 'Không tìm thấy phòng số $roomNumber trong hệ thống.';
        _isLoading = false;
        notifyListeners();
        return false;
      }

      // 2. Fetch tenant to find corresponding tenant_id
      final tenantResponse = await _apiService.get<List<dynamic>>(
        '/admin/tenants',
        fromJsonT: (json) => json['data'] as List<dynamic>,
      );

      int? tenantId;
      if (tenantResponse.status && tenantResponse.result != null) {
        for (var t in tenantResponse.result!) {
          if (t['full_name']?.toString().toLowerCase().trim() == tenantName.toLowerCase().trim()) {
            tenantId = t['id'] as int?;
            break;
          }
        }
      }

      if (tenantId == null) {
        _errorMessage = 'Không tìm thấy khách thuê "$tenantName" trong hệ thống.';
        _isLoading = false;
        notifyListeners();
        return false;
      }

      // 3. Post contract to API
      final payload = {
        if (contractCode.isNotEmpty) 'contract_code': contractCode,
        'room_id': roomId,
        'start_date': startDate,
        'end_date': endDate,
        'billing_cycle_day': 5,
        'room_price': rentalPrice.toStringAsFixed(2),
        'deposit_amount': depositAmount.toStringAsFixed(2),
        'tenants': [
          {
            'tenant_id': tenantId,
            'join_date': startDate,
            'is_staying': true,
          }
        ],
        'is_deposit_paid': true,
      };

      final response = await _apiService.post<Map<String, dynamic>>(
        '/admin/contracts',
        data: payload,
        fromJsonT: (json) => json as Map<String, dynamic>,
      );

      if (response.status) {
        await fetchContracts('admin');
        return true;
      } else {
        _errorMessage = response.message;
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (e) {
      _errorMessage = 'Lỗi kết nối API: $e';
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  /// Update contract details (Admin action)
  Future<bool> updateContract(Contract contract) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      await _apiService.init();
      final payload = {
        'room_id': contract.roomId,
        'start_date': contract.startDate,
        'end_date': contract.endDate,
        'room_price': contract.roomPrice.toStringAsFixed(2),
        'deposit_amount': contract.depositAmount.toStringAsFixed(2),
        'note': contract.note,
      };

      final response = await _apiService.put<Map<String, dynamic>>(
        '/admin/contracts/${contract.id}',
        data: payload,
        fromJsonT: (json) => json as Map<String, dynamic>,
      );

      if (response.status) {
        await fetchContracts('admin');
        return true;
      } else {
        _errorMessage = response.message;
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (e) {
      _errorMessage = 'Lỗi kết nối API: $e';
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  /// Extend a contract (Gia hạn)
  Future<bool> extendContract(int id, String newEndDate) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      await _apiService.init();

      // 1. Fetch details of contract to be extended
      final responseDetail = await _apiService.get<Map<String, dynamic>>(
        '/admin/contracts/$id',
        fromJsonT: (json) => json as Map<String, dynamic>,
      );

      if (!responseDetail.status || responseDetail.result == null) {
        _errorMessage = 'Không lấy được chi tiết hợp đồng: ${responseDetail.message}';
        _isLoading = false;
        notifyListeners();
        return false;
      }

      final contractData = responseDetail.result!;
      final double roomPrice = double.tryParse(contractData['room_price']?.toString() ?? '0') ?? 0.0;
      final double depositAmount = double.tryParse(contractData['deposit_amount']?.toString() ?? '0') ?? 0.0;
      final int roomId = contractData['room_id'] as int? ?? 0;
      final int billingCycleDay = contractData['billing_cycle_day'] as int? ?? 5;
      
      final oldEndDateStr = contractData['end_date'] as String?;
      String newStartDate = DateTime.now().toString().split(' ')[0]; // default today
      if (oldEndDateStr != null && oldEndDateStr.isNotEmpty) {
        try {
          final oldEndDate = DateTime.parse(oldEndDateStr);
          final nextDay = oldEndDate.add(const Duration(days: 1));
          newStartDate = nextDay.toString().split(' ')[0];
        } catch (_) {}
      }

      final tenantsList = contractData['contract_tenants'] as List<dynamic>? ?? [];
      final List<Map<String, dynamic>> mappedTenants = tenantsList.map((ct) {
        return {
          'tenant_id': ct['tenant_id'],
          'join_date': newStartDate,
          'is_staying': true,
        };
      }).toList();

      if (mappedTenants.isEmpty) {
        _errorMessage = 'Không tìm thấy khách thuê trong hợp đồng cũ để gia hạn.';
        _isLoading = false;
        notifyListeners();
        return false;
      }

      final renewPayload = {
        'room_id': roomId,
        'start_date': newStartDate,
        'end_date': newEndDate,
        'billing_cycle_day': billingCycleDay,
        'room_price': roomPrice.toStringAsFixed(2),
        'deposit_amount': depositAmount.toStringAsFixed(2),
        'tenants': mappedTenants,
      };

      final renewResponse = await _apiService.post<Map<String, dynamic>>(
        '/admin/contracts/$id/renew',
        data: renewPayload,
        fromJsonT: (json) => json as Map<String, dynamic>,
      );

      if (renewResponse.status) {
        await fetchContracts('admin');
        return true;
      } else {
        _errorMessage = renewResponse.message;
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (e) {
      _errorMessage = 'Lỗi kết nối API: $e';
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  /// Terminate a contract (Kết thúc)
  Future<bool> terminateContract(int id) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      await _apiService.init();
      final todayStr = DateTime.now().toString().split(' ')[0];
      final payload = {
        'status': Contract.STATUS_LIQUIDATED,
        'actual_end_date': todayStr,
        'note': 'Chấm dứt hợp đồng qua Mobile App',
      };

      final response = await _apiService.patch<Map<String, dynamic>>(
        '/admin/contracts/$id/status',
        data: payload,
        fromJsonT: (json) => json as Map<String, dynamic>,
      );

      if (response.status) {
        await fetchContracts('admin');
        return true;
      } else {
        _errorMessage = response.message;
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (e) {
      _errorMessage = 'Lỗi kết nối API: $e';
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  /// Schedule room transfer (only first day of next month, backend validates date)
  Future<bool> scheduleRoomTransfer({
    required int contractId,
    required String newRoomNumber,
    required String movementDate,
    double depositDeductionAmount = 0,
    double transferFee = 0,
    double? newDepositAmount,
    String? note,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      await _apiService.init();

      // 1. Fetch room list to find the room_id matching newRoomNumber
      final roomResponse = await _apiService.get<List<dynamic>>(
        '/admin/rooms',
        queryParameters: {'per_page': 1000},
        fromJsonT: (json) {
          if (json is List<dynamic>) return json;
          if (json is Map<String, dynamic> && json['data'] is List<dynamic>) return json['data'] as List<dynamic>;
          return <dynamic>[];
        },
      );

      int? roomId;
      if (roomResponse.status && roomResponse.result != null) {
        for (var r in roomResponse.result!) {
          if (r['room_number']?.toString() == newRoomNumber) {
            roomId = r['id'] as int?;
            break;
          }
        }
      }

      if (roomId == null) {
        _errorMessage = 'Không tìm thấy phòng số $newRoomNumber trong hệ thống.';
        _isLoading = false;
        notifyListeners();
        return false;
      }

      final responseDetail = await _apiService.get<Map<String, dynamic>>(
        '/admin/contracts/$contractId',
        fromJsonT: (json) => json as Map<String, dynamic>,
      );

      if (!responseDetail.status || responseDetail.result == null) {
        _errorMessage = responseDetail.message.isNotEmpty ? responseDetail.message : 'Không thể tải hợp đồng cũ để lấy danh sách khách thuê.';
        _isLoading = false;
        notifyListeners();
        return false;
      }

      final contractData = responseDetail.result!;
      final tenantsList = contractData['contract_tenants'] as List<dynamic>? ?? [];
      final tenantIds = <int>{};

      for (final row in tenantsList) {
        if (row is! Map<String, dynamic>) continue;
        if (row['is_staying'] == false) continue;

        final tenantId = row['tenant_id'] as int?;
        if (tenantId != null && tenantId > 0) {
          tenantIds.add(tenantId);
        }
      }

      final representativeTenantId = contractData['representative_tenant_id'] as int? ?? contractData['tenant_id'] as int?;
      if (tenantIds.isEmpty && representativeTenantId != null && representativeTenantId > 0) {
        tenantIds.add(representativeTenantId);
      }

      if (tenantIds.isEmpty) {
        _errorMessage = 'Không tìm thấy khách thuê đang ở trong hợp đồng cũ để lên lịch chuyển phòng.';
        _isLoading = false;
        notifyListeners();
        return false;
      }

      final payload = {
        'tenant_ids': tenantIds.toList(),
        'to_room_id': roomId,
        'movement_date': movementDate,
        'deposit_deduction_amount': depositDeductionAmount.toStringAsFixed(2),
        'transfer_fee': transferFee.toStringAsFixed(2),
        if (newDepositAmount != null) 'new_deposit_amount': newDepositAmount.toStringAsFixed(2),
        if (note != null && note.trim().isNotEmpty) 'note': note.trim(),
      };

      final response = await _apiService.post<Map<String, dynamic>>(
        '/admin/room-transfers/tenant',
        data: payload,
        fromJsonT: (json) => json as Map<String, dynamic>,
      );

      if (response.status) {
        await fetchContracts('admin');
        return true;
      } else {
        _errorMessage = response.message;
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (e) {
      _errorMessage = 'Lỗi kết nối API: $e';
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  /// Sign contract (Tenant action)
  Future<bool> signContract({
    required int contractId,
    required String fullName,
    required String identityNumber,
    required int identityType,
    required String identityDate,
    required String identityPlace,
    required String permanentAddress,
    required List<int> signatureBytes,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      await _apiService.init();

      final formData = dio.FormData.fromMap({
        'full_name': fullName,
        'identity_number': identityNumber,
        'identity_type': identityType,
        'identity_date': identityDate,
        'identity_place': identityPlace,
        'permanent_address': permanentAddress,
        'signature_file': dio.MultipartFile.fromBytes(
          signatureBytes,
          filename: 'signature.png',
        ),
      });

      final response = await _apiService.post<Map<String, dynamic>>(
        '/tenant/contracts/$contractId/sign',
        data: formData,
        fromJsonT: (json) => json as Map<String, dynamic>,
      );

      if (response.status) {
        await fetchContracts('tenant');
        return true;
      } else {
        _errorMessage = response.message;
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (e) {
      _errorMessage = 'Lỗi kết nối API: $e';
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
