import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:dart_pusher_channels/dart_pusher_channels.dart';
import 'package:dio/dio.dart';
import '../config/app_config.dart';
import 'api_service.dart';
import 'realtime_contract.dart';

class WebSocketService extends ChangeNotifier with WidgetsBindingObserver {
  PusherChannelsClient? _client;
  StreamSubscription? _statusSubscription;
  Timer? _reconnectTimer;

  // Track active channel subscriptions and event streams
  final Map<String, dynamic> _activeChannels = {};
  final Map<String, dynamic> _eventSubscriptions = {};

  bool _isConnected = false;
  bool _isConnecting = false;
  bool _manualDisconnect = false;
  bool _isAppInBackground = false;
  bool _isDisposed = false;
  int _reconnectAttempt = 0;
  bool get isConnected => _isConnected;

  // Registered subscription callbacks for reconnection/lazy connection
  VoidCallback? _onAdminMaintenanceCallback;
  Function(Map<String, dynamic>)? _onTenantNotificationCallback;
  final Map<String, Function(Map<String, dynamic>)> _onChatMessageCallbacks =
      {};
  final Map<String, Function(Map<String, dynamic>)> _onChatReadCallbacks = {};
  Function(Map<String, dynamic>)? _onContractExpiredCallback;
  final Set<int> _adminBuildingIds = {};
  int? _tenantId;
  int? _adminChatId;
  int? _tenantChatId;
  int? _conversationId;

  WebSocketService() {
    WidgetsBinding.instance.addObserver(this);
  }

  // Stream for broadcasting notification events to the application
  final StreamController<Map<String, dynamic>> _notificationStreamController =
      StreamController<Map<String, dynamic>>.broadcast();
  Stream<Map<String, dynamic>> get notificationsStream =>
      _notificationStreamController.stream;

  // Stream for debugging logs to the UI
  final StreamController<String> _debugStreamController =
      StreamController<String>.broadcast();
  Stream<String> get debugStream => _debugStreamController.stream;

  void _addDebugLog(String message) {
    if (!_debugStreamController.isClosed) {
      _debugStreamController.add(message);
    }
  }

  void _addNotificationEvent(Map<String, dynamic> event) {
    if (!_notificationStreamController.isClosed) {
      _notificationStreamController.add(event);
    }
  }

  void _notifyIfActive() {
    if (!_isDisposed) {
      notifyListeners();
    }
  }

  /// Connect to the Laravel Reverb WebSocket server
  Future<void> connect() async {
    if (_isDisposed) return;

    _manualDisconnect = false;

    if (_client != null && (_isConnected || _isConnecting)) {
      debugPrint('WS: Already connected.');
      return;
    }

    _cancelReconnectTimer();
    _isConnecting = true;

    if (_client != null) {
      await _closeConnectionOnly(cancelReconnect: false);
    }

    _isConnecting = true;

    final scheme = AppConfig.reverbPort == 443 ? 'wss' : 'ws';
    final wsUrl = '$scheme://${AppConfig.reverbHost}:${AppConfig.reverbPort}';
    debugPrint('WS: Connecting to Laravel Reverb at $wsUrl...');
    _addDebugLog('Đang kết nối WebSocket tới $wsUrl...');

    final options = PusherChannelsOptions.fromHost(
      scheme: scheme,
      host: AppConfig.reverbHost,
      port: AppConfig.reverbPort == 443 ? null : AppConfig.reverbPort,
      key: AppConfig.reverbAppKey,
    );

    _client = PusherChannelsClient.websocket(
      options: options,
      connectionErrorHandler: (exception, trace, refresh) {
        debugPrint('WS Connection Error: $exception');
        _addDebugLog('Lỗi kết nối WebSocket: $exception');
        _isConnected = false;
        _isConnecting = false;
        _notifyIfActive();
        _scheduleReconnect(reason: exception.toString());
      },
    );

    _statusSubscription = _client!.lifecycleStream.listen(
      _handleLifecycleState,
    );

    try {
      await _client!.connect();
    } catch (e) {
      debugPrint('WS Connect Error: $e');
      _addDebugLog('Lỗi mở WebSocket: $e');
      _isConnected = false;
      _isConnecting = false;
      _notifyIfActive();
      _scheduleReconnect(reason: e.toString());
    }
  }

