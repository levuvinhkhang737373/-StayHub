import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:dart_pusher_channels/dart_pusher_channels.dart';
import 'package:dio/dio.dart';
import '../config/app_config.dart';
import 'api_service.dart';

class WebSocketService extends ChangeNotifier with WidgetsBindingObserver {
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
  Function(Map<String, dynamic>)? _onChatMessageCallback;
  Function(Map<String, dynamic>)? _onChatReadCallback;
  int? _tenantId;
  int? _adminChatId;
  int? _tenantChatId;
  int? _conversationId;

  WebSocketService() {
    WidgetsBinding.instance.addObserver(this);
  }

  // Stream for broadcasting notification events to the application
  final StreamController<Map<String, dynamic>> _notificationStreamController = StreamController<Map<String, dynamic>>.broadcast();
  Stream<Map<String, dynamic>> get notificationsStream => _notificationStreamController.stream;

  // Stream for debugging logs to the UI
  final StreamController<String> _debugStreamController = StreamController<String>.broadcast();
  Stream<String> get debugStream => _debugStreamController.stream;

  /// Connect to the Laravel Reverb WebSocket server
  Future<void> connect() async {
    if (_client != null && _isConnected) {
      debugPrint('WS: Already connected.');
      return;
    }

    if (_client != null) {
      await _closeConnectionOnly();
    }

    final scheme = AppConfig.reverbPort == 443 ? 'wss' : 'ws';
    final wsUrl = '$scheme://${AppConfig.reverbHost}:${AppConfig.reverbPort}';
    debugPrint('WS: Connecting to Laravel Reverb at $wsUrl...');
    _debugStreamController.add('Đang kết nối WebSocket tới $wsUrl...');

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
        _debugStreamController.add('Lỗi kết nối WebSocket: $exception');
        _isConnected = false;
        notifyListeners();
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

      // Bind to ContractDepositPaid event on admin channel
      final depositSubscription = channel.bind('ContractDepositPaid').listen((event) {
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
        _notificationStreamController.add({
          'type': 'admin_contract_deposit_paid',
          'data': parsedData ?? event.data,
        });
      });
      subscriptions.add(depositSubscription);

      // Bind to NotificationSent event on admin channel
      final adminNotificationSubscription = channel.bind('NotificationSent').listen((event) {
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
        _notificationStreamController.add({
          'type': 'admin_notification_sent',
          'data': parsedData ?? event.data,
        });
      });
      subscriptions.add(adminNotificationSubscription);

      // Bind to InvoicePaid event on admin channel
      final adminInvoicePaidSubscription = channel.bind('InvoicePaid').listen((event) {
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
            _notificationStreamController.add({
              'type': 'admin_invoice_paid',
              'data': decoded['invoice'] ?? decoded,
            });
          }
        } catch (e) {
          debugPrint('WS Error handling InvoicePaid (Admin): $e');
        }
      });
      subscriptions.add(adminInvoicePaidSubscription);

      final adminInvoiceReissuedSubscription = channel.bind('InvoiceReissued').listen((event) {
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
            _notificationStreamController.add({
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

      final List<StreamSubscription> subscriptions = [];

      // Bind to 'NotificationSent' event broadcast by backend
      final notificationSub = channel.bind('NotificationSent').listen((event) {
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
              final notificationData = decoded['notification'] as Map<String, dynamic>;
              _onTenantNotificationCallback!(notificationData);
              
              // Broadcast event locally to other screens
              _notificationStreamController.add({
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
            _notificationStreamController.add({
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
            _notificationStreamController.add({
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
            _notificationStreamController.add({
              'type': 'invoice_issued',
              'data': decoded['invoice'] ?? decoded,
            });
          }
        } catch (e) {
          debugPrint('WS Error handling InvoiceIssued: $e');
        }
      });
      subscriptions.add(invoiceIssuedSub);

      final invoiceReissuedSub = channel.bind('InvoiceReissued').listen((event) {
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
            _notificationStreamController.add({
              'type': 'invoice_reissued',
              'data': decoded['invoice'] ?? decoded,
            });
          }
        } catch (e) {
          debugPrint('WS Error handling InvoiceReissued: $e');
        }
      });
      subscriptions.add(invoiceReissuedSub);

      _eventSubscriptions[channelName] = subscriptions;
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
    if (_adminChatId != null && _onChatMessageCallback != null) {
      _subscribeToChatInboxChannel('private-chat.admin.$_adminChatId');
    }
    if (_tenantChatId != null && _onChatMessageCallback != null) {
      _subscribeToChatInboxChannel('private-chat.tenant.$_tenantChatId');
    }
    if (_conversationId != null && _onChatMessageCallback != null) {
      _subscribeToChatConversationChannel(_conversationId!);
    }
  }

  void subscribeToAdminChat(int adminId, {
    required Function(Map<String, dynamic>) onMessage,
    Function(Map<String, dynamic>)? onRead,
  }) {
    _adminChatId = adminId;
    _onChatMessageCallback = onMessage;
    _onChatReadCallback = onRead;
    if (_isConnected) {
      _subscribeToChatInboxChannel('private-chat.admin.$adminId');
    }
  }

  void subscribeToTenantChat(int tenantId, {
    required Function(Map<String, dynamic>) onMessage,
    Function(Map<String, dynamic>)? onRead,
  }) {
    _tenantChatId = tenantId;
    _onChatMessageCallback = onMessage;
    _onChatReadCallback = onRead;
    if (_isConnected) {
      _subscribeToChatInboxChannel('private-chat.tenant.$tenantId');
    }
  }

  void subscribeToChatConversation(int conversationId, {
    required Function(Map<String, dynamic>) onMessage,
    Function(Map<String, dynamic>)? onRead,
  }) {
    _conversationId = conversationId;
    _onChatMessageCallback = onMessage;
    _onChatReadCallback = onRead;
    if (_isConnected) {
      _subscribeToChatConversationChannel(conversationId);
    }
  }

  Future<void> _subscribeToChatConversationChannel(int conversationId) async {
    await _subscribeToChatChannel('private-chat.conversation.$conversationId');
  }

  Future<void> _subscribeToChatInboxChannel(String channelName) async {
    await _subscribeToChatChannel(channelName);
  }

  Future<void> _subscribeToChatChannel(String channelName) async {
    if (_client == null || !_isConnected) return;
    if (_activeChannels.containsKey(channelName)) return;

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

      final List<StreamSubscription> subscriptions = [];
      subscriptions.add(channel.bind('ChatMessageSent').listen((event) {
        final decoded = _decodeEventData(event.data);
        if (decoded != null) {
          _onChatMessageCallback?.call(decoded);
          _notificationStreamController.add({'type': 'chat_message_sent', 'data': decoded});
        }
      }));
      subscriptions.add(channel.bind('ChatConversationRead').listen((event) {
        final decoded = _decodeEventData(event.data);
        if (decoded != null) {
          _onChatReadCallback?.call(decoded);
          _notificationStreamController.add({'type': 'chat_conversation_read', 'data': decoded});
        }
      }));

      _eventSubscriptions[channelName] = subscriptions;
      channel.subscribe();
    } catch (e) {
      debugPrint('WS Chat subscription error ($channelName): $e');
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
    _onChatMessageCallback = null;
    _onChatReadCallback = null;
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
    notifyListeners();
  }
  
  /// Closes the current connection and cleans up active subscriptions/channels, but preserves callbacks/configs.
  Future<void> _closeConnectionOnly() async {
    debugPrint('WS: Closing connection only, keeping callbacks...');
    
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
  void didChangeAppLifecycleState(AppLifecycleState state) {
    debugPrint('WS AppLifecycleState changed to: $state');
    if (state == AppLifecycleState.paused || state == AppLifecycleState.inactive) {
      debugPrint('WS: App backgrounded, closing connection to save battery...');
      _closeConnectionOnly();
    } else if (state == AppLifecycleState.resumed) {
      debugPrint('WS: App resumed, checking connection status...');
      
      final bool hasActiveSubscriptions = _onAdminMaintenanceCallback != null ||
          _tenantId != null ||
          _adminChatId != null ||
          _tenantChatId != null ||
          _conversationId != null;

      if (hasActiveSubscriptions) {
        debugPrint('WS: App resumed with active subscriptions. Reconnecting...');
        _debugStreamController.add('Ứng dụng hoạt động trở lại, đang kết nối lại WebSocket...');
        connect();
      }
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
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
      debugPrint('WS Auth: Authorizing $channelName with socket $socketId');

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

      debugPrint('WS Auth: Authorized $channelName');

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
