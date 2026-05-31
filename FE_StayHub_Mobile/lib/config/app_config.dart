class AppConfig {
  // Use http://10.0.2.2:8000 for Android Emulator, http://localhost:8000 for iOS Simulator,
  // or your local computer IP address (e.g. 192.168.1.X) if testing on physical devices.
  static const String apiOrigin = 'http://10.0.2.2:8000';
  static const String apiUrl = '$apiOrigin/api';
  
  // Connect timeout in milliseconds
  static const int connectTimeout = 15000;
  // Receive timeout in milliseconds
  static const int receiveTimeout = 15000;
}
