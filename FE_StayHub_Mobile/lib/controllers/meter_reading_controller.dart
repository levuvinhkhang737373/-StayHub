import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'package:image_picker/image_picker.dart';
import '../services/api_service.dart';

class RoomReading {
  final int roomId;
  final String roomNumber;
  final String? tenantName;
  final int? contractId;
  final List<MeterDeviceReading> meters;

  RoomReading({
    required this.roomId,
    required this.roomNumber,
    this.tenantName,
    this.contractId,
    required this.meters,
  });

  factory RoomReading.fromJson(Map<String, dynamic> json) {
    return RoomReading(
      roomId: json['room_id'] as int,
      roomNumber: json['room_number'] as String? ?? '',
      tenantName: json['tenant_name'] as String?,
      contractId: json['contract_id'] as int?,
      meters: (json['meters'] as List? ?? [])
          .map((m) => MeterDeviceReading.fromJson(m as Map<String, dynamic>))
          .toList(),
    );
  }
}

class MeterDeviceReading {
  final int id;
  final String? meterCode;
  final int meterType; // 1: electric, 2: water
  final int serviceId;
  final String serviceName;
  final double previousReading;
  final ExistingReading? existingReading;

  MeterDeviceReading({
    required this.id,
    this.meterCode,
    required this.meterType,
    required this.serviceId,
    required this.serviceName,
    required this.previousReading,
    this.existingReading,
  });

  factory MeterDeviceReading.fromJson(Map<String, dynamic> json) {
    return MeterDeviceReading(
      id: json['id'] as int,
      meterCode: json['meter_code'] as String?,
      meterType: json['meter_type'] as int? ?? 1,
      serviceId: json['service_id'] as int? ?? 0,
      serviceName: json['service_name'] as String? ?? '',
      previousReading: (json['previous_reading'] as num? ?? 0).toDouble(),
      existingReading: json['existing_reading'] != null
          ? ExistingReading.fromJson(json['existing_reading'] as Map<String, dynamic>)
          : null,
    );
  }
}

class ExistingReading {
  final int id;
  final double currentReading;
  final double consumption;
  final String? readingDate;
  final int status;
  final String? imagePath;
  final String? imageUrl;
  final String? note;

  ExistingReading({
    required this.id,
    required this.currentReading,
    required this.consumption,
    this.readingDate,
    required this.status,
    this.imagePath,
    this.imageUrl,
    this.note,
  });

  factory ExistingReading.fromJson(Map<String, dynamic> json) {
    return ExistingReading(
      id: json['id'] as int,
      currentReading: (json['current_reading'] as num? ?? 0).toDouble(),
      consumption: (json['consumption'] as num? ?? 0).toDouble(),
      readingDate: json['reading_date'] as String?,
      status: json['status'] as int? ?? 1,
      imagePath: json['image_path'] as String?,
      imageUrl: json['image_url'] as String?,
      note: json['note'] as String?,
    );
  }
}

class AnalyzeMeterImageResult {
  final bool success;
  final double? readingValue;
  final String? confidence;
  final String? warning;
  final String? anomalyWarning;
  final String? error;
  final String? imagePath;
  final String? imageUrl;

  AnalyzeMeterImageResult({
    required this.success,
    this.readingValue,
    this.confidence,
    this.warning,
    this.anomalyWarning,
    this.error,
    this.imagePath,
    this.imageUrl,
  });

  factory AnalyzeMeterImageResult.fromJson(Map<String, dynamic> json) {
    return AnalyzeMeterImageResult(
      success: json['success'] as bool? ?? false,
      readingValue: json['reading_value'] != null ? double.tryParse(json['reading_value'].toString()) : null,
      confidence: json['confidence'] as String?,
      warning: json['warning'] as String?,
      anomalyWarning: json['anomaly_warning'] as String?,
      error: json['error'] as String?,
      imagePath: json['image_path'] as String?,
      imageUrl: json['image_url'] as String?,
    );
  }
}