  /// Forces a complete clean reconnection to the WebSocket server
  Future<void> forceReconnect() async {
    debugPrint('WS: Forcing clean reconnect...');
    _manualDisconnect = false;
    _cancelReconnectTimer();
    _isConnected = false;
    await _closeConnectionOnly(cancelReconnect: false);
    await connect();
  }

  void _ensureConnected() {
    if (_isDisposed || _manualDisconnect || _isAppInBackground) return;
    if (_isConnected || _isConnecting) return;

    unawaited(connect());
  }

  void _handleLifecycleState(PusherChannelsClientLifeCycleState state) {
    debugPrint('WS Lifecycle: $state');

    if (state == PusherChannelsClientLifeCycleState.establishedConnection) {
      _isConnected = true;
      _isConnecting = false;
      _reconnectAttempt = 0;
      _cancelReconnectTimer();
      _addDebugLog('Kết nối WebSocket thành công!');
      _resubscribeActiveChannels();
      _triggerPendingSubscriptions();
      _notifyIfActive();
      return;
    }

    if (state == PusherChannelsClientLifeCycleState.pendingConnection ||
        state == PusherChannelsClientLifeCycleState.reconnecting) {
      _isConnected = false;
      _isConnecting = true;
      _notifyIfActive();
      return;
    }

    if (state == PusherChannelsClientLifeCycleState.connectionError ||
        state == PusherChannelsClientLifeCycleState.gotPusherError ||
        state == PusherChannelsClientLifeCycleState.disconnected ||
        state == PusherChannelsClientLifeCycleState.disposed) {
      _isConnected = false;
      _isConnecting = false;
      _notifyIfActive();

      if (state != PusherChannelsClientLifeCycleState.disposed ||
          !_manualDisconnect) {
        _scheduleReconnect(reason: _reconnectReasonFor(state));
      }
    }
  }

  String _reconnectReasonFor(PusherChannelsClientLifeCycleState state) {
    switch (state) {
      case PusherChannelsClientLifeCycleState.connectionError:
        return 'connection error';
      case PusherChannelsClientLifeCycleState.gotPusherError:
        return 'Pusher error';
      case PusherChannelsClientLifeCycleState.disconnected:
        return 'disconnected';
      case PusherChannelsClientLifeCycleState.disposed:
        return 'disposed';
      default:
        return state.name;
    }
  }

  void _scheduleReconnect({String? reason}) {
    if (_isDisposed || _manualDisconnect || _isAppInBackground) return;
    if (_reconnectTimer?.isActive ?? false) return;

    const retryDelays = [2, 5, 10, 20, 30];
    final delaySeconds =
        retryDelays[_reconnectAttempt < retryDelays.length
            ? _reconnectAttempt
            : retryDelays.length - 1];
    _reconnectAttempt++;

    final message = reason == null || reason.isEmpty
        ? 'WebSocket mất kết nối, thử lại sau ${delaySeconds}s...'
        : 'WebSocket mất kết nối ($reason), thử lại sau ${delaySeconds}s...';
    debugPrint('WS: $message');
    _addDebugLog(message);

    _reconnectTimer = Timer(Duration(seconds: delaySeconds), () {
      if (_isDisposed || _manualDisconnect || _isAppInBackground) return;
      debugPrint('WS: Retrying WebSocket connection...');
      unawaited(forceReconnect());
    });
  }

  void _cancelReconnectTimer() {
    _reconnectTimer?.cancel();
    _reconnectTimer = null;
  }

  void _resubscribeActiveChannels() {
    for (final entry in _activeChannels.entries) {
      try {
        debugPrint('WS: Re-subscribing active channel ${entry.key}...');
        entry.value.subscribeIfNotUnsubscribed();
      } catch (e) {
        debugPrint('WS: Failed to re-subscribe ${entry.key}: $e');
      }
    }
  }

