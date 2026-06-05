import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:dart_pusher_channels/dart_pusher_channels.dart';
import 'package:dio/dio.dart';
import '../config/app_config.dart';
import 'api_service.dart';

class WebSocketService extends ChangeNotifier {
  final ApiService _apiService = ApiService();
  PusherChannelsClient? _client;
  StreamSubscription? _statusSubscription;
  
  // Track active channel subscriptions and event streams
  final Map<String, dynamic> _activeChannels = {};
  final Map<String, dynamic> _eventSubscriptions = {};
  
  bool _isConnected = false;
  bool get isConnected => _isConnected;

  // Registered subscription callbacks for reconnection/lazy connection
  VoidCallback? _onAdminMaintenanceCallback;
  Function(Map<String, dynamic>)? _onTenantNotificationCallback;
  int? _tenantId;

  // Stream for broadcasting notification events to the application
  final StreamController<Map<String, dynamic>> _notificationStreamController = StreamController<Map<String, dynamic>>.broadcast();
  Stream<Map<String, dynamic>> get notificationsStream => _notificationStreamController.stream;

  // Stream for debugging logs to the UI
  final StreamController<String> _debugStreamController = StreamController<String>.broadcast();
  Stream<String> get debugStream => _debugStreamController.stream;

  /// Connect to the Laravel Reverb WebSocket server
  Future<void> connect() async {
    if (_client != null) {
      await disconnect();
    }

    final wsUrl = 'ws://${AppConfig.reverbHost}:${AppConfig.reverbPort}';
    debugPrint('WS: Connecting to Laravel Reverb at $wsUrl...');
    _debugStreamController.add('Đang kết nối WebSocket tới $wsUrl...');

    final options = PusherChannelsOptions.fromHost(
      scheme: AppConfig.reverbPort == 443 ? 'wss' : 'ws',
      host: AppConfig.reverbHost,
      port: AppConfig.reverbPort,
      key: AppConfig.reverbAppKey,
    );

    _client = PusherChannelsClient.websocket(
      options: options,
      connectionErrorHandler: (exception, trace, refresh) {
        debugPrint('WS Connection Error: $exception');
        _debugStreamController.add('Lỗi kết nối WebSocket: $exception');
        // Automatically attempt reconnection
        Future.delayed(const Duration(seconds: 5), () => refresh());
      },
    );

    _statusSubscription = _client!.onConnectionEstablished.listen((_) {
      debugPrint('WS Connection Status: Connected');
      _isConnected = true;
      _debugStreamController.add('Kết nối WebSocket thành công!');
      _triggerPendingSubscriptions();
      notifyListeners();
    });

    await _client!.connect();
  }

  /// Subscribe to private channel 'admin-maintenance' (public registration)
  void subscribeToAdminMaintenance(VoidCallback onMaintenanceCreated) {
    _onAdminMaintenanceCallback = onMaintenanceCreated;
    if (_isConnected) {
      _subscribeToAdminMaintenanceChannel();
    }
  }

  Future<void> _subscribeToAdminMaintenanceChannel() async {
    if (_client == null || !_isConnected) return;
    
    const channelName = 'private-admin-maintenance';
    if (_activeChannels.containsKey(channelName)) return;

    _debugStreamController.add('Đang đăng ký kênh $channelName...');

    try {
      final channel = _client!.privateChannel(
        channelName,
        authorizationDelegate: DioPrivateChannelAuthorizationDelegate(
          onAuthFailed: (exception, trace) {
            debugPrint('WS Auth failed for channel $channelName: $exception');
            _debugStreamController.add('Lỗi xác thực kênh $channelName: $exception');
          },
        ),
      );

      _activeChannels[channelName] = channel;

      StreamSubscription? successSubscription;
      successSubscription = channel.whenSubscriptionSucceeded().listen((event) {
        debugPrint('WS: Subscription to $channelName succeeded!');
        _debugStreamController.add('Đăng ký kênh $channelName thành công!');
        successSubscription?.cancel();
      });
      
      // Bind to all relevant maintenance events broadcast by backend
      final List<StreamSubscription> subscriptions = [];
      final maintenanceEvents = [
        'MaintenanceRequestCreated',
        'MaintenanceRequestAssigned',
        'MaintenanceRequestProcessing',
        'MaintenanceRequestCompleted',
        'MaintenanceFeedbackCreated',
      ];

      for (final eventName in maintenanceEvents) {
        final subscription = channel.bind(eventName).listen((event) {
          debugPrint('WS Event: $eventName -> ${event.data}');
          if (_onAdminMaintenanceCallback != null) {
            _onAdminMaintenanceCallback!();
          }
          
          Map<String, dynamic>? parsedData;
          try {
            final rawData = event.data;
            if (rawData != null) {
              if (rawData is String) {
                parsedData = jsonDecode(rawData) as Map<String, dynamic>;
              } else if (rawData is Map) {
                parsedData = Map<String, dynamic>.from(rawData);
              }
            }
          } catch (e) {
            debugPrint('WS Error decoding JSON: $e');
          }

          // Broadcast event locally
          _notificationStreamController.add({
            'type': 'maintenance_created',
            'data': parsedData ?? event.data,
          });
        });
        subscriptions.add(subscription);
      }

      _eventSubscriptions[channelName] = subscriptions;
      channel.subscribe();
      debugPrint('WS: Subscribed to private channel $channelName successfully!');
    } catch (e) {
      debugPrint('WS Subscription Error ($channelName): $e');
    }
  }

  /// Subscribe to private channel 'tenant.{id}' (public registration)
  void subscribeToTenantNotifications(int tenantId, Function(Map<String, dynamic> notification) onNotificationReceived) {
    _tenantId = tenantId;
    _onTenantNotificationCallback = onNotificationReceived;
    if (_isConnected) {
      _subscribeToTenantChannel(tenantId);
    }
  }

