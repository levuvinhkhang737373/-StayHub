import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:fe_stayhub_mobile/controllers/auth_controller.dart';
import 'package:fe_stayhub_mobile/services/api_service.dart';

class QueueAdapter implements HttpClientAdapter {
  final List<RequestOptions> requests = [];
  final Map<String, List<ResponseBody Function(RequestOptions)>>
  responsesByPath;

  QueueAdapter(this.responsesByPath);

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);
    final queue = responsesByPath[options.uri.path];
    if (queue == null || queue.isEmpty) {
      fail('Unexpected request: ${options.method} ${options.uri.path}');
    }

    return queue.removeAt(0)(options);
  }

  @override
  void close({bool force = false}) {}
}

ResponseBody jsonResponse(int statusCode, Map<String, dynamic> body) {
  return ResponseBody.fromString(
    jsonEncode(body),
    statusCode,
    headers: {
      Headers.contentTypeHeader: [Headers.jsonContentType],
    },
  );
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const pathProviderChannel = MethodChannel('plugins.flutter.io/path_provider');

  Future<QueueAdapter> setUpApiServiceAdapter(
    Map<String, List<ResponseBody Function(RequestOptions)>> responsesByPath,
  ) async {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(pathProviderChannel, (call) async {
          if (call.method == 'getApplicationDocumentsDirectory') {
            return '/tmp/stayhub-test-cookies';
          }
          return null;
        });
    await ApiService().init();
    final originalAdapter = ApiService().client.httpClientAdapter;
    final adapter = QueueAdapter(responsesByPath);
    ApiService().client.httpClientAdapter = adapter;
    addTearDown(() {
      ApiService().client.httpClientAdapter = originalAdapter;
      ApiService().onUnauthorized = null;
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
          .setMockMethodCallHandler(pathProviderChannel, null);
    });

    return adapter;
  }

  test(
    'tenant session refresh does not trigger global unauthorized logout when admin guard returns 401',
    () async {
      var unauthorizedCalls = 0;
      ApiService().onUnauthorized = () => unauthorizedCalls++;
      await setUpApiServiceAdapter({
        '/api/v1/admin/me': [
          (_) => jsonResponse(401, {
            'status': false,
            'message': 'Unauthenticated.',
            'errorCode': 401,
            'result': null,
          }),
        ],
        '/api/v1/tenant/me': [
          (_) => jsonResponse(200, {
            'status': true,
            'message': 'OK',
            'errorCode': 200,
            'result': {
              'id': 1,
              'full_name': 'Nguyen Van A',
              'gender': 1,
              'phone': '0900000000',
              'email': 'a@example.com',
              'username': 'tenant_a',
              'status': 1,
              'identity_type': 1,
              'identity_number': '012345678901',
            },
          }),
        ],
      });

      final authController = AuthController();

      expect(await authController.checkSession(), isTrue);
      expect(authController.isTenant, isTrue);
      expect(unauthorizedCalls, 0);
    },
  );

  test('ordinary unauthorized requests still trigger global logout', () async {
    var unauthorizedCalls = 0;
    ApiService().onUnauthorized = () => unauthorizedCalls++;
    await setUpApiServiceAdapter({
      '/api/v1/tenant/contracts': [
        (_) => jsonResponse(401, {
          'status': false,
          'message': 'Unauthenticated.',
          'errorCode': 401,
          'result': null,
        }),
      ],
    });

    await expectLater(
      ApiService().get<List<dynamic>>(
        '/tenant/contracts',
        fromJsonT: (json) => json as List<dynamic>,
      ),
      throwsA(isA<ApiException>()),
    );
    expect(unauthorizedCalls, 1);
  });
}
