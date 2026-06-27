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
      backgroundColor: const Color(0xFFF7F6F0),
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
      body: Column(
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
              decoration: const BoxDecoration(
                color: Colors.white,
                border: Border(top: BorderSide(color: Color(0xFFE4E2D7))),
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
                        filled: true,
                        fillColor: const Color(0xFFF9F8F6),
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
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        decoration: BoxDecoration(
          color: isMine ? const Color(0xFF0F766E) : Colors.white,
          borderRadius: BorderRadius.circular(18).copyWith(
            bottomRight: isMine ? const Radius.circular(4) : const Radius.circular(18),
            bottomLeft: isMine ? const Radius.circular(18) : const Radius.circular(4),
          ),
          border: isMine ? null : Border.all(color: const Color(0xFFE4E2D7)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(message.body, style: TextStyle(color: isMine ? Colors.white : const Color(0xFF1C1917), fontWeight: FontWeight.w700, height: 1.35)),
            const SizedBox(height: 4),
            Text(message.optimistic ? 'Đang gửi...' : (message.createdAt ?? ''), style: TextStyle(fontSize: 10, color: isMine ? Colors.white70 : Colors.grey)),
          ],
        ),
      ),
    );
  }
}
