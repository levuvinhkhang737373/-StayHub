import 'package:flutter/foundation.dart';
import 'package:dio/dio.dart';

class AppConfig {
  static bool useTunnel = false;
  static const String tunnelOrigin = 'https://api.stayhub.id.vn';

  // Use http://10.0.2.2:8080 for Android Emulator, http://localhost:8080 for iOS Simulator/Web/Desktop,
  // or your local computer IP address (e.g. 192.168.1.X) if testing on physical devices.
  static String get localOrigin {
    if (kIsWeb) {
      return 'http://localhost:8080';
    }
    if (defaultTargetPlatform == TargetPlatform.android) {
      return 'http://10.0.2.2:8080';
    }
    return 'http://localhost:8080';
  }

  static String get apiOrigin {
    return useTunnel ? tunnelOrigin : localOrigin;
  }

  static String get apiUrl => '$apiOrigin/api/v1/';

  static String get reverbHost {
    if (useTunnel) {
      return 'api.stayhub.id.vn';
    }
    if (kIsWeb) return 'localhost';
    if (defaultTargetPlatform == TargetPlatform.android) {
      return '10.0.2.2';
    }
    return 'localhost';
  }

  static int get reverbPort => useTunnel ? 443 : 8080;
  static const String reverbAppKey = 'rhtxfafogu4wbww3eufp';

  // Connect timeout in milliseconds
  static const int connectTimeout = 15000;
  // Receive timeout in milliseconds
  static const int receiveTimeout = 15000;

  // Auto-detect if Cloudflare Tunnel is alive
  static Future<void> checkServerConnection() async {
    if (kIsWeb) {
      useTunnel = false;
      return;
    }
    try {
      final dio = Dio(BaseOptions(
        connectTimeout: const Duration(milliseconds: 2000),
        receiveTimeout: const Duration(milliseconds: 2000),
      ));
      final response = await dio.get('$tunnelOrigin/up');
      if (response.statusCode == 200) {
        useTunnel = true;
        debugPrint('StayHub Config: Connected to Cloudflare Tunnel ($tunnelOrigin)');
      } else {
        useTunnel = false;
        debugPrint('StayHub Config: Tunnel returned status ${response.statusCode}, using local API');
      }
    } catch (e) {
      useTunnel = false;
      debugPrint('StayHub Config: Cannot reach Cloudflare Tunnel ($e), using local API ($localOrigin)');
    }
  }
}
