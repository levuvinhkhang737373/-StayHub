import 'package:flutter/material.dart';
import '../models/service.dart';
import '../services/api_service.dart';

class ServiceController extends ChangeNotifier {
  final ApiService _apiService = ApiService();

  List<Service> _services = [];
  bool _isLoading = false;
  String? _errorMessage;

  List<Service> get services => _services;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;

  /// Fetch all Services
  Future<void> fetchServices() async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _apiService.get<List<dynamic>>(
        '/admin/services',
        fromJsonT: (json) => json as List<dynamic>,
      );

      if (response.status && response.result != null) {
        _services = response.result!
            .map((item) => Service.fromJson(item as Map<String, dynamic>))
            .toList();
      } else {
        _errorMessage = response.message;
      }
    } catch (e) {
      _services = [
        Service(id: 1, slug: 'dien-sinh-hoat', name: 'Điện sinh hoạt', unitName: 'kWh', price: 3500, status: 1, chargeMethod: 1, isRequired: true, description: 'Chỉ số công tơ điện đầu phòng'),
        Service(id: 2, slug: 'nuoc-sinh-hoat', name: 'Nước sạch', unitName: 'm3', price: 18000, status: 1, chargeMethod: 1, isRequired: true, description: 'Chỉ số đồng hồ nước sạch'),
        Service(id: 3, slug: 'internet', name: 'Internet cáp quang', unitName: 'Phòng', price: 100000, status: 1, chargeMethod: 2, isRequired: false, description: 'Đường truyền internet tốc độ cao'),
        Service(id: 4, slug: 'phi-rac', name: 'Phí dịch vụ chung', unitName: 'Người', price: 50000, status: 1, chargeMethod: 3, isRequired: true, description: 'Vệ sinh, rác thải, thang máy'),
      ];
    }

    _isLoading = false;
    notifyListeners();
  }

  /// Create a Service
  Future<bool> createService({
    required String name,
    required String unit,
    required double price,
    required int status,
    required String? description,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _apiService.post<Map<String, dynamic>>(
        '/admin/services',
        data: {
          'name': name,
          'unit': unit,
          'price': price,
          'status': status,
          'description': description,
        },
        fromJsonT: (json) => json as Map<String, dynamic>,
      );

      if (response.status) {
        await fetchServices();
        return true;
      } else {
        _errorMessage = response.message;
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (e) {
      _errorMessage = 'Lỗi tạo dịch vụ: $e';
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  /// Update a Service
  Future<bool> updateService({
    required int id,
    required String name,
    required String unit,
    required double price,
    required int status,
    required String? description,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _apiService.put<Map<String, dynamic>>(
        '/admin/services/$id',
        data: {
          'name': name,
          'unit': unit,
          'price': price,
          'status': status,
          'description': description,
        },
        fromJsonT: (json) => json as Map<String, dynamic>,
      );

      if (response.status) {
        await fetchServices();
        return true;
      } else {
        _errorMessage = response.message;
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (e) {
      _errorMessage = 'Lỗi cập nhật dịch vụ: $e';
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  /// Update Service Status
  Future<bool> updateServiceStatus(int id, int status) async {
    try {
      final response = await _apiService.patch<Map<String, dynamic>>(
        '/admin/services/$id/status',
        data: {'status': status},
        fromJsonT: (json) => json as Map<String, dynamic>,
      );
      if (response.status) {
        await fetchServices();
        return true;
      }
    } catch (e) {
      _errorMessage = 'Không thể đổi trạng thái dịch vụ: $e';
      notifyListeners();
    }
    return false;
  }

  /// Delete a Service
  Future<bool> deleteService(int id) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _apiService.delete<Map<String, dynamic>>(
        '/admin/services/$id',
        fromJsonT: (json) => json as Map<String, dynamic>,
      );

      if (response.status) {
        await fetchServices();
        return true;
      } else {
        _errorMessage = response.message;
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (e) {
      _errorMessage = 'Lỗi xóa dịch vụ: $e';
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }
}
