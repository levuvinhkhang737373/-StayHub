import 'package:flutter/foundation.dart';
import 'package:dio/dio.dart';

class AppConfig {
  static bool useTunnel = true;
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
      return 'socket.stayhub.id.vn';
    }
    if (kIsWeb) return 'localhost';
    if (defaultTargetPlatform == TargetPlatform.android) {
      return '10.0.2.2';
    }
    return 'localhost';
  }

  static int get reverbPort => useTunnel ? 443 : 8080;
  static const String reverbAppKey = 'rhtxfafogu4wbww3eufp';                                                                                                                                                                                                                                                                                                Z Q

  // Connect timeout in milliseconds
  static const int connectTimeout = 15000;
  // Receive timeout in milliseconds
  static const int receiveTimeout = 15000;

  // Auto-detect if Cloudflare Tunnel is alive
  static Future<void> checkServerConnection() async {
    useTunnel = true;
    debugPrint(
      'StayHub Config: Forcing Cloudflare Tunnel host API ($tunnelOrigin)',
    );
  }

}
