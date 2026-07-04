import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../controllers/auth_controller.dart';
import '../../controllers/chat_controller.dart';
import '../../services/websocket_service.dart';
import '../../models/chat.dart';

class TenantChatScreen extends StatefulWidget {
  const TenantChatScreen({super.key});

  @override
  State<TenantChatScreen> createState() => _TenantChatScreenState();
}

class _TenantChatScreenState extends State<TenantChatScreen> {
  final TextEditingController _messageController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  StreamSubscription? _chatSubscription;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final chatController = context.read<ChatController>();
      await chatController.fetchTenantMessages();
      _subscribeRealtime();
      _scrollToBottom();
    });
  }

  void _subscribeRealtime() {
    final auth = context.read<AuthController>();
    final tenantId = auth.currentTenant?.id;
    final conversationId = context.read<ChatController>().activeConversation?.id;
    final ws = context.read<WebSocketService>();

    if (tenantId != null) {
      ws.subscribeToTenantChat(
        tenantId,
        onMessage: (payload) {
          if (!mounted) return;
          context.read<ChatController>().handleRealtimeMessage(payload);
          context.read<ChatController>().markTenantRead();
          _scrollToBottom();
        },
        onRead: (payload) {
          if (!mounted) return;
          context.read<ChatController>().handleRealtimeRead(payload);
        },
      );
    }

    if (conversationId != null) {
      ws.subscribeToChatConversation(
        conversationId,
        onMessage: (payload) {
          if (!mounted) return;
          context.read<ChatController>().handleRealtimeMessage(payload);
          context.read<ChatController>().markTenantRead();
          _scrollToBottom();
        },
        onRead: (payload) {
          if (!mounted) return;
          context.read<ChatController>().handleRealtimeRead(payload);
        },
      );
    }

    _chatSubscription ??= ws.notificationsStream.listen((event) {
      if (!mounted) return;
      if (event['type'] == 'chat_message_sent') {
        _scrollToBottom();
      }
    });
  }

  @override
  void dispose() {
    final ws = context.read<WebSocketService>();
    final auth = context.read<AuthController>();
    final tenantId = auth.currentTenant?.id;
    final conversationId = context.read<ChatController>().activeConversation?.id;

    if (tenantId != null) {
      ws.unsubscribeFromTenantChat(tenantId);
    }
    if (conversationId != null) {
      ws.unsubscribeFromChatConversation(conversationId);
    }

    _chatSubscription?.cancel();
    _messageController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scrollController.hasClients) return;
      _scrollController.animateTo(
        _scrollController.position.maxScrollExtent,
        duration: const Duration(milliseconds: 220),
        curve: Curves.easeOut,
      );
    });
  }

  Future<void> _send() async {
    final text = _messageController.text.trim();
    if (text.isEmpty) return;
    _messageController.clear();
    await context.read<ChatController>().sendTenantMessage(text);
    _subscribeRealtime();
    _scrollToBottom();
  }

  @override
  Widget build(BuildContext context) {
    final chatController = context.watch<ChatController>();
    final conversation = chatController.activeConversation;

    return Scaffold(
      appBar: AppBar(
        backgroundColor: const Color(0xFF0F766E),
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Chat với quản lý', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
            Text(
              conversation?.managerName ?? 'StayHub realtime',
              style: const TextStyle(fontSize: 11, color: Colors.white70, fontWeight: FontWeight.w600),
            ),
          ],
        ),
      ),
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              Color(0xFFFFFAF1),
              Color(0xFFF4EFE6),
            ],
          ),
        ),
        child: Column(
          children: [
            if (chatController.errorMessage != null)
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                color: Colors.red.shade50,
                child: Text(chatController.errorMessage!, style: const TextStyle(color: Colors.red, fontWeight: FontWeight.bold)),
              ),
            Expanded(
              child: chatController.isLoading
                  ? const Center(child: CircularProgressIndicator(color: Color(0xFF0F766E)))
                  : chatController.messages.isEmpty
                      ? const _EmptyChatState()
                      : ListView.builder(
                          controller: _scrollController,
                          padding: const EdgeInsets.all(16),
                          itemCount: chatController.messages.length,
                          itemBuilder: (context, index) => _MessageBubble(
                            message: chatController.messages[index],
                            isMine: chatController.messages[index].senderRole == 1,
                          ),
                        ),
            ),
            SafeArea(
              top: false,
              child: Container(
                padding: const EdgeInsets.fromLTRB(12, 10, 12, 12),
                decoration: BoxDecoration(
                  color: Colors.white,
                  border: Border(top: BorderSide(color: const Color(0xFF3D2A18).withOpacity(0.1))),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Expanded(
                      child: TextField(
                        controller: _messageController,
                        minLines: 1,
                        maxLines: 4,
                        textInputAction: TextInputAction.newline,
                        decoration: InputDecoration(
                          hintText: 'Nhập tin nhắn...',
                          hintStyle: TextStyle(color: const Color(0xFF8B5E34).withOpacity(0.5), fontWeight: FontWeight.bold),
                          filled: true,
                          fillColor: const Color(0xFFF9F5EC),
                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(22), borderSide: BorderSide.none),
                          contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    SizedBox(
                      width: 48,
                      height: 48,
                      child: ElevatedButton(
                        onPressed: chatController.isSending ? null : _send,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFF0F766E),
                          foregroundColor: Colors.white,
                          shape: const CircleBorder(),
                          padding: EdgeInsets.zero,
                        ),
                        child: const Icon(Icons.send_rounded),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _EmptyChatState extends StatelessWidget {
  const _EmptyChatState();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.all(18),
              decoration: const BoxDecoration(color: Color(0xFFE0F2F1), shape: BoxShape.circle),
              child: const Icon(Icons.chat_bubble_rounded, color: Color(0xFF0F766E), size: 36),
            ),
            const SizedBox(height: 16),
            const Text('Bạn cần hỗ trợ?', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w900, color: Color(0xFF1C1917))),
            const SizedBox(height: 8),
            const Text('Nhắn trực tiếp cho quản lý tòa nhà của bạn. Tin nhắn sẽ được gửi realtime qua Reverb.', textAlign: TextAlign.center, style: TextStyle(color: Color(0xFF57534E), height: 1.4)),
          ],
        ),
      ),
    );
  }
}

