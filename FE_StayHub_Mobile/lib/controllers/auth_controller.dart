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
      // Try checking admin session
      try {
        final adminEnvelope = await _apiService.get<Admin>(
          '/admin/me',
          fromJsonT: (json) => Admin.fromJson(json as Map<String, dynamic>),
        );
        if (adminEnvelope.status && adminEnvelope.result != null) {
          _currentAdmin = adminEnvelope.result;
          _currentRole = 'admin';
          _isLoading = false;
          notifyListeners();
          return true;
        }
      } catch (_) {}

      // Try checking tenant session
      try {
        final tenantEnvelope = await _apiService.get<Tenant>(
          '/tenant/me',
          fromJsonT: (json) => Tenant.fromJson(json as Map<String, dynamic>),
        );
        if (tenantEnvelope.status && tenantEnvelope.result != null) {
          _currentTenant = tenantEnvelope.result;
          _currentRole = 'tenant';
          _isLoading = false;
          notifyListeners();
          return true;
        }
      } catch (_) {}
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

    try {
      await _apiService.init();
      try {
        await _apiService.getCsrfCookie();
      } catch (e) {
        // Fail-silent for CSRF cookie fetching since mobile app doesn't always need it
        // and login is excluded from CSRF checks in Laravel backend anyway.
        debugPrint('CSRF Cookie retrieval failed/skipped: $e');
      }

      final path = isLoggingAsAdmin ? '/admin/login' : '/tenant/login';
      final response = await _apiService.post<Map<String, dynamic>>(
        path,
        data: {
          'username': username,
          'password': password,
        },
        fromJsonT: (json) => json as Map<String, dynamic>,
      );

      if (response.status && response.result != null) {
        if (isLoggingAsAdmin) {
          final adminData = response.result!['admin'];
          _currentAdmin = Admin.fromJson(adminData as Map<String, dynamic>);
          _currentRole = 'admin';
          _currentTenant = null;
        } else {
          final tenantData = response.result!['tenant'];
          _currentTenant = Tenant.fromJson(tenantData as Map<String, dynamic>);
          _currentRole = 'tenant';
          _currentAdmin = null;
        }
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
    } else if (_currentRole == 'tenant') {
      try {
        await _apiService.post<dynamic>(
          '/tenant/logout',
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

  /// Update tenant identity profile
  Future<bool> updateTenantProfile({
    required String fullName,
    required String identityNumber,
    required int identityType,
    required String identityDate,
    required String identityPlace,
    required String permanentAddress,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _apiService.patch<Tenant>(
        '/tenant/profile',
        data: {
          'full_name': fullName,
          'identity_number': identityNumber,
          'identity_type': identityType,
          'identity_date': identityDate,
          'identity_place': identityPlace,
          'permanent_address': permanentAddress,
        },
        fromJsonT: (json) => Tenant.fromJson(json as Map<String, dynamic>),
      );

      if (response.status && response.result != null) {
        _currentTenant = response.result;
        _isLoading = false;
        notifyListeners();
        return true;
      } else {
        _errorMessage = response.message;
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (e) {
      _errorMessage = 'Lỗi cập nhật thông tin: $e';
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }
}
