import 'package:flutter/foundation.dart';
import 'package:dio/dio.dart';

class AppConfig {
  static bool useTunnel = true;
  static const String tunnelOrigin = 'https://api.stayhub.id.vn';

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

  static String get assetOrigin => apiOrigin;

  static String get reverbHost {
    if (useTunnel) {
      return 'socket.stayhub.id.vn';
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
    final dio = Dio(
      BaseOptions(
        connectTimeout: const Duration(seconds: 3),
        receiveTimeout: const Duration(seconds: 3),
      ),
    );
    try {
      final response = await dio.get('$tunnelOrigin/up');
      if (response.statusCode == 200) {
        useTunnel = true;
        debugPrint(
          'StayHub Config: Cloudflare Tunnel is active ($tunnelOrigin)',
        );
        return;
      }
    } catch (e) {
      debugPrint(
        'StayHub Config: Cloudflare Tunnel is unreachable ($e). Falling back to local.',
      );
    }
    useTunnel = false;
    debugPrint('StayHub Config: Using Local API ($localOrigin)');
  }
}
