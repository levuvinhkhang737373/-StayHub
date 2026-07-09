import 'package:flutter/material.dart';
import '../models/tenant.dart';
import '../services/api_service.dart';

class TenantController extends ChangeNotifier {
  final ApiService _apiService = ApiService();
  
  final List<Tenant> _mockTenants = [
    Tenant(
      id: 1,
      fullName: 'Nguyễn Văn An',
      gender: 1,
      phone: '0912345678',
      email: 'vanan@gmail.com',
      username: 'vanan123',
      permanentAddress: 'Hải Phòng',
      currentAddress: 'Phòng 101, StayHub Sài Gòn Q1',
      status: 1,
      roomNumber: '101',
      buildingName: 'StayHub Sài Gòn Q1',
      identityType: 1,
      identityNumber: '012345678901',
    ),
    Tenant(
      id: 2,
      fullName: 'Trần Thị Bình',
      gender: 2,
      phone: '0987654321',
      email: 'thibinh@gmail.com',
      username: 'thibinh',
      permanentAddress: 'Nam Định',
      currentAddress: 'Phòng 102, StayHub Sài Gòn Q1',
      status: 1,
      roomNumber: '102',
      buildingName: 'StayHub Sài Gòn Q1',
      identityType: 1,
      identityNumber: '012345678902',
    ),
    Tenant(
      id: 3,
      fullName: 'Lê Hoàng Cường',
      gender: 1,
      phone: '0905111222',
      email: 'hoangcuong@gmail.com',
      username: 'cuongle',
      permanentAddress: 'Quảng Nam',
      currentAddress: 'Phòng 201, StayHub Sài Gòn Q3',
      status: 1,
      roomNumber: '201',
      buildingName: 'StayHub Sài Gòn Q3',
      identityType: 1,
      identityNumber: '012345678903',
    ),
    Tenant(
      id: 4,
      fullName: 'Phạm Minh Đức',
      gender: 1,
      phone: '0977444555',
      email: 'minhduc@gmail.com',
      username: 'ducpham',
      permanentAddress: 'Thanh Hóa',
      currentAddress: 'Trước đây thuê Phòng 101',
      status: 2,
      roomNumber: '101',
      buildingName: 'StayHub Sài Gòn Q1',
      identityType: 1,
      identityNumber: '012345678904',
    ),
  ];

  List<Tenant>? _realTenants; // null means not fetched yet, [] means fetched but empty
  List<Tenant> _filteredTenants = [];
  bool _isLoading = false;
  String _searchQuery = '';
  String? _errorMessage;

  List<Tenant> get tenants {
    return _filteredTenants;
  }
      
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;

  TenantController() {
    _filteredTenants = List.from(_mockTenants);
  }

  /// Tải danh sách khách thuê thực tế từ API
  Future<void> fetchTenants() async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _apiService.get<List<dynamic>>(
        '/admin/tenants',
        fromJsonT: (json) => json['data'] as List<dynamic>,
      );