class _MessageBubble extends StatelessWidget {
  final ChatMessage message;
  final bool isMine;

  const _MessageBubble({required this.message, required this.isMine});

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: isMine ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.78),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        decoration: BoxDecoration(
          color: isMine ? const Color(0xFF0F766E) : Colors.white,
          borderRadius: BorderRadius.circular(20).copyWith(
            bottomRight: isMine ? const Radius.circular(4) : const Radius.circular(20),
            bottomLeft: isMine ? const Radius.circular(20) : const Radius.circular(4),
          ),
          border: isMine ? null : Border.all(color: const Color(0xFF3D2A18).withOpacity(0.1)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Align(
              alignment: Alignment.centerLeft,
              child: Text(
                message.body,
                style: TextStyle(
                  color: isMine ? Colors.white : const Color(0xFF24170D),
                  fontWeight: FontWeight.bold,
                  height: 1.35,
                ),
              ),
            ),
            const SizedBox(height: 4),
            Text(
              message.optimistic ? 'Đang gửi...' : _formatMessageTime(message.createdAt),
              style: TextStyle(
                fontSize: 9,
                color: isMine ? Colors.white60 : const Color(0xFF8B5E34).withOpacity(0.6),
                fontWeight: FontWeight.bold,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

String _formatMessageTime(String? dateTimeStr) {
  if (dateTimeStr == null || dateTimeStr.isEmpty) return '';
  try {
    DateTime dt;
    if (dateTimeStr.contains('T')) {
      dt = DateTime.parse(dateTimeStr).toLocal();
    } else {
      final parts = dateTimeStr.split(' ');
      if (parts.length == 2) {
        final dateParts = parts[0].split('-');
        final timeParts = parts[1].split(':');
        if (dateParts.length == 3 && timeParts.length >= 2) {
          dt = DateTime(
            int.parse(dateParts[0]),
            int.parse(dateParts[1]),
            int.parse(dateParts[2]),
            int.parse(timeParts[0]),
            int.parse(timeParts[1]),
            timeParts.length == 3 ? int.parse(timeParts[2].split('.')[0]) : 0,
          );
        } else {
          dt = DateTime.parse(dateTimeStr).toLocal();
        }
      } else {
        dt = DateTime.parse(dateTimeStr).toLocal();
      }
    }
    
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final yesterday = today.subtract(const Duration(days: 1));
    final messageDate = DateTime(dt.year, dt.month, dt.day);

    final hour = dt.hour.toString().padLeft(2, '0');
    final minute = dt.minute.toString().padLeft(2, '0');

    if (messageDate == today) {
      return '$hour:$minute';
    } else if (messageDate == yesterday) {
      return 'Hôm qua $hour:$minute';
    } else if (dt.year == now.year) {
      final day = dt.day.toString().padLeft(2, '0');
      final month = dt.month.toString().padLeft(2, '0');
      return '$day/$month $hour:$minute';
    } else {
      final day = dt.day.toString().padLeft(2, '0');
      final month = dt.month.toString().padLeft(2, '0');
      final year = dt.year;
      return '$day/$month/$year $hour:$minute';
    }
  } catch (e) {
    return dateTimeStr;
  }
}
