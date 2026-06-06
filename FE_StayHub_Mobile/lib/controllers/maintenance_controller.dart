import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:image_picker/image_picker.dart';
import 'package:dio/dio.dart';
import '../models/maintenance_request.dart';
import '../services/api_service.dart';

class MaintenanceController extends ChangeNotifier {
  final ApiService _apiService = ApiService();

  List<MaintenanceRequest> _requests = [];
  bool _isLoading = false;
  String? _errorMessage;

  List<MaintenanceRequest> get requests => _requests;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;

  /// Get requests for specific room (for Tenant view)
  List<MaintenanceRequest> getRequestsForRoom(String roomNumber) {
    // Trả về toàn bộ danh sách vì backend đã lọc theo tenant đang đăng nhập rồi
    return _requests;
  }

  /// Tải danh sách yêu cầu bảo trì của Tenant
  Future<void> fetchRequests() async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _apiService.get<List<dynamic>>(
        '/tenant/maintenance-requests',
        fromJsonT: (json) => json['data'] as List<dynamic>,
      );

      if (response.status && response.result != null) {
        _requests = response.result!
            .map((item) => MaintenanceRequest.fromJson(item as Map<String, dynamic>))
            .toList();
      } else {
        _errorMessage = response.message;
      }
    } catch (e) {
      _errorMessage = 'Lỗi tải danh sách bảo trì: $e';
    }

    _isLoading = false;
    notifyListeners();
  }

  /// Tải danh sách yêu cầu bảo trì của Admin
  Future<void> fetchAdminRequests() async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _apiService.get<List<dynamic>>(
        '/admin/maintenance-requests',
        fromJsonT: (json) => json['data'] as List<dynamic>,
      );

      if (response.status && response.result != null) {
        _requests = response.result!
            .map((item) => MaintenanceRequest.fromJson(item as Map<String, dynamic>))
            .toList();
      } else {
        _errorMessage = response.message;
      }
    } catch (e) {
      _errorMessage = 'Lỗi tải danh sách bảo trì admin: $e';
    }

    _isLoading = false;
    notifyListeners();
  }

  /// Gửi một yêu cầu sửa chữa mới (Tenant action)
  Future<bool> createRequest({
    required String roomNumber,
    required String title,
    required String description,
    XFile? imageFile,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final Map<String, dynamic> dataMap = {
        'title': title,
        'description': description,
      };

      if (imageFile != null) {
        if (kIsWeb) {
          final bytes = await imageFile.readAsBytes();
          dataMap['images[]'] = MultipartFile.fromBytes(
            bytes,
            filename: imageFile.name,
          );
        } else {
          dataMap['images[]'] = await MultipartFile.fromFile(
            imageFile.path,
            filename: imageFile.name,
          );
        }
      }

      final formData = FormData.fromMap(dataMap);

      final response = await _apiService.post<Map<String, dynamic>>(
        '/tenant/maintenance-requests',
        data: formData,
        fromJsonT: (json) => json as Map<String, dynamic>,
      );

      if (response.status) {
        await fetchRequests();
        _isLoading = false;
        notifyListeners();
        return true;
      } else {
        _errorMessage = response.message;
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (e) {
      _errorMessage = 'Lỗi gửi yêu cầu sửa chữa: $e';
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  /// Cập nhật trạng thái phiếu sửa chữa (Admin action)
  Future<bool> updateRequestStatus(int id, int status, {String? note, String? afterImageUrl}) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _apiService.patch<Map<String, dynamic>>(
        '/admin/maintenance-requests/$id/status',
        data: {
          'status': status,
          'note': note ?? 'Cập nhật trạng thái bảo trì',
        },
        fromJsonT: (json) => json as Map<String, dynamic>,
      );

      if (response.status) {
        await fetchAdminRequests();
        _isLoading = false;
        notifyListeners();
        return true;
      } else {
        _errorMessage = response.message;
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (e) {
      _errorMessage = 'Lỗi cập nhật trạng thái bảo trì: $e';
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  /// Phân công nhân viên sửa chữa (Admin action)
  Future<bool> assignStaff(int id, int adminId) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _apiService.patch<Map<String, dynamic>>(
        '/admin/maintenance-requests/$id/assign',
        data: {
          'assigned_to': adminId,
        },
        fromJsonT: (json) => json as Map<String, dynamic>,
      );

      if (response.status) {
        await fetchAdminRequests();
        _isLoading = false;
        notifyListeners();
        return true;
      } else {
        _errorMessage = response.message;
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (e) {
      _errorMessage = 'Lỗi phân công nhân viên: $e';
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  /// Đánh giá chất lượng sau khi sửa chữa (Tenant action)
  Future<bool> addFeedback(int id, String comment, {int rating = 5}) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _apiService.post<Map<String, dynamic>>(
        '/tenant/maintenance-requests/$id/feedback',
        data: {
          'rating': rating,
          'comment': comment,
        },
        fromJsonT: (json) => json as Map<String, dynamic>,
      );

      if (response.status) {
        await fetchRequests();
        _isLoading = false;
        notifyListeners();
        return true;
      } else {
        _errorMessage = response.message;
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (e) {
      _errorMessage = 'Lỗi gửi đánh giá: $e';
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }
}
