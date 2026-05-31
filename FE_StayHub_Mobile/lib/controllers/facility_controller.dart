import 'package:flutter/material.dart';
import '../models/region.dart';
import '../models/building.dart';
import '../services/api_service.dart';

class FacilityController extends ChangeNotifier {
  final ApiService _apiService = ApiService();

  List<Region> _regions = [];
  List<Building> _buildings = [];
  bool _isLoading = false;
  String? _errorMessage;

  List<Region> get regions => _regions;
  List<Building> get buildings => _buildings;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;

  /// Fetch all Regions
  Future<void> fetchRegions() async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _apiService.get<List<dynamic>>(
        '/admin/regions',
        fromJsonT: (json) => json as List<dynamic>,
      );

      if (response.status && response.result != null) {
        _regions = response.result!
            .map((item) => Region.fromJson(item as Map<String, dynamic>))
            .toList();
      } else {
        _errorMessage = response.message;
      }
    } catch (e) {
      // API Offline Fallback
      _regions = [
        Region(
          id: 1,
          code: 'KV-HCM',
          name: 'Khu vực TP. Hồ Chí Minh',
          slug: 'tp-ho-chi-minh',
          status: 1,
          description: 'Các tòa nhà khu vực phía Nam',
        ),
        Region(
          id: 2,
          code: 'KV-HN',
          name: 'Khu vực Hà Nội',
          slug: 'ha-noi',
          status: 1,
          description: 'Các tòa nhà khu vực phía Bắc',
        ),
      ];
    }

    _isLoading = false;
    notifyListeners();
  }

  /// Create a new Region
  Future<bool> createRegion({
    required String name,
    required String? description,
    required int status,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _apiService.post<Map<String, dynamic>>(
        '/admin/regions',
        data: {
          'name': name,
          'description': description,
          'status': status,
        },
        fromJsonT: (json) => json as Map<String, dynamic>,
      );

      if (response.status) {
        await fetchRegions(); // Reload the list
        return true;
      } else {
        _errorMessage = response.message;
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (e) {
      _errorMessage = 'Lỗi tạo vùng: $e';
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  /// Update an existing Region
  Future<bool> updateRegion({
    required int id,
    required String name,
    required String? description,
    required int status,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _apiService.put<Map<String, dynamic>>(
        '/admin/regions/$id',
        data: {
          'name': name,
          'description': description,
          'status': status,
        },
        fromJsonT: (json) => json as Map<String, dynamic>,
      );

      if (response.status) {
        await fetchRegions();
        return true;
      } else {
        _errorMessage = response.message;
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (e) {
      _errorMessage = 'Lỗi cập nhật vùng: $e';
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  /// Update Region Status
  Future<bool> updateRegionStatus(int id, int status) async {
    try {
      final response = await _apiService.patch<Map<String, dynamic>>(
        '/admin/regions/$id/status',
        data: {'status': status},
        fromJsonT: (json) => json as Map<String, dynamic>,
      );
      if (response.status) {
        await fetchRegions();
        return true;
      }
    } catch (e) {
      _errorMessage = 'Không thể đổi trạng thái vùng: $e';
      notifyListeners();
    }
    return false;
  }

  /// Delete a Region
  Future<bool> deleteRegion(int id) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _apiService.delete<Map<String, dynamic>>(
        '/admin/regions/$id',
        fromJsonT: (json) => json as Map<String, dynamic>,
      );

      if (response.status) {
        await fetchRegions();
        return true;
      } else {
        _errorMessage = response.message;
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (e) {
      _errorMessage = 'Lỗi xóa vùng: $e';
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  /// Fetch all Buildings
  Future<void> fetchBuildings() async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _apiService.get<List<dynamic>>(
        '/admin/buildings',
        fromJsonT: (json) => json as List<dynamic>,
      );

      if (response.status && response.result != null) {
        _buildings = response.result!
            .map((item) => Building.fromJson(item as Map<String, dynamic>))
            .toList();
      } else {
        _errorMessage = response.message;
      }
    } catch (e) {
      // API Offline Fallback
      _buildings = [
        Building(
          id: 1,
          regionId: 1,
          name: 'StayHub Sài Gòn Q1',
          slug: 'stayhub-sai-gon-q1',
          address: '123 Cống Quỳnh, Q.1, TP.HCM',
          description: 'Tòa nhà trung tâm',
          status: 1,
          genderPolicy: 1,
        ),
        Building(
          id: 2,
          regionId: 1,
          name: 'StayHub Sài Gòn Q3',
          slug: 'stayhub-sai-gon-q3',
          address: '456 Lê Văn Sỹ, Q.3, TP.HCM',
          description: 'Khu dân cư đông đúc',
          status: 1,
          genderPolicy: 1,
        ),
        Building(
          id: 3,
          regionId: 2,
          name: 'StayHub Hà Nội Cầu Giấy',
          slug: 'stayhub-ha-noi-cau-giay',
          address: '10 Trần Thái Tông, Cầu Giấy, HN',
          description: 'Tòa nhà văn phòng và căn hộ',
          status: 1,
          genderPolicy: 1,
        ),
      ];
    }

    _isLoading = false;
    notifyListeners();
  }

  /// Create a Building
  Future<bool> createBuilding({
    required String name,
    required int regionId,
    required int? managerAdminId,
    required String? address,
    required String? description,
    required int status,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _apiService.post<Map<String, dynamic>>(
        '/admin/buildings',
        data: {
          'name': name,
          'region_id': regionId,
          'manager_admin_id': managerAdminId,
          'address': address,
          'description': description,
          'status': status,
        },
        fromJsonT: (json) => json as Map<String, dynamic>,
      );

      if (response.status) {
        await fetchBuildings();
        return true;
      } else {
        _errorMessage = response.message;
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (e) {
      _errorMessage = 'Lỗi tạo tòa nhà: $e';
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  /// Update a Building
  Future<bool> updateBuilding({
    required int id,
    required String name,
    required int regionId,
    required int? managerAdminId,
    required String? address,
    required String? description,
    required int status,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _apiService.put<Map<String, dynamic>>(
        '/admin/buildings/$id',
        data: {
          'name': name,
          'region_id': regionId,
          'manager_admin_id': managerAdminId,
          'address': address,
          'description': description,
          'status': status,
        },
        fromJsonT: (json) => json as Map<String, dynamic>,
      );

      if (response.status) {
        await fetchBuildings();
        return true;
      } else {
        _errorMessage = response.message;
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (e) {
      _errorMessage = 'Lỗi cập nhật tòa nhà: $e';
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  /// Update Building Status
  Future<bool> updateBuildingStatus(int id, int status) async {
    try {
      final response = await _apiService.patch<Map<String, dynamic>>(
        '/admin/buildings/$id/status',
        data: {'status': status},
        fromJsonT: (json) => json as Map<String, dynamic>,
      );
      if (response.status) {
        await fetchBuildings();
        return true;
      }
    } catch (e) {
      _errorMessage = 'Không thể đổi trạng thái tòa nhà: $e';
      notifyListeners();
    }
    return false;
  }

  /// Delete a Building
  Future<bool> deleteBuilding(int id) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _apiService.delete<Map<String, dynamic>>(
        '/admin/buildings/$id',
        fromJsonT: (json) => json as Map<String, dynamic>,
      );

      if (response.status) {
        await fetchBuildings();
        return true;
      } else {
        _errorMessage = response.message;
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (e) {
      _errorMessage = 'Lỗi xóa tòa nhà: $e';
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }
}
