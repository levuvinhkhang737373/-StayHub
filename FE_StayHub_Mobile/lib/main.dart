import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'controllers/auth_controller.dart';
import 'controllers/dashboard_controller.dart';
import 'controllers/facility_controller.dart';
import 'controllers/service_controller.dart';
import 'controllers/tenant_controller.dart';
import 'controllers/room_controller.dart';
import 'controllers/invoice_controller.dart';
import 'controllers/maintenance_controller.dart';
import 'controllers/contract_controller.dart';
import 'controllers/notification_controller.dart';
import 'services/websocket_service.dart';

import 'views/auth/login_screen.dart';
import 'views/dashboard/dashboard_screen.dart';
import 'views/facilities/facilities_screen.dart';
import 'views/services/services_screen.dart';
import 'views/settings/settings_screen.dart';
import 'views/admin/tenants_screen.dart';
import 'views/admin/rooms_screen.dart';
import 'views/admin/meters_screen.dart';
import 'views/admin/invoices_screen.dart';
import 'views/admin/maintenance_screen.dart';
import 'views/admin/contracts_screen.dart';
import 'views/admin/chat_screen.dart';

import 'views/tenant/tenant_dashboard_screen.dart';
import 'views/tenant/tenant_invoices_screen.dart';
import 'views/tenant/tenant_maintenance_screen.dart';
import 'views/tenant/tenant_chat_screen.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthController()),
        ChangeNotifierProvider(create: (_) => DashboardController()),
        ChangeNotifierProvider(create: (_) => FacilityController()),
        ChangeNotifierProvider(create: (_) => ServiceController()),
        ChangeNotifierProvider(create: (_) => TenantController()),
        ChangeNotifierProvider(create: (_) => RoomController()),
        ChangeNotifierProvider(create: (_) => InvoiceController()),
        ChangeNotifierProvider(create: (_) => MaintenanceController()),
        ChangeNotifierProvider(create: (_) => ContractController()),
        ChangeNotifierProvider(create: (_) => NotificationController()),
        ChangeNotifierProvider(create: (_) => WebSocketService()),
      ],
      child: const MyApp(),
    ),
  );
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'StayHub Mobile',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        useMaterial3: true,
        scaffoldBackgroundColor: const Color(0xFFF7F6F0),
        primaryColor: const Color(0xFF1C1917),
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF1C1917),
          primary: const Color(0xFF1C1917),
          secondary: const Color(0xFFEAB308),
          surface: const Color(0xFFF7F6F0),
        ),
        appBarTheme: const AppBarTheme(
          backgroundColor: Color(0xFF1C1917),
          foregroundColor: Colors.white,
          elevation: 0,
        ),
      ),
      builder: (context, child) {
        final mediaQuery = MediaQuery.of(context);
        final isWide = mediaQuery.size.width > 500;
        
        // Suppress system text scaling and clamp size to mobile bounds on wide screens
        final clampedMediaQuery = mediaQuery.copyWith(
          // ignore: deprecated_member_use
          textScaleFactor: 1.0,
          size: isWide ? Size(480, mediaQuery.size.height) : mediaQuery.size,
        );

        Widget mainApp = Container(
          color: isWide ? const Color(0xFF0F172A) : Colors.transparent, // Dark slate background for wide screens
          alignment: Alignment.center,
          child: Container(
            constraints: BoxConstraints(
              maxWidth: isWide ? 480 : double.infinity,
            ),
            decoration: isWide
                ? BoxDecoration(
                    color: const Color(0xFFF7F6F0),
                    borderRadius: BorderRadius.circular(24),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.3),
                        blurRadius: 30,
                        spreadRadius: 2,
                        offset: const Offset(0, 10),
                      ),
                    ],
                  )
                : const BoxDecoration(color: Colors.transparent),
            child: ClipRRect(
              borderRadius: isWide ? BorderRadius.circular(24) : BorderRadius.zero,
              child: child,
            ),
          ),
        );

        return MediaQuery(
          data: clampedMediaQuery,
          child: mainApp,
        );
      },
      home: const SplashScreen(),
      routes: {
        '/login': (_) => const LoginScreen(),
        '/dashboard': (_) => const DashboardScreen(),
        '/facilities': (_) => const FacilitiesScreen(),
        '/services': (_) => const ServicesScreen(),
        '/settings': (_) => const SettingsScreen(),
        // Admin Operations
        '/admin/tenants': (_) => const TenantsScreen(),
        '/admin/rooms': (_) => const RoomsScreen(),
        '/admin/meters': (_) => const MetersScreen(),
        '/admin/invoices': (_) => const InvoicesScreen(),
        '/admin/maintenance': (_) => const MaintenanceScreen(),
        '/admin/contracts': (_) => const ContractsScreen(),
        '/admin/notifications': (_) => const AdminNotificationScreen(),
        // Tenant Operations
        '/tenant-dashboard': (_) => const TenantDashboardScreen(),
        '/tenant/invoices': (_) => const TenantInvoicesScreen(),
        '/tenant/maintenance': (_) => const TenantMaintenanceScreen(),
        '/tenant/notifications': (_) => const TenantNotificationScreen(),
      },
    );
  }
}

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _checkAuth();
    });
  }

  Future<void> _checkAuth() async {
    final authController = context.read<AuthController>();
    final isLoggedIn = await authController.checkSession();

    if (!mounted) return;

    if (isLoggedIn) {
      context.read<WebSocketService>().connect();
      if (authController.isAdmin) {
        Navigator.pushReplacementNamed(context, '/dashboard');
      } else {
        Navigator.pushReplacementNamed(context, '/tenant-dashboard');
      }
    } else {
      Navigator.pushReplacementNamed(context, '/login');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF1C1917),
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: const [
            Icon(
              Icons.home_work_rounded,
              size: 80,
              color: Color(0xFFEAB308),
            ),
            SizedBox(height: 24),
            CircularProgressIndicator(
              valueColor: AlwaysStoppedAnimation<Color>(Color(0xFFEAB308)),
            ),
          ],
        ),
      ),
    );
  }
}
