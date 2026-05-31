import 'dart:io';
import 'package:dio/dio.dart';
import 'package:dio_cookie_manager/dio_cookie_manager.dart';
import 'package:cookie_jar/cookie_jar.dart';
import 'package:path_provider/path_provider.dart';
import '../config/app_config.dart';

class ApiEnvelope<T> {
  final bool status;
  final String message;
  final int? errorCode;
  final T? result;

  ApiEnvelope({
    required this.status,
    required this.message,
    this.errorCode,
    this.result,
  });

  factory ApiEnvelope.fromJson(Map<String, dynamic> json, T Function(dynamic json) fromJsonT) {
    return ApiEnvelope(
      status: json['status'] as bool? ?? false,
      message: json['message'] as String? ?? '',
      errorCode: json['errorCode'] as int?,
      result: json['result'] != null ? fromJsonT(json['result']) : null,
    );
  }
}

class ApiException implements Exception {
  final String message;
  final int? statusCode;
  final int? errorCode;
  final Map<String, dynamic>? validationErrors;

  ApiException({
    required this.message,
    this.statusCode,
    this.errorCode,
    this.validationErrors,
  });

  @override
  String toString() => 'ApiException: $message (Status: $statusCode)';
}

class ApiService {
  static final ApiService _instance = ApiService._internal();
  late final Dio _dio;
  late final PersistCookieJar _cookieJar;
  bool _initialized = false;

  factory ApiService() {
    return _instance;
  }

  ApiService._internal() {
    _dio = Dio(BaseOptions(
      baseUrl: AppConfig.apiUrl,
      connectTimeout: const Duration(milliseconds: AppConfig.connectTimeout),
      receiveTimeout: const Duration(milliseconds: AppConfig.receiveTimeout),
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    ));
  }

  Dio get client => _dio;

  Future<void> init() async {
    if (_initialized) return;

    final appDocDir = await getApplicationDocumentsDirectory();
    final cookiePath = '${appDocDir.path}/.cookies/';
    
    // Create the directory if it doesn't exist
    await Directory(cookiePath).create(recursive: true);

    _cookieJar = PersistCookieJar(
      storage: FileStorage(cookiePath),
      ignoreExpires: true,
    );

    _dio.interceptors.add(CookieManager(_cookieJar));
    
    // Custom interceptor to log and handle unauthorized errors
    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) {
        // Automatically inject CSRF token from cookies if present
        return handler.next(options);
      },
      onError: (DioException e, handler) async {
        if (e.response?.statusCode == 419) {
          // Token mismatch / CSRF expired, try refreshing CSRF cookie and retrying
          try {
            await getCsrfCookie();
            final options = e.requestOptions;
            final response = await _dio.request(
              options.path,
              data: options.data,
              queryParameters: options.queryParameters,
              options: Options(
                method: options.method,
                headers: options.headers,
              ),
            );
            return handler.resolve(response);
          } catch (retryError) {
            return handler.next(e);
          }
        }
        return handler.next(e);
      },
    ));

    _initialized = true;
  }

  /// Get CSRF cookie from Laravel Sanctum
  Future<void> getCsrfCookie() async {
    try {
      await _dio.get(
        '${AppConfig.apiOrigin}/sanctum/csrf-cookie',
        options: Options(
          headers: {
            'Accept': 'application/json',
          },
        ),
      );
    } catch (e) {
      throw ApiException(message: 'Không thể kết nối lấy token bảo mật CSRF: $e');
    }
  }

  /// Helper to map DioException to ApiException
  ApiException _handleDioError(DioException e) {
    if (e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.receiveTimeout ||
        e.type == DioExceptionType.sendTimeout) {
      return ApiException(
        message: 'Kết nối mạng quá hạn. Vui lòng kiểm tra lại đường truyền.',
        statusCode: 408,
      );
    }

    if (e.error is SocketException) {
      return ApiException(
        message: 'Không thể kết nối tới máy chủ. Vui lòng kiểm tra địa chỉ API hoặc mạng internet.',
        statusCode: 503,
      );
    }

    final response = e.response;
    if (response != null) {
      final statusCode = response.statusCode;
      final data = response.data;

      if (data is Map<String, dynamic>) {
        final message = data['message'] as String? ?? 'Đã xảy ra lỗi từ hệ thống.';
        final errorCode = data['errorCode'] as int?;
        Map<String, dynamic>? validationErrors;

        if (statusCode == 422 && data['result'] is Map<String, dynamic>) {
          validationErrors = data['result'] as Map<String, dynamic>;
        }

        return ApiException(
          message: message,
          statusCode: statusCode,
          errorCode: errorCode,
          validationErrors: validationErrors,
        );
      }

      return ApiException(
        message: 'Lỗi hệ thống (${response.statusCode}). Vui lòng thử lại sau.',
        statusCode: statusCode,
      );
    }

    return ApiException(message: 'Đã xảy ra lỗi không xác định: ${e.message}');
  }

  /// Perform a GET request
  Future<ApiEnvelope<T>> get<T>(
    String path, {
    Map<String, dynamic>? queryParameters,
    required T Function(dynamic json) fromJsonT,
  }) async {
    try {
      final response = await _dio.get(path, queryParameters: queryParameters);
      return ApiEnvelope.fromJson(response.data as Map<String, dynamic>, fromJsonT);
    } on DioException catch (e) {
      throw _handleDioError(e);
    }
  }

  /// Perform a POST request
  Future<ApiEnvelope<T>> post<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    required T Function(dynamic json) fromJsonT,
  }) async {
    try {
      final response = await _dio.post(path, data: data, queryParameters: queryParameters);
      return ApiEnvelope.fromJson(response.data as Map<String, dynamic>, fromJsonT);
    } on DioException catch (e) {
      throw _handleDioError(e);
    }
  }

  /// Perform a PUT request
  Future<ApiEnvelope<T>> put<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    required T Function(dynamic json) fromJsonT,
  }) async {
    try {
      final response = await _dio.put(path, data: data, queryParameters: queryParameters);
      return ApiEnvelope.fromJson(response.data as Map<String, dynamic>, fromJsonT);
    } on DioException catch (e) {
      throw _handleDioError(e);
    }
  }

  /// Perform a PATCH request
  Future<ApiEnvelope<T>> patch<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    required T Function(dynamic json) fromJsonT,
  }) async {
    try {
      final response = await _dio.patch(path, data: data, queryParameters: queryParameters);
      return ApiEnvelope.fromJson(response.data as Map<String, dynamic>, fromJsonT);
    } on DioException catch (e) {
      throw _handleDioError(e);
    }
  }

  /// Perform a DELETE request
  Future<ApiEnvelope<T>> delete<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    required T Function(dynamic json) fromJsonT,
  }) async {
    try {
      final response = await _dio.delete(path, data: data, queryParameters: queryParameters);
      return ApiEnvelope.fromJson(response.data as Map<String, dynamic>, fromJsonT);
    } on DioException catch (e) {
      throw _handleDioError(e);
    }
  }

  /// Clear all stored session cookies (logout helper)
  Future<void> clearCookies() async {
    await _cookieJar.deleteAll();
  }
}
