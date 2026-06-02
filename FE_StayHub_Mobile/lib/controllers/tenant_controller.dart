import 'package:flutter/material.dart';
import '../models/tenant.dart';

class TenantController extends ChangeNotifier {
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

  List<Tenant> _filteredTenants = [];
  bool _isLoading = false;
  String _searchQuery = '';

  List<Tenant> get tenants => _filteredTenants.isEmpty && _searchQuery.isEmpty ? _mockTenants : _filteredTenants;
  bool get isLoading => _isLoading;

  TenantController() {
    _filteredTenants = List.from(_mockTenants);
  }

  /// Search/filter tenants by name or phone or room
  void search(String query) {
    _searchQuery = query.toLowerCase();
    if (_searchQuery.isEmpty) {
      _filteredTenants = List.from(_mockTenants);
    } else {
      _filteredTenants = _mockTenants.where((t) {
        return t.fullName.toLowerCase().contains(_searchQuery) ||
            t.phone.contains(_searchQuery) ||
            (t.roomNumber != null && t.roomNumber!.contains(_searchQuery));
      }).toList();
    }
    notifyListeners();
  }

  /// Toggle/Update tenant status
  Future<bool> updateStatus(int id, int status) async {
    _isLoading = true;
    notifyListeners();

    await Future.delayed(const Duration(milliseconds: 500)); // Simulate API delay

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
        search(_searchQuery); // Refresh filtered list
        _isLoading = false;
        notifyListeners();
        return true;
      }
    } catch (_) {}

    _isLoading = false;
    notifyListeners();
    return false;
  }
}