  Future<void> _handleChannelAuthFailed(
    String channelName,
    Object exception,
  ) async {
    debugPrint('WS: Channel auth failed for $channelName: $exception');
    await _removeChannelLocally(channelName);
    _scheduleReconnect(reason: 'auth $channelName');
  }

  Future<void> _removeChannelLocally(String channelName) async {
    _activeChannels.remove(channelName);
    final subscription = _eventSubscriptions.remove(channelName);

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

  String _privateChannelName(String channelName) {
    return StayHubRealtimeContract.privateChannelName(channelName);
  }

  /// Subscribe to private channel 'admin-maintenance' (public registration)
  void subscribeToAdminMaintenance(VoidCallback onMaintenanceCreated) {
    _onAdminMaintenanceCallback = onMaintenanceCreated;
    if (_isConnected) {
      _subscribeToAdminMaintenanceChannel();
    } else {
      _ensureConnected();
    }
  }

  Future<void> _subscribeToAdminMaintenanceChannel() async {
    if (_client == null || !_isConnected) return;

    const channelName = StayHubRealtimeContract.adminMaintenanceChannel;
    if (_activeChannels.containsKey(channelName)) return;

    _addDebugLog('Đang đăng ký kênh $channelName...');

    try {
      final channel = _client!.privateChannel(
        _privateChannelName(channelName),
        authorizationDelegate: DioPrivateChannelAuthorizationDelegate(
          onAuthFailed: (exception, trace) {
            debugPrint('WS Auth failed for channel $channelName: $exception');
            _addDebugLog(
              'Lỗi xác thực kênh $channelName: $exception',
            );
            unawaited(_handleChannelAuthFailed(channelName, exception));
          },
        ),
      );

      _activeChannels[channelName] = channel;

      StreamSubscription? successSubscription;
      successSubscription = channel.whenSubscriptionSucceeded().listen((event) {
        debugPrint('WS: Subscription to $channelName succeeded!');
        _addDebugLog('Đăng ký kênh $channelName thành công!');
        successSubscription?.cancel();
      });

      // Bind to all relevant maintenance events broadcast by backend
      final List<StreamSubscription> subscriptions = [];
      const maintenanceEvents =
          StayHubRealtimeContract.adminMaintenanceEvents;

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
          _addNotificationEvent({
            'type': 'maintenance_created',
            'data': parsedData ?? event.data,
          });
        });
        subscriptions.add(subscription);
      }

      // Bind to ContractDepositPaid event on admin channel
      final depositSubscription = channel.bind('ContractDepositPaid').listen((
        event,
      ) {
        debugPrint('WS Event: ContractDepositPaid (Admin) -> ${event.data}');
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
        _addNotificationEvent({
          'type': 'admin_contract_deposit_paid',
          'data': parsedData ?? event.data,
        });
      });
      subscriptions.add(depositSubscription);

      // Bind to NotificationSent event on admin channel
      final adminNotificationSubscription = channel
          .bind('NotificationSent')
          .listen((event) {
            debugPrint('WS Event: NotificationSent (Admin) -> ${event.data}');
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
            _addNotificationEvent({
              'type': 'admin_notification_sent',
              'data': parsedData?['notification'] ?? parsedData ?? event.data,
            });
          });
      subscriptions.add(adminNotificationSubscription);

      // Bind to InvoicePaid event on admin channel
      final adminInvoicePaidSubscription = channel.bind('InvoicePaid').listen((
        event,
      ) {
        debugPrint('WS Event: InvoicePaid (Admin) -> ${event.data}');
        try {
          final rawData = event.data;
          if (rawData != null) {
            Map<String, dynamic> decoded;
            if (rawData is String) {
              decoded = jsonDecode(rawData) as Map<String, dynamic>;
            } else if (rawData is Map) {
              decoded = Map<String, dynamic>.from(rawData);
            } else {
              throw Exception('Unexpected data format');
            }
            _addNotificationEvent({
              'type': 'admin_invoice_paid',
              'data': decoded['invoice'] ?? decoded,
            });
          }
        } catch (e) {
          debugPrint('WS Error handling InvoicePaid (Admin): $e');
        }
      });
      subscriptions.add(adminInvoicePaidSubscription);

