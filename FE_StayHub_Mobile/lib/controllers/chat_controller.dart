import 'dart:io';
import 'package:flutter/material.dart';
import 'package:dio/dio.dart' as dio;
import '../models/chat.dart';
import '../services/api_service.dart';

class ChatController extends ChangeNotifier {
  final ApiService _apiService = ApiService();

  List<ChatConversation> _conversations = [];
  List<ChatConversation> _tenantConversations = [];
  List<ChatConversation> _directConversations = [];
  ChatConversation? _activeConversation;
  List<ChatMessage> _messages = [];
  bool _isLoading = false;
  bool _isSending = false;
  String? _errorMessage;
  int _lastFetchCount = 0;

  List<ChatConversation> get conversations => _conversations;
  List<ChatConversation> get tenantConversations => _tenantConversations;
  List<ChatConversation> get directConversations => _directConversations;
  ChatConversation? get activeConversation => _activeConversation;
  List<ChatMessage> get messages => _messages;
  bool get isLoading => _isLoading;
  bool get isSending => _isSending;
  String? get errorMessage => _errorMessage;
  int get lastFetchCount => _lastFetchCount;

  Future<void> fetchAdminConversations({String? keyword}) async {
    await fetchAdminTenantConversations(keyword: keyword);
  }

  Future<void> fetchAdminTenantConversations({String? keyword}) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _apiService.get<Map<String, dynamic>>(
        '/admin/chat/conversations',
        queryParameters: {
          if (keyword != null && keyword.trim().isNotEmpty)
            'keyword': keyword.trim(),
          'per_page': 50,
        },
        fromJsonT: (json) => Map<String, dynamic>.from(json as Map),
      );
      final data = response.result?['data'] as List<dynamic>? ?? [];
      _tenantConversations = data
          .map(
            (item) => ChatConversation.fromJson(
              Map<String, dynamic>.from(item as Map),
            ),
          )
          .toList();
      _conversations = _tenantConversations;
      _activeConversation = _activeConversation?.isDirect == false
          ? (_tenantConversations.any(
                  (item) => item.id == _activeConversation!.id,
                )
                ? _activeConversation
                : null)
          : null;
      _activeConversation ??= _tenantConversations.isNotEmpty
          ? _tenantConversations.first
          : null;
    } catch (e) {
      _errorMessage = 'Không thể tải danh sách chat: $e';
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<void> fetchAdminDirectConversations({String? keyword}) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _apiService.get<Map<String, dynamic>>(
        '/admin/chat/direct-conversations',
        queryParameters: {
          if (keyword != null && keyword.trim().isNotEmpty)
            'keyword': keyword.trim(),
          'per_page': 100,
        },
        fromJsonT: (json) => Map<String, dynamic>.from(json as Map),
      );
      final data = response.result?['data'] as List<dynamic>? ?? [];
      _directConversations = data
          .map(
            (item) => ChatConversation.fromJson(
              Map<String, dynamic>.from(item as Map),
            ),
          )
          .toList();
      _conversations = _directConversations;
      _activeConversation = _activeConversation?.isDirect == true
          ? (_directConversations.any(
                  (item) => item.id == _activeConversation!.id,
                )
                ? _activeConversation
                : null)
          : null;
      _activeConversation ??= _directConversations.isNotEmpty
          ? _directConversations.first
          : null;
    } catch (e) {
      _errorMessage = 'Không thể tải danh sách superadmin: $e';
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<void> selectAdminConversation(
    ChatConversation conversation, {
    int? currentAdminId,
  }) async {
    _activeConversation = conversation;
    notifyListeners();
    if (conversation.isDirect) {
      await fetchAdminDirectMessages(conversation.id);
    } else {
      await fetchAdminMessages(conversation.id);
    }
    if (conversation.unreadCountForAdmin(currentAdminId) > 0) {
      if (conversation.isDirect) {
        await markAdminDirectRead(conversation.id);
      } else {
        await markAdminRead(conversation.id);
      }
    }
  }

  Future<void> fetchAdminMessages(int conversationId) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _apiService.get<Map<String, dynamic>>(
        '/admin/chat/conversations/$conversationId/messages',
        queryParameters: {'per_page': 50},
        fromJsonT: (json) => Map<String, dynamic>.from(json as Map),
      );
      final data = response.result?['data'] as List<dynamic>? ?? [];
      _messages = data
          .map(
            (item) =>
                ChatMessage.fromJson(Map<String, dynamic>.from(item as Map)),
          )
          .toList();
      _lastFetchCount = data.length;
    } catch (e) {
      _errorMessage = 'Không thể tải tin nhắn: $e';
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<void> fetchMoreAdminMessages(
    int conversationId, {
    required int beforeId,
    int perPage = 30,
  }) async {
    await _fetchMoreAdminMessages(
      '/admin/chat/conversations/$conversationId/messages',
      beforeId: beforeId,
      perPage: perPage,
    );
  }

  Future<void> fetchAdminDirectMessages(int conversationId) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _apiService.get<Map<String, dynamic>>(
        '/admin/chat/direct-conversations/$conversationId/messages',
        queryParameters: {'per_page': 50},
        fromJsonT: (json) => Map<String, dynamic>.from(json as Map),
      );
      final data = response.result?['data'] as List<dynamic>? ?? [];
      _messages = data
          .map(
            (item) =>
                ChatMessage.fromJson(Map<String, dynamic>.from(item as Map)),
          )
          .toList();
      _lastFetchCount = data.length;
    } catch (e) {
      _errorMessage = 'Không thể tải tin nhắn superadmin: $e';
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<void> fetchMoreAdminDirectMessages(
    int conversationId, {
    required int beforeId,
    int perPage = 30,
  }) async {
    await _fetchMoreAdminMessages(
      '/admin/chat/direct-conversations/$conversationId/messages',
      beforeId: beforeId,
      perPage: perPage,
    );
  }

  Future<void> _fetchMoreAdminMessages(
    String path, {
    required int beforeId,
    int perPage = 30,
  }) async {
    try {
      final response = await _apiService.get<Map<String, dynamic>>(
        path,
        queryParameters: {'per_page': perPage, 'before_id': beforeId},
        fromJsonT: (json) => Map<String, dynamic>.from(json as Map),
      );
      final data = response.result?['data'] as List<dynamic>? ?? [];
      final olderMessages = data
          .map(
            (item) =>
                ChatMessage.fromJson(Map<String, dynamic>.from(item as Map)),
          )
          .toList();
      _lastFetchCount = olderMessages.length;
      if (olderMessages.isNotEmpty) {
        _messages = [...olderMessages, ..._messages];
        notifyListeners();
      }
    } catch (e) {
      debugPrint('Error fetching more messages: $e');
    }
  }

  Future<void> fetchTenantMessages() async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _apiService.get<Map<String, dynamic>>(
        '/tenant/chat/messages',
        queryParameters: {'per_page': 50},
        fromJsonT: (json) => Map<String, dynamic>.from(json as Map),
      );
      final conversationJson = response.result?['conversation'];
      if (conversationJson is Map) {
        _activeConversation = ChatConversation.fromJson(
          Map<String, dynamic>.from(conversationJson),
        );
      }
      final data = response.result?['data'] as List<dynamic>? ?? [];
      _messages = data
          .map(
            (item) =>
                ChatMessage.fromJson(Map<String, dynamic>.from(item as Map)),
          )
          .toList();
      if ((_activeConversation?.tenantUnreadCount ?? 0) > 0) {
        await markTenantRead();
      }
    } catch (e) {
      _errorMessage = 'Không thể tải đoạn chat: $e';
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<void> sendTenantMessage(String body, {List<File>? images}) async {
    await _sendMessage(
      '/tenant/chat/messages',
      body,
      isTenant: true,
      images: images,
    );
  }

  Future<void> sendAdminMessage(
    int conversationId,
    String body, {
    List<File>? images,
  }) async {
    final path = _activeConversation?.isDirect == true
        ? '/admin/chat/direct-conversations/$conversationId/messages'
        : '/admin/chat/conversations/$conversationId/messages';
    await _sendMessage(path, body, isTenant: false, images: images);
  }

  Future<void> _sendMessage(
    String path,
    String body, {
    required bool isTenant,
    List<File>? images,
  }) async {
    final trimmed = body.trim();
    if (trimmed.isEmpty && (images == null || images.isEmpty)) return;
    if (_isSending) return;

    final optimistic = ChatMessage(
      id: DateTime.now().microsecondsSinceEpoch * -1,
      conversationId: _activeConversation?.id ?? 0,
      senderType: isTenant ? 'tenant' : 'admin',
      senderId: isTenant
          ? (_activeConversation?.tenantId ?? 0)
          : (_activeConversation?.managerAdminId ?? 0),
      senderRole: isTenant ? 1 : 2,
      body: trimmed,
      createdAt: DateTime.now().toIso8601String(),
      optimistic: true,
      attachments: images != null
          ? images.map((img) => img.path).toList()
          : const [],
    );

    _messages = [..._messages, optimistic];
    _isSending = true;
    _errorMessage = null;
    notifyListeners();

    try {
      dynamic postData;
      if (images != null && images.isNotEmpty) {
        final formData = dio.FormData();
        if (trimmed.isNotEmpty) {
          formData.fields.add(MapEntry('body', trimmed));
        }
        for (final img in images) {
          formData.files.add(
            MapEntry(
              'images[]',
              await dio.MultipartFile.fromFile(
                img.path,
                filename: img.path.split('/').last,
              ),
            ),
          );
        }
        postData = formData;
      } else {
        postData = {'body': trimmed};
      }

      final response = await _apiService.post<Map<String, dynamic>>(
        path,
        data: postData,
        fromJsonT: (json) => Map<String, dynamic>.from(json as Map),
      );
      final result = response.result;
      if (result != null) {
        final message = ChatMessage.fromJson(
          Map<String, dynamic>.from(result['message'] as Map),
        );
        final conversation = ChatConversation.fromJson(
          Map<String, dynamic>.from(result['conversation'] as Map),
        );
        _messages = _messages.where((item) => item.id != optimistic.id).toList()
          ..add(message);
        upsertConversation(conversation);
        _activeConversation = conversation;
      }
    } catch (e) {
      _messages = _messages.where((item) => item.id != optimistic.id).toList();
      _errorMessage = 'Không thể gửi tin nhắn: $e';
    }

    _isSending = false;
    notifyListeners();
  }

  Future<void> markTenantRead() async {
    try {
      final response = await _apiService.patch<Map<String, dynamic>>(
        '/tenant/chat/read',
        fromJsonT: (json) => json == null
            ? <String, dynamic>{}
            : Map<String, dynamic>.from(json as Map),
      );
      if (response.result != null && response.result!.isNotEmpty) {
        _activeConversation = ChatConversation.fromJson(response.result!);
        upsertConversation(_activeConversation!);
        notifyListeners();
      }
    } catch (_) {}
  }

  Future<void> markAdminRead(int conversationId) async {
    try {
      final response = await _apiService.patch<Map<String, dynamic>>(
        '/admin/chat/conversations/$conversationId/read',
        fromJsonT: (json) => Map<String, dynamic>.from(json as Map),
      );
      if (response.result != null) {
        final conversation = ChatConversation.fromJson(response.result!);
        upsertConversation(conversation);
        _activeConversation = conversation;
        notifyListeners();
      }
    } catch (_) {}
  }

  Future<void> markAdminDirectRead(int conversationId) async {
    try {
      final response = await _apiService.patch<Map<String, dynamic>>(
        '/admin/chat/direct-conversations/$conversationId/read',
        fromJsonT: (json) => Map<String, dynamic>.from(json as Map),
      );
      if (response.result != null) {
        final conversation = ChatConversation.fromJson(response.result!);
        upsertConversation(conversation);
        _activeConversation = conversation;
        notifyListeners();
      }
    } catch (_) {}
  }

  void handleRealtimeMessage(Map<String, dynamic> payload) {
    final conversationJson = payload['conversation'];
    final messageJson = payload['message'];
    if (conversationJson is Map) {
      final conversation = ChatConversation.fromJson(
        Map<String, dynamic>.from(conversationJson),
      );
      upsertConversation(conversation);
      _activeConversation ??= conversation;
    }
    if (messageJson is Map) {
      final message = ChatMessage.fromJson(
        Map<String, dynamic>.from(messageJson),
      );
      if (!_messages.any((item) => item.id == message.id)) {
        _messages = _messages.where((item) => !item.optimistic).toList()
          ..add(message);
      }
    }
    notifyListeners();
  }

  void handleRealtimeRead(Map<String, dynamic> payload) {
    final conversationJson = payload['conversation'];
    if (conversationJson is Map) {
      final conversation = ChatConversation.fromJson(
        Map<String, dynamic>.from(conversationJson),
      );
      upsertConversation(conversation);
      if (_activeConversation?.id == conversation.id) {
        _activeConversation = conversation;
      }
      notifyListeners();
    }
  }

  void upsertConversation(ChatConversation conversation) {
    final target = conversation.isDirect
        ? _directConversations
        : _tenantConversations;
    final updated = [
      conversation,
      ...target.where((item) => item.id != conversation.id),
    ];
    if (conversation.isDirect) {
      _directConversations = updated;
    } else {
      _tenantConversations = updated;
    }
    if (_conversations.any((item) => item.isDirect == conversation.isDirect)) {
      _conversations = updated;
    }
  }
}
