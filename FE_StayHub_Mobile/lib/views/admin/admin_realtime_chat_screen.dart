import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../controllers/auth_controller.dart';
import '../../controllers/chat_controller.dart';
import '../../models/chat.dart';
import '../../services/websocket_service.dart';

class AdminChatScreen extends StatefulWidget {
  const AdminChatScreen({super.key});

  @override
  State<AdminChatScreen> createState() => _AdminChatScreenState();
}

class _AdminChatScreenState extends State<AdminChatScreen> {
  final TextEditingController _searchController = TextEditingController();
  final TextEditingController _messageController = TextEditingController();
  final ScrollController _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final chatController = context.read<ChatController>();
      await chatController.fetchAdminConversations();
      final adminId = context.read<AuthController>().currentAdmin?.id;
      if (adminId != null) {
        context.read<WebSocketService>().subscribeToAdminChat(
          adminId,
          onMessage: (payload) {
            if (!mounted) return;
            context.read<ChatController>().handleRealtimeMessage(payload);
          },
          onRead: (payload) {
            if (!mounted) return;
            context.read<ChatController>().handleRealtimeRead(payload);
          },
        );
      }
      final active = chatController.activeConversation;
      if (active != null) {
        await chatController.selectAdminConversation(active);
        _subscribeConversation(active.id);
      }
    });
  }

  void _subscribeConversation(int conversationId) {
    context.read<WebSocketService>().subscribeToChatConversation(
      conversationId,
      onMessage: (payload) {
        if (!mounted) return;
        context.read<ChatController>().handleRealtimeMessage(payload);
        _scrollToBottom();
      },
      onRead: (payload) {
        if (!mounted) return;
        context.read<ChatController>().handleRealtimeRead(payload);
      },
    );
  }

  @override
  void dispose() {
    _searchController.dispose();
    _messageController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scrollController.hasClients) return;
      _scrollController.animateTo(_scrollController.position.maxScrollExtent, duration: const Duration(milliseconds: 220), curve: Curves.easeOut);
    });
  }

  Future<void> _send() async {
    final conversation = context.read<ChatController>().activeConversation;
    final text = _messageController.text.trim();
    if (conversation == null || text.isEmpty) return;
    _messageController.clear();
    await context.read<ChatController>().sendAdminMessage(conversation.id, text);
    _scrollToBottom();
  }

  @override
  Widget build(BuildContext context) {
    final chatController = context.watch<ChatController>();
    final active = chatController.activeConversation;

    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        title: const Text('Đoạn chat', style: TextStyle(fontWeight: FontWeight.bold)),
        backgroundColor: const Color(0xFF1C1917),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(12),
            child: TextField(
              controller: _searchController,
              onSubmitted: (value) => context.read<ChatController>().fetchAdminConversations(keyword: value),
              decoration: InputDecoration(
                hintText: 'Tìm phòng hoặc tenant...',
                prefixIcon: const Icon(Icons.search),
                filled: true,
                fillColor: Colors.white,
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: BorderSide.none),
              ),
            ),
          ),
          SizedBox(
            height: 104,
            child: chatController.isLoading && chatController.conversations.isEmpty
                ? const Center(child: CircularProgressIndicator(color: Color(0xFF1C1917)))
                : ListView.builder(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(horizontal: 12),
                    itemCount: chatController.conversations.length,
                    itemBuilder: (context, index) {
                      final item = chatController.conversations[index];
                      final selected = active?.id == item.id;
                      return Padding(
                        padding: const EdgeInsets.only(right: 10),
                        child: InkWell(
                          onTap: () async {
                            await context.read<ChatController>().selectAdminConversation(item);
                            _subscribeConversation(item.id);
                            _scrollToBottom();
                          },
                          borderRadius: BorderRadius.circular(18),
                          child: Container(
                            width: 220,
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: selected ? const Color(0xFF1C1917) : Colors.white,
                              borderRadius: BorderRadius.circular(18),
                              border: Border.all(color: selected ? const Color(0xFFEAB308) : const Color(0xFFE4E2D7)),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    Expanded(child: Text('Phòng ${item.roomNumber ?? '—'}', style: TextStyle(color: selected ? Colors.white : const Color(0xFF1C1917), fontWeight: FontWeight.w900))),
                                    if (item.adminUnreadCount > 0)
                                      Badge(label: Text('${item.adminUnreadCount}'), backgroundColor: Colors.redAccent),
                                  ],
                                ),
                                const SizedBox(height: 4),
                                Text(item.tenantName ?? 'Khách thuê', maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: selected ? Colors.white70 : const Color(0xFF57534E), fontWeight: FontWeight.bold)),
                                const SizedBox(height: 4),
                                Text(item.lastMessage?.body ?? item.buildingName ?? 'Chưa có tin nhắn', maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: selected ? Colors.white60 : Colors.grey, fontSize: 11)),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
                  ),
          ),
          Expanded(
            child: active == null
                ? const Center(child: Text('Chọn một đoạn chat để bắt đầu.', style: TextStyle(fontWeight: FontWeight.bold)))
                : ListView.builder(
                    controller: _scrollController,
                    padding: const EdgeInsets.all(16),
                    itemCount: chatController.messages.length,
                    itemBuilder: (context, index) {
                      final message = chatController.messages[index];
                      return _AdminMessageBubble(message: message, isMine: message.senderRole == 2);
                    },
                  ),
          ),
          if (active != null)
            SafeArea(
              top: false,
              child: Container(
                padding: const EdgeInsets.fromLTRB(12, 10, 12, 12),
                color: Colors.white,
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Expanded(
                      child: TextField(
                        controller: _messageController,
                        minLines: 1,
                        maxLines: 4,
                        decoration: InputDecoration(
                          hintText: 'Nhập tin nhắn cho ${active.tenantName ?? 'tenant'}...',
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
                        style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF1C1917), foregroundColor: Colors.white, shape: const CircleBorder(), padding: EdgeInsets.zero),
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

class _AdminMessageBubble extends StatelessWidget {
  final ChatMessage message;
  final bool isMine;

  const _AdminMessageBubble({required this.message, required this.isMine});

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: isMine ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.78),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        decoration: BoxDecoration(
          color: isMine ? const Color(0xFF1C1917) : Colors.white,
          borderRadius: BorderRadius.circular(18),
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