class ServicePriceInit {
  final int serviceId;
  final String name;
  final String slug;
  final double price;
  final String unitName;

  ServicePriceInit({
    required this.serviceId,
    required this.name,
    required this.slug,
    required this.price,
    required this.unitName,
  });

  factory ServicePriceInit.fromJson(Map<String, dynamic> json) {
    return ServicePriceInit(
      serviceId: json['service_id'] as int,
      name: json['name'] as String? ?? '',
      slug: json['slug'] as String? ?? '',
      price: (json['price'] as num? ?? 0).toDouble(),
      unitName: json['unit_name'] as String? ?? '',
    );
  }
}

class MeterReadingController extends ChangeNotifier {
  final ApiService _apiService = ApiService();

  List<RoomReading> _rooms = [];
  List<ServicePriceInit> _servicePrices = [];
  bool _isLoading = false;
  String? _errorMessage;

  List<RoomReading> get rooms => _rooms;
  List<ServicePriceInit> get servicePrices => _servicePrices;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;

  Future<void> fetchMeterReadings({
    required int buildingId,
    required int month,
    required int year,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _apiService.get<Map<String, dynamic>>(
        '/admin/meter-readings/init',
        queryParameters: {
          'building_id': buildingId,
          'billing_month': month,
          'billing_year': year,
        },
        fromJsonT: (json) => json as Map<String, dynamic>,
      );

      if (response.status && response.result != null) {
        final data = response.result!;
        _rooms = (data['rooms'] as List? ?? [])
            .map((item) => RoomReading.fromJson(item as Map<String, dynamic>))
            .toList();
        _servicePrices = (data['service_prices'] as List? ?? [])
            .map((item) => ServicePriceInit.fromJson(item as Map<String, dynamic>))
            .toList();
      } else {
        _errorMessage = response.message;
      }
    } catch (e) {
      _errorMessage = 'Không thể tải dữ liệu chốt số điện nước: $e';
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> saveMeterReading({
    required int meterDeviceId,
    required int month,
    required int year,
    required double currentReading,
    required String readingDate,
    String? note,
    String? imagePath,
  }) async {
    try {
      final response = await _apiService.post<Map<String, dynamic>>(
        '/admin/meter-readings',
        data: {
          'meter_device_id': meterDeviceId,
          'billing_month': month,
          'billing_year': year,
          'current_reading': currentReading,
          'reading_date': readingDate,
          'note': note,
          if (imagePath != null && imagePath.isNotEmpty) 'image_path': imagePath,
        },
        fromJsonT: (json) => json as Map<String, dynamic>,
      );
      return response.status;
    } catch (e) {
      _errorMessage = 'Lỗi chốt số đồng hồ: $e';
      notifyListeners();
      return false;
    }
  }

  Future<AnalyzeMeterImageResult?> analyzeMeterImage({
    required XFile image,
    required int meterType,
    required double previousReading,
  }) async {
    try {
      final bytes = await image.readAsBytes();
      final formData = FormData.fromMap({
        'image': MultipartFile.fromBytes(bytes, filename: image.name.isNotEmpty ? image.name : 'meter-photo.jpg'),
        'meter_type': meterType,
        'previous_reading': previousReading,
      });

      final response = await _apiService.post<Map<String, dynamic>>(
        '/admin/meter-readings/analyze-image',
        data: formData,
        receiveTimeout: const Duration(seconds: 60),
        fromJsonT: (json) => json as Map<String, dynamic>,
      );

      if (response.result != null) {
        return AnalyzeMeterImageResult.fromJson(response.result!);
      }

      _errorMessage = response.message;
      notifyListeners();
      return null;
    } catch (e) {
      _errorMessage = 'Không thể phân tích ảnh đồng hồ: $e';
      notifyListeners();
      return AnalyzeMeterImageResult(success: false, error: 'ai_service_unavailable');
    }
  }
}