  Future<void> _subscribeToTenantChannel(int tenantId) async {
    if (_client == null || !_isConnected) return;

    final channelName = 'private-tenant.$tenantId';
    if (_activeChannels.containsKey(channelName)) return;

    _debugStreamController.add('Đang đăng ký kênh $channelName...');

    try {
      final channel = _client!.privateChannel(
        channelName,
        authorizationDelegate: DioPrivateChannelAuthorizationDelegate(
          onAuthFailed: (exception, trace) {
            debugPrint('WS Auth failed for channel $channelName: $exception');
            _debugStreamController.add('Lỗi xác thực kênh $channelName: $exception');
          },
        ),
      );

      _activeChannels[channelName] = channel;

      StreamSubscription? successSubscription;
      successSubscription = channel.whenSubscriptionSucceeded().listen((event) {
        debugPrint('WS: Subscription to $channelName succeeded!');
        _debugStreamController.add('Đăng ký kênh $channelName thành công!');
        successSubscription?.cancel();
      });

      // Bind to 'NotificationSent' event broadcast by backend
      final subscription = channel.bind('NotificationSent').listen((event) {
        debugPrint('WS Event: NotificationSent -> ${event.data}');
        try {
          final rawData = event.data;
          if (rawData != null && _onTenantNotificationCallback != null) {
            Map<String, dynamic> decoded;
            if (rawData is String) {
              decoded = jsonDecode(rawData) as Map<String, dynamic>;
            } else if (rawData is Map) {
              decoded = Map<String, dynamic>.from(rawData);
            } else {
              throw Exception('Unexpected data format: ${rawData.runtimeType}');
            }
            if (decoded['notification'] != null) {
              _onTenantNotificationCallback!(decoded['notification'] as Map<String, dynamic>);
            }
          }
        } catch (e) {
          debugPrint('WS Error handling NotificationSent: $e');
        }
      });

      _eventSubscriptions[channelName] = subscription;
      channel.subscribe();
      debugPrint('WS: Subscribed to private channel $channelName successfully!');
    } catch (e) {
      debugPrint('WS Subscription Error ($channelName): $e');
    }
  }

  /// Trigger all pending subscriptions after connecting
  void _triggerPendingSubscriptions() {
    if (_onAdminMaintenanceCallback != null) {
      _subscribeToAdminMaintenanceChannel();
    }
    if (_tenantId != null && _onTenantNotificationCallback != null) {
      _subscribeToTenantChannel(_tenantId!);
    }
  }

  /// Unsubscribe from a channel
  Future<void> unsubscribe(String channelName) async {
    final channel = _activeChannels.remove(channelName);
    final subscription = _eventSubscriptions.remove(channelName);
    
    if (subscription != null) {
      if (subscription is List) {
        for (final sub in subscription) {
          if (sub is StreamSubscription) {
            await sub.cancel();
          }
        }
      } else if (subscription is StreamSubscription) {
        await subscription.cancel();
      }
    }
    
    if (channel != null && _client != null) {
      await channel.unsubscribe();
      debugPrint('WS: Unsubscribed from channel $channelName');
    }
  }

  /// Disconnect the socket client
  Future<void> disconnect() async {
    debugPrint('WS: Closing Reverb WebSocket connection...');
    
    _onAdminMaintenanceCallback = null;
    _onTenantNotificationCallback = null;
    _tenantId = null;

    // Cancel subscriptions
    for (final sub in _eventSubscriptions.values) {
      if (sub is List) {
        for (final item in sub) {
          if (item is StreamSubscription) {
            await item.cancel();
          }
        }
      } else if (sub is StreamSubscription) {
        await sub.cancel();
      }
    }
    _eventSubscriptions.clear();
    _activeChannels.clear();

    if (_statusSubscription != null) {
      await _statusSubscription!.cancel();
      _statusSubscription = null;
    }

    if (_client != null) {
      await _client!.disconnect();
      _client = null;
    }
    
    _isConnected = false;
    notifyListeners();
  }
  
  @override
  void dispose() {
    disconnect();
    _notificationStreamController.close();
    _debugStreamController.close();
    super.dispose();
  }
}

class DioPrivateChannelAuthorizationDelegate
    implements
        EndpointAuthorizableChannelAuthorizationDelegate<
            PrivateChannelAuthorizationData> {
  final ApiService _apiService = ApiService();

  @override
  final EndpointAuthFailedCallback? onAuthFailed;

  DioPrivateChannelAuthorizationDelegate({this.onAuthFailed});

  @override
  Future<PrivateChannelAuthorizationData> authorizationData(
    String socketId,
    String channelName,
  ) async {
    try {
      await _apiService.init();
      final authHeaders = await _apiService.getAuthHeaders();

      final response = await _apiService.client.post<Map<String, dynamic>>(
        '${AppConfig.apiOrigin}/broadcasting/auth',
        data: {
          'socket_id': socketId,
          'channel_name': channelName,
        },
        options: Options(
          headers: authHeaders,
          contentType: Headers.formUrlEncodedContentType,
        ),
      );

      final data = response.data;
      if (data == null || data['auth'] == null) {
        throw Exception('Invalid auth response: $data');
      }

      return PrivateChannelAuthorizationData(
        authKey: data['auth'] as String,
      );
    } catch (e, stack) {
      String errMsg = e.toString();
      if (e is DioException) {
        final resp = e.response;
        if (resp != null) {
          errMsg = 'HTTP ${resp.statusCode}: ${resp.data}';
        }
      }
      if (onAuthFailed != null) {
        onAuthFailed!(Exception(errMsg), stack);
      }
      rethrow;
    }
  }
}
