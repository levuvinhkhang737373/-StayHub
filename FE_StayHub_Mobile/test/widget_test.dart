import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:fe_stayhub_mobile/main.dart';
import 'package:fe_stayhub_mobile/controllers/auth_controller.dart';
import 'package:fe_stayhub_mobile/controllers/dashboard_controller.dart';
import 'package:fe_stayhub_mobile/controllers/facility_controller.dart';
import 'package:fe_stayhub_mobile/controllers/service_controller.dart';
import 'package:fe_stayhub_mobile/controllers/tenant_controller.dart';
import 'package:fe_stayhub_mobile/controllers/room_controller.dart';
import 'package:fe_stayhub_mobile/controllers/invoice_controller.dart';
import 'package:fe_stayhub_mobile/controllers/maintenance_controller.dart';
import 'package:fe_stayhub_mobile/controllers/contract_controller.dart';
import 'package:fe_stayhub_mobile/controllers/notification_controller.dart';
import 'package:fe_stayhub_mobile/services/websocket_service.dart';
import 'package:fe_stayhub_mobile/controllers/meter_reading_controller.dart';

void main() {
  testWidgets('Splash screen shows logo and loader', (WidgetTester tester) async {
    // Build our app and trigger a frame.
    await tester.pumpWidget(
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
          ChangeNotifierProvider(create: (_) => MeterReadingController()),
        ],
        child: const MyApp(),
      ),
    );

    // Verify that the splash screen shows the home work icon and loading indicator.
    expect(find.byIcon(Icons.home_work_rounded), findsOneWidget);
    expect(find.byType(CircularProgressIndicator), findsOneWidget);
  });
}
