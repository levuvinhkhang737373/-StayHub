import 'package:flutter/material.dart';
import '../models/admin.dart';
import '../models/tenant.dart';
import '../services/api_service.dart';

class AuthController extends ChangeNotifier {
  final ApiService _apiService = ApiService();
  
  Admin? _currentAdmin;
  Tenant? _currentTenant;
  String? _currentRole; // 'admin' or 'tenant'
  bool _isLoading = false;
  String? _errorMessage;

  Admin? get currentAdmin => _currentAdmin;
  Tenant? get currentTenant => _currentTenant;
  String? get currentRole => _currentRole;
  
  bool get isAuthenticated => _currentAdmin != null || _currentTenant != null;
  bool get isAdmin => _currentRole == 'admin';
  bool get isTenant => _currentRole == 'tenant';
  
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;

  /// Clear the error message
  void clearError() {
    _errorMessage = null;
    notifyListeners();
  }

  /// Initialize and check existing session/me API
  Future<bool> checkSession() async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      await _apiService.init();
      // For mock bypass testing, if local variables are populated, just keep them
      if (_currentRole != null) {
        _isLoading = false;
        notifyListeners();
        return true;
      }
      
      final envelope = await _apiService.get<Admin>(
        '/admin/me',
        fromJsonT: (json) => Admin.fromJson(json as Map<String, dynamic>),
      );
      
      if (envelope.status && envelope.result != null) {
        _currentAdmin = envelope.result;
        _currentRole = 'admin';
        _isLoading = false;
        notifyListeners();
        return true;
      }
    } catch (e) {
      // Session expired or unreachable
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  /// Login with Username, Password, and Role selection
  Future<bool> login(String username, String password, {required bool isLoggingAsAdmin}) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    // Mock Login Bypass for Admin
    if (isLoggingAsAdmin && username.trim() == 'admin' && password == '123456') {
      await Future.delayed(const Duration(milliseconds: 600)); // Simulate delay
      _currentAdmin = Admin(
        id: 1,
        username: 'admin',
        fullName: 'Quản lý Tòa nhà',
        email: 'admin@stayhub.id.vn',
        phone: '0987654321',
        role: 1,
        status: 1,
        gender: 1,
        address: 'Thành phố Hồ Chí Minh',
      );
      _currentRole = 'admin';
      _currentTenant = null;
      _isLoading = false;
      notifyListeners();
      return true;
    }

    // Mock Login Bypass for Tenant
    if (!isLoggingAsAdmin && username.trim() == 'tenant' && password == '123456') {
      await Future.delayed(const Duration(milliseconds: 600)); // Simulate delay
      _currentTenant = Tenant(
        id: 1,
        buildingId: 1,
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
        identityNumber: '123456789012',
      );
      _currentRole = 'tenant';
      _currentAdmin = null;
      _isLoading = false;
      notifyListeners();
      return true;
    }

    // Otherwise attempt actual API call (currently only supports Admin login in BE)
    if (isLoggingAsAdmin) {
      try {
        await _apiService.init();
        await _apiService.getCsrfCookie();

        final response = await _apiService.post<Map<String, dynamic>>(
          '/admin/login',
          data: {
            'username': username,
            'password': password,
          },
          fromJsonT: (json) => json as Map<String, dynamic>,
        );

        if (response.status && response.result != null) {
          final adminData = response.result!['admin'];
          _currentAdmin = Admin.fromJson(adminData as Map<String, dynamic>);
          _currentRole = 'admin';
          _currentTenant = null;
          _isLoading = false;
          notifyListeners();
          return true;
        } else {
          _errorMessage = response.message;
        }
      } on ApiException catch (e) {
        _errorMessage = e.message;
      } catch (e) {
        _errorMessage = 'Đã xảy ra lỗi kết nối: $e';
      }
    } else {
      _errorMessage = 'Tên đăng nhập hoặc mật khẩu của khách thuê không chính xác (Thử: tenant / 123456)';
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  /// Change current password
  Future<bool> changePassword({
    required String currentPassword,
    required String newPassword,
    required String confirmPassword,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    // Mock response if API fails
    if (_currentRole != null) {
      await Future.delayed(const Duration(milliseconds: 500));
      _isLoading = false;
      notifyListeners();
      return true;
    }

    try {
      final response = await _apiService.patch<Map<String, dynamic>>(
        '/admin/password',
        data: {
          'current_password': currentPassword,
          'new_password': newPassword,
          'new_password_confirmation': confirmPassword,
        },
        fromJsonT: (json) => json as Map<String, dynamic>,
      );

      if (response.status && response.result != null) {
        final adminData = response.result!['admin'];
        _currentAdmin = Admin.fromJson(adminData as Map<String, dynamic>);
        _isLoading = false;
        notifyListeners();
        return true;
      } else {
        _errorMessage = response.message;
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (e) {
      _errorMessage = 'Lỗi đổi mật khẩu: $e';
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  /// Logout
  Future<bool> logout() async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    if (_currentRole == 'admin') {
      try {
        await _apiService.post<dynamic>(
          '/admin/logout',
          fromJsonT: (json) => json,
        );
      } catch (_) {}
    }

    await _apiService.clearCookies();
    _currentAdmin = null;
    _currentTenant = null;
    _currentRole = null;
    _isLoading = false;
    notifyListeners();
    return true;
  }
}