      if (response.status && response.result != null) {
        _realTenants = response.result!
            .map((item) => Tenant.fromJson(item as Map<String, dynamic>))
            .toList();
        search(_searchQuery); // Cập nhật danh sách hiển thị
      } else {
        _errorMessage = response.message;
      }
    } catch (e) {
      _errorMessage = 'Lỗi tải danh sách khách thuê: $e';
    }

    _isLoading = false;
    notifyListeners();
  }

  /// Search/filter tenants by name or phone or room
  void search(String query) {
    _searchQuery = query.toLowerCase();
    final list = _realTenants;
    final sourceList = list ?? _mockTenants;
    if (_searchQuery.isEmpty) {
      _filteredTenants = List.from(sourceList);
    } else {
      _filteredTenants = sourceList.where((t) {
        return t.fullName.toLowerCase().contains(_searchQuery) ||
            t.phone.contains(_searchQuery) ||
            (t.roomNumber != null && t.roomNumber!.contains(_searchQuery));
      }).toList();
    }
    notifyListeners();
  }

  Future<bool> updateStatus(int id, int status) async {
    _isLoading = true;
    notifyListeners();

    bool success = false;
    try {
      final response = await _apiService.patch<dynamic>(
        '/admin/tenants/$id/status',
        data: {
          'status': status,
          'reason': status == 1 ? 'Kích hoạt thuê lại từ Mobile App' : 'Ngừng thuê từ Mobile App',
        },
        fromJsonT: (json) => json,
      );
      if (response.status) {
        success = true;
      }
    } catch (_) {}

    try {
      final index = _mockTenants.indexWhere((t) => t.id == id);
      if (index != -1) {
        final oldTenant = _mockTenants[index];
        _mockTenants[index] = Tenant(
          id: oldTenant.id,
          buildingId: oldTenant.buildingId,
          fullName: oldTenant.fullName,
          gender: oldTenant.gender,
          dateOfBirth: oldTenant.dateOfBirth,
          phone: oldTenant.phone,
          email: oldTenant.email,
          username: oldTenant.username,
          permanentAddress: oldTenant.permanentAddress,
          currentAddress: oldTenant.currentAddress,
          avatarUrl: oldTenant.avatarUrl,
          status: status,
          roomNumber: oldTenant.roomNumber,
          buildingName: oldTenant.buildingName,
          identityType: oldTenant.identityType,
          identityNumber: oldTenant.identityNumber,
          frontImageUrl: oldTenant.frontImageUrl,
          backImageUrl: oldTenant.backImageUrl,
        );
      }
      
      final list = _realTenants;
      if (list != null) {
        final realIndex = list.indexWhere((t) => t.id == id);
        if (realIndex != -1) {
          final oldTenant = list[realIndex];
          list[realIndex] = Tenant(
            id: oldTenant.id,
            buildingId: oldTenant.buildingId,
            fullName: oldTenant.fullName,
            gender: oldTenant.gender,
            dateOfBirth: oldTenant.dateOfBirth,
            phone: oldTenant.phone,
            email: oldTenant.email,
            username: oldTenant.username,
            permanentAddress: oldTenant.permanentAddress,
            currentAddress: oldTenant.currentAddress,
            avatarUrl: oldTenant.avatarUrl,
            status: status,
            roomNumber: oldTenant.roomNumber,
            buildingName: oldTenant.buildingName,
            identityType: oldTenant.identityType,
            identityNumber: oldTenant.identityNumber,
            frontImageUrl: oldTenant.frontImageUrl,
            backImageUrl: oldTenant.backImageUrl,
          );
        }
      }
      
      search(_searchQuery); // Refresh filtered list
      if (!success && _realTenants == null) {
        success = true; // Chế độ mock hoàn toàn
      }
    } catch (_) {}

    _isLoading = false;
    notifyListeners();
    return success;
  }

  /// Lấy thông tin chi tiết của một khách thuê (bao gồm hợp đồng hiện tại)
  Future<Map<String, dynamic>?> fetchTenantDetail(int id) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      await _apiService.init();
      final response = await _apiService.get<Map<String, dynamic>>(
        '/admin/tenants/$id',
        fromJsonT: (json) => json as Map<String, dynamic>,
      );

      _isLoading = false;
      notifyListeners();
      if (response.status && response.result != null) {
        return response.result;
      } else {
        _errorMessage = response.message;
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (e) {
      _errorMessage = 'Lỗi tải chi tiết khách thuê: $e';
    }

    // Chế độ mock dự phòng khi API lỗi hoặc chưa kết nối
    try {
      final t = _mockTenants.firstWhere((element) => element.id == id);
      _isLoading = false;
      notifyListeners();
      return {
        'id': t.id,
        'full_name': t.fullName,
        'phone': t.phone,
        'email': t.email,
        'username': t.username,
        'status': t.status,
        'room_number': t.roomNumber,
        'building_name': t.buildingName,
        'current_contract': {
          'id': t.id, // Giả lập contract_id bằng tenant_id cho mock
        }
      };
    } catch (_) {}

    _isLoading = false;
    notifyListeners();
    return null;
  }
}