      final adminInvoiceReissuedSubscription = channel
          .bind('InvoiceReissued')
          .listen((event) {
            debugPrint('WS Event: InvoiceReissued (Admin) -> ${event.data}');
            try {
              final rawData = event.data;
              if (rawData != null) {
                Map<String, dynamic> decoded;
                if (rawData is String) {
                  decoded = jsonDecode(rawData) as Map<String, dynamic>;
                } else if (rawData is Map) {
                  decoded = Map<String, dynamic>.from(rawData);
                } else {
                  throw Exception('Unexpected data format');
                }
                _addNotificationEvent({
                  'type': 'admin_invoice_reissued',
                  'data': decoded['invoice'] ?? decoded,
                });
              }
            } catch (e) {
              debugPrint('WS Error handling InvoiceReissued (Admin): $e');
            }
          });
      subscriptions.add(adminInvoiceReissuedSubscription);

      _eventSubscriptions[channelName] = subscriptions;
      channel.subscribe();
      debugPrint(
        'WS: Subscribed to private channel $channelName successfully!',
      );
    } catch (e) {
      debugPrint('WS Subscription Error ($channelName): $e');
    }
  }

  /// Subscribe to private channel 'tenant.{id}' (public registration)
  void subscribeToTenantNotifications(
    int tenantId,
    Function(Map<String, dynamic> notification) onNotificationReceived,
  ) {
    _tenantId = tenantId;
    _onTenantNotificationCallback = onNotificationReceived;
    if (_isConnected) {
      _subscribeToTenantChannel(tenantId);
    } else {
      _ensureConnected();
    }
  }

  void ensureTenantNotificationChannel(int tenantId) {
    _tenantId = tenantId;
    if (_isConnected) {
      _subscribeToTenantChannel(tenantId);
    } else {
      _ensureConnected();
    }
  }

  Future<void> _subscribeToTenantChannel(int tenantId) async {
    if (_client == null || !_isConnected) return;

    final channelName = StayHubRealtimeContract.tenantChannel(tenantId);
    if (_activeChannels.containsKey(channelName)) return;

    _addDebugLog('Đang đăng ký kênh $channelName...');

    try {
      final channel = _client!.privateChannel(
        _privateChannelName(channelName),
        authorizationDelegate: DioPrivateChannelAuthorizationDelegate(
          onAuthFailed: (exception, trace) {
            debugPrint('WS Auth failed for channel $channelName: $exception');
            _addDebugLog(
              'Lỗi xác thực kênh $channelName: $exception',
            );
            unawaited(_handleChannelAuthFailed(channelName, exception));
          },
        ),
      );

      _activeChannels[channelName] = channel;

      StreamSubscription? successSubscription;
      successSubscription = channel.whenSubscriptionSucceeded().listen((event) {
        debugPrint('WS: Subscription to $channelName succeeded!');
        _addDebugLog('Đăng ký kênh $channelName thành công!');
        successSubscription?.cancel();
      });

      final List<StreamSubscription> subscriptions = [];

      // Bind to 'NotificationSent' event broadcast by backend
      final notificationSub = channel.bind('NotificationSent').listen((event) {
        debugPrint('WS Event: NotificationSent -> ${event.data}');
        try {
          final rawData = event.data;
          if (rawData != null) {
            Map<String, dynamic> decoded;
            if (rawData is String) {
              decoded = jsonDecode(rawData) as Map<String, dynamic>;
            } else if (rawData is Map) {
              decoded = Map<String, dynamic>.from(rawData);
            } else {
              throw Exception('Unexpected data format: ${rawData.runtimeType}');
            }
            if (decoded['notification'] != null) {
              final notificationData =
                  decoded['notification'] as Map<String, dynamic>;
              _onTenantNotificationCallback?.call(notificationData);

              // Broadcast event locally to other screens
              _addNotificationEvent({
                'type': 'notification_sent',
                'data': notificationData,
              });
            }
          }
        } catch (e) {
          debugPrint('WS Error handling NotificationSent: $e');
        }
      });
      subscriptions.add(notificationSub);

      // Bind to 'ContractDepositPaid' event broadcast by backend
      final depositSub = channel.bind('ContractDepositPaid').listen((event) {
        debugPrint('WS Event: ContractDepositPaid -> ${event.data}');
        try {
          final rawData = event.data;
          if (rawData != null) {
            Map<String, dynamic> decoded;
            if (rawData is String) {
              decoded = jsonDecode(rawData) as Map<String, dynamic>;
            } else if (rawData is Map) {
              decoded = Map<String, dynamic>.from(rawData);
            } else {
              throw Exception('Unexpected data format: ${rawData.runtimeType}');
            }
            // Broadcast event locally
            _addNotificationEvent({
              'type': 'contract_deposit_paid',
              'data': decoded['contract'] ?? decoded,
            });
          }
        } catch (e) {
          debugPrint('WS Error handling ContractDepositPaid: $e');
        }
      });
      subscriptions.add(depositSub);

      // Bind to InvoicePaid event on tenant channel
      final invoicePaidSub = channel.bind('InvoicePaid').listen((event) {
        debugPrint('WS Event: InvoicePaid -> ${event.data}');
        try {
          final rawData = event.data;
          if (rawData != null) {
            Map<String, dynamic> decoded;
            if (rawData is String) {
              decoded = jsonDecode(rawData) as Map<String, dynamic>;
            } else if (rawData is Map) {
              decoded = Map<String, dynamic>.from(rawData);
            } else {
              throw Exception('Unexpected data format');
            }
            _addNotificationEvent({
              'type': 'invoice_paid',
              'data': decoded['invoice'] ?? decoded,
            });
          }
        } catch (e) {
          debugPrint('WS Error handling InvoicePaid: $e');
        }
      });
      subscriptions.add(invoicePaidSub);

      // Bind to InvoiceIssued event on tenant channel
      final invoiceIssuedSub = channel.bind('InvoiceIssued').listen((event) {
        debugPrint('WS Event: InvoiceIssued -> ${event.data}');
        try {
          final rawData = event.data;
          if (rawData != null) {
            Map<String, dynamic> decoded;
            if (rawData is String) {
              decoded = jsonDecode(rawData) as Map<String, dynamic>;
            } else if (rawData is Map) {
              decoded = Map<String, dynamic>.from(rawData);
            } else {
              throw Exception('Unexpected data format');
            }
            _addNotificationEvent({
              'type': 'invoice_issued',
              'data': decoded['invoice'] ?? decoded,
            });
          }
        } catch (e) {
          debugPrint('WS Error handling InvoiceIssued: $e');
        }
      });
      subscriptions.add(invoiceIssuedSub);

      final invoiceReissuedSub = channel.bind('InvoiceReissued').listen((
        event,
      ) {
        debugPrint('WS Event: InvoiceReissued -> ${event.data}');
        try {
          final rawData = event.data;
          if (rawData != null) {
            Map<String, dynamic> decoded;
            if (rawData is String) {
              decoded = jsonDecode(rawData) as Map<String, dynamic>;
            } else if (rawData is Map) {
              decoded = Map<String, dynamic>.from(rawData);
            } else {
              throw Exception('Unexpected data format');
            }
            _addNotificationEvent({
              'type': 'invoice_reissued',
              'data': decoded['invoice'] ?? decoded,
            });
          }
        } catch (e) {
          debugPrint('WS Error handling InvoiceReissued: $e');
        }
      });
      subscriptions.add(invoiceReissuedSub);

      for (final eventName in StayHubRealtimeContract.tenantMaintenanceEvents) {
        subscriptions.add(
          channel.bind(eventName).listen((event) {
            debugPrint('WS Event: $eventName (Tenant) -> ${event.data}');
            final decoded = _decodeEventData(event.data);
            if (decoded != null) {
              _addNotificationEvent({
                'type': StayHubRealtimeContract.localMaintenanceEventType(
                  eventName,
                ),
                'data': decoded['request'] ?? decoded,
              });
            }
          }),
        );
      }

      _eventSubscriptions[channelName] = subscriptions;
      channel.subscribe();
      debugPrint(
        'WS: Subscribed to private channel $channelName successfully!',
      );
    } catch (e) {
      debugPrint('WS Subscription Error ($channelName): $e');
    }
  }

  /// Trigger all pending subscriptions after connecting
  void _triggerPendingSubscriptions() {
    if (_onAdminMaintenanceCallback != null) {
      _subscribeToAdminMaintenanceChannel();
    }
    if (_tenantId != null) {
      _subscribeToTenantChannel(_tenantId!);
    }
    for (final channelName in _onChatMessageCallbacks.keys) {
      _subscribeToChatChannel(channelName);
    }
    for (final buildingId in _adminBuildingIds) {
      _subscribeToAdminBuildingChannel(buildingId);
    }
  }

  void subscribeToAdminBuildingContractExpirations(
    List<int> buildingIds, {
    required Function(Map<String, dynamic>) onContractExpired,
  }) {
    final nextBuildingIds = buildingIds.where((id) => id > 0).toSet();
    final staleBuildingIds = _adminBuildingIds.difference(nextBuildingIds);
    for (final buildingId in staleBuildingIds) {
      unawaited(
        unsubscribe(StayHubRealtimeContract.adminBuildingChannel(buildingId)),
      );
    }

    _adminBuildingIds
      ..clear()
      ..addAll(nextBuildingIds);
    _onContractExpiredCallback = onContractExpired;

    if (_isConnected) {
      for (final buildingId in _adminBuildingIds) {
        _subscribeToAdminBuildingChannel(buildingId);
      }
    } else {
      _ensureConnected();
    }
  }

  Future<void> _subscribeToAdminBuildingChannel(int buildingId) async {
    if (_client == null || !_isConnected) return;

    final channelName = StayHubRealtimeContract.adminBuildingChannel(buildingId);
    if (_activeChannels.containsKey(channelName)) return;

    try {
      final channel = _client!.privateChannel(
        _privateChannelName(channelName),
        authorizationDelegate: DioPrivateChannelAuthorizationDelegate(
          onAuthFailed: (exception, trace) {
            debugPrint('WS Auth failed for channel $channelName: $exception');
            _addDebugLog(
              'Lỗi xác thực kênh $channelName: $exception',
            );
            unawaited(_handleChannelAuthFailed(channelName, exception));
          },
        ),
      );
      _activeChannels[channelName] = channel;

      final subscription = channel.bind('ContractExpired').listen((event) {
        final decoded = _decodeEventData(event.data);
        if (decoded != null) {
          final contract = decoded['contract'] is Map
              ? Map<String, dynamic>.from(decoded['contract'] as Map)
              : decoded;
          _onContractExpiredCallback?.call(contract);
          _addNotificationEvent({
            'type': 'contract_expired',
            'data': contract,
          });
        }
      });

      _eventSubscriptions[channelName] = subscription;
      channel.subscribe();
      debugPrint(
        'WS: Subscribed to private channel $channelName successfully!',
      );
    } catch (e) {
      debugPrint('WS Subscription Error ($channelName): $e');
    }
  }

  void subscribeToAdminChat(
    int adminId, {
    required Function(Map<String, dynamic>) onMessage,
    Function(Map<String, dynamic>)? onRead,
  }) {
    final channelName = StayHubRealtimeContract.chatAdminChannel(adminId);
    _adminChatId = adminId;
    _onChatMessageCallbacks[channelName] = onMessage;
    if (onRead != null) {
      _onChatReadCallbacks[channelName] = onRead;
    }
    if (_isConnected) {
      _subscribeToChatInboxChannel(channelName);
    } else {
      _ensureConnected();
    }
  }

  void subscribeToTenantChat(
    int tenantId, {
    required Function(Map<String, dynamic>) onMessage,
    Function(Map<String, dynamic>)? onRead,
  }) {
    final channelName = StayHubRealtimeContract.chatTenantChannel(tenantId);
    _tenantChatId = tenantId;
    _onChatMessageCallbacks[channelName] = onMessage;
    if (onRead != null) {
      _onChatReadCallbacks[channelName] = onRead;
    }
    if (_isConnected) {
      _subscribeToChatInboxChannel(channelName);
    } else {
      _ensureConnected();
    }
  }

  void subscribeToChatConversation(
    int conversationId, {
    required Function(Map<String, dynamic>) onMessage,
    Function(Map<String, dynamic>)? onRead,
  }) {
    final channelName = StayHubRealtimeContract.chatConversationChannel(
      conversationId,
    );
    _conversationId = conversationId;
    _onChatMessageCallbacks[channelName] = onMessage;
    if (onRead != null) {
      _onChatReadCallbacks[channelName] = onRead;
    }
    if (_isConnected) {
      _subscribeToChatConversationChannel(conversationId);
    } else {
      _ensureConnected();
    }
  }

  Future<void> _subscribeToChatConversationChannel(int conversationId) async {
    await _subscribeToChatChannel(
      StayHubRealtimeContract.chatConversationChannel(conversationId),
    );
  }

  Future<void> _subscribeToChatInboxChannel(String channelName) async {
    await _subscribeToChatChannel(channelName);
  }

  Future<void> _subscribeToChatChannel(String channelName) async {
    if (_client == null || !_isConnected) return;
    if (_activeChannels.containsKey(channelName)) return;

    try {
      final channel = _client!.privateChannel(
        _privateChannelName(channelName),
        authorizationDelegate: DioPrivateChannelAuthorizationDelegate(
          onAuthFailed: (exception, trace) {
            debugPrint('WS Auth failed for channel $channelName: $exception');
            _addDebugLog(
              'Lỗi xác thực kênh $channelName: $exception',
            );
            unawaited(_handleChannelAuthFailed(channelName, exception));
          },
        ),
      );
      _activeChannels[channelName] = channel;

      final List<StreamSubscription> subscriptions = [];
      subscriptions.add(
        channel.bind('ChatMessageSent').listen((event) {
          final decoded = _decodeEventData(event.data);
          if (decoded != null) {
            _onChatMessageCallbacks[channelName]?.call(decoded);
            _addNotificationEvent({
              'type': 'chat_message_sent',
              'data': decoded,
            });
          }
        }),
      );
      subscriptions.add(
        channel.bind('ChatConversationRead').listen((event) {
          final decoded = _decodeEventData(event.data);
          if (decoded != null) {
            _onChatReadCallbacks[channelName]?.call(decoded);
            _addNotificationEvent({
              'type': 'chat_conversation_read',
              'data': decoded,
            });
          }
        }),
      );
      subscriptions.add(
        channel.bind('NotificationSent').listen((event) {
          final decoded = _decodeEventData(event.data);
          if (decoded != null) {
            _addNotificationEvent({
              'type': channelName.startsWith(
                StayHubRealtimeContract.chatAdminChannelPrefix,
              )
                  ? 'admin_notification_sent'
                  : 'notification_sent',
              'data': decoded['notification'] ?? decoded,
            });
          }
        }),
      );

      _eventSubscriptions[channelName] = subscriptions;
      channel.subscribe();
      debugPrint(
        'WS: Subscribed to private channel $channelName successfully!',
      );
    } catch (e) {
      debugPrint('WS Subscription Error ($channelName): $e');
    }
  }

  void unsubscribeFromChatConversation(int conversationId) {
    final channelName = StayHubRealtimeContract.chatConversationChannel(
      conversationId,
    );
    _onChatMessageCallbacks.remove(channelName);
    _onChatReadCallbacks.remove(channelName);
    unsubscribe(channelName);
    if (_conversationId == conversationId) {
      _conversationId = null;
    }
  }

  void unsubscribeFromTenantChat(int tenantId) {
    final channelName = StayHubRealtimeContract.chatTenantChannel(tenantId);
    _onChatMessageCallbacks.remove(channelName);
    _onChatReadCallbacks.remove(channelName);
    unsubscribe(channelName);
    if (_tenantChatId == tenantId) {
      _tenantChatId = null;
    }
  }

  void unsubscribeFromAdminChat(int adminId) {
    final channelName = StayHubRealtimeContract.chatAdminChannel(adminId);
    _onChatMessageCallbacks.remove(channelName);
    _onChatReadCallbacks.remove(channelName);
    unsubscribe(channelName);
    if (_adminChatId == adminId) {
      _adminChatId = null;
    }
  }

  Map<String, dynamic>? _decodeEventData(dynamic rawData) {
    try {
      if (rawData is String) {
        return jsonDecode(rawData) as Map<String, dynamic>;
      }
      if (rawData is Map) {
        return Map<String, dynamic>.from(rawData);
      }
    } catch (e) {
      debugPrint('WS decode event error: $e');
    }
    return null;
  }

  /// Unsubscribe from a channel
  Future<void> unsubscribe(String channelName) async {
    final channel = _activeChannels.remove(channelName);
    await _removeChannelLocally(channelName);

    if (channel != null && _client != null) {
      await channel.unsubscribe();
      debugPrint('WS: Unsubscribed from channel $channelName');
    }
  }

  /// Disconnect the socket client
  Future<void> disconnect() async {
    debugPrint('WS: Closing Reverb WebSocket connection...');
    _manualDisconnect = true;
    _cancelReconnectTimer();

    _onAdminMaintenanceCallback = null;
    _onTenantNotificationCallback = null;
    _onChatMessageCallbacks.clear();
    _onChatReadCallbacks.clear();
    _onContractExpiredCallback = null;
    _adminBuildingIds.clear();
    _tenantId = null;
    _adminChatId = null;
    _tenantChatId = null;
    _conversationId = null;

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
    _isConnecting = false;
    _notifyIfActive();
  }

  /// Closes the current connection and cleans up active subscriptions/channels, but preserves callbacks/configs.
  Future<void> _closeConnectionOnly({bool cancelReconnect = true}) async {
    debugPrint('WS: Closing connection only, keeping callbacks...');

    if (cancelReconnect) {
      _cancelReconnectTimer();
    }

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
    _isConnecting = false;
    _notifyIfActive();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    debugPrint('WS AppLifecycleState changed to: $state');
    if (state == AppLifecycleState.paused ||
        state == AppLifecycleState.inactive) {
      _isAppInBackground = true;
      debugPrint('WS: App backgrounded, closing connection to save battery...');
      _closeConnectionOnly();
    } else if (state == AppLifecycleState.resumed) {
      _isAppInBackground = false;
      _manualDisconnect = false;
      debugPrint('WS: App resumed, checking connection status...');

      final bool hasActiveSubscriptions =
          _onAdminMaintenanceCallback != null ||
          _tenantId != null ||
          _adminChatId != null ||
          _tenantChatId != null ||
          _conversationId != null ||
          _adminBuildingIds.isNotEmpty;

      if (hasActiveSubscriptions) {
        debugPrint(
          'WS: App resumed with active subscriptions. Reconnecting...',
        );
        _addDebugLog(
          'Ứng dụng hoạt động trở lại, đang kết nối lại WebSocket...',
        );
        forceReconnect();
      }
    }
  }

  @override
  void dispose() {
    _isDisposed = true;
    _cancelReconnectTimer();
    WidgetsBinding.instance.removeObserver(this);
    unawaited(disconnect());
    _notificationStreamController.close();
    _debugStreamController.close();
    super.dispose();
  }
}

class DioPrivateChannelAuthorizationDelegate
    implements
        EndpointAuthorizableChannelAuthorizationDelegate<
          PrivateChannelAuthorizationData
        > {
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
      await _apiService.ensureCsrfTokenForAuth();
      final authHeaders = await _apiService.getAuthHeaders();
      debugPrint('WS Auth: Authorizing $channelName with socket $socketId');

      final response = await _apiService.client.post<Map<String, dynamic>>(
        '${AppConfig.apiOrigin}/broadcasting/auth',
        data: {'socket_id': socketId, 'channel_name': channelName},
        options: Options(
          headers: authHeaders,
          contentType: Headers.formUrlEncodedContentType,
        ),
      );

      final data = response.data;
      if (data == null || data['auth'] == null) {
        throw Exception('Invalid auth response: $data');
      }

      debugPrint('WS Auth: Authorized $channelName');

      return PrivateChannelAuthorizationData(authKey: data['auth'] as String);
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
