import 'dart:async';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:image_picker/image_picker.dart';
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
  List<File> _selectedImages = [];
  final ImagePicker _picker = ImagePicker();

  Future<void> _pickImages() async {
    try {
      final List<XFile> pickedFiles = await _picker.pickMultiImage();
      if (pickedFiles.isNotEmpty) {
        setState(() {
          _selectedImages.addAll(pickedFiles.map((x) => File(x.path)));
          if (_selectedImages.length > 5) {
            _selectedImages = _selectedImages.sublist(0, 5);
          }
        });
      }
    } catch (e) {
      debugPrint('Error picking images: $e');
    }
  }

  

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
    if (text.isEmpty && _selectedImages.isEmpty) return;
    _messageController.clear();
    final imagesToSend = List<File>.from(_selectedImages);
    setState(() {
      _selectedImages.clear();
    });
    await context.read<ChatController>().sendTenantMessage(text, images: imagesToSend);
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
                          itemBuilder: (context, index) {
                            final message = chatController.messages[index];
                            final isMine = message.senderRole == 1;

                            // Date separator logic
                            final prevMessage = index > 0 ? chatController.messages[index - 1] : null;
                            final currentDividerLabel = _getChatDividerLabel(message.createdAt);
                            final prevDividerLabel = prevMessage != null ? _getChatDividerLabel(prevMessage.createdAt) : null;
                            final showDateDivider = prevMessage == null || currentDividerLabel != prevDividerLabel;

                            return Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                if (showDateDivider)
                                  Padding(
                                    padding: const EdgeInsets.symmetric(vertical: 16),
                                    child: Row(
                                      children: [
                                        Expanded(child: Divider(color: const Color(0xFF0F766E).withOpacity(0.15), thickness: 1)),
                                        Padding(
                                          padding: const EdgeInsets.symmetric(horizontal: 12),
                                          child: Text(
                                            currentDividerLabel,
                                            style: TextStyle(
                                              fontSize: 10,
                                              fontWeight: FontWeight.w900,
                                              letterSpacing: 1.5,
                                              color: const Color(0xFF0F766E).withOpacity(0.7),
                                            ),
                                          ),
                                        ),
                                        Expanded(child: Divider(color: const Color(0xFF0F766E).withOpacity(0.15), thickness: 1)),
                                      ],
                                    ),
                                  ),
                                _MessageBubble(
                                  message: message,
                                  isMine: isMine,
                                ),
                              ],
                            );
                          },
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
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (_selectedImages.isNotEmpty)
                      Container(
                        margin: const EdgeInsets.only(bottom: 8),
                        height: 76,
                        decoration: BoxDecoration(
                          color: const Color(0xFFF4FBF9),
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: const Color(0xFF0F766E).withOpacity(0.15)),
                        ),
                        padding: const EdgeInsets.all(8),
                        child: ListView.builder(
                          scrollDirection: Axis.horizontal,
                          itemCount: _selectedImages.length,
                          itemBuilder: (context, idx) {
                            final file = _selectedImages[idx];
                            return Padding(
                              padding: const EdgeInsets.only(right: 8),
                              child: Stack(
                                clipBehavior: Clip.none,
                                children: [
                                  ClipRRect(
                                    borderRadius: BorderRadius.circular(12),
                                    child: Image.file(
                                      file,
                                      width: 60,
                                      height: 60,
                                      fit: BoxFit.cover,
                                    ),
                                  ),
                                  Positioned(
                                    top: -6,
                                    right: -6,
                                    child: GestureDetector(
                                      onTap: () {
                                        setState(() {
                                          _selectedImages.removeAt(idx);
                                        });
                                      },
                                      child: Container(
                                        padding: const EdgeInsets.all(2),
                                        decoration: const BoxDecoration(
                                          color: Color(0xFF24170D),
                                          shape: BoxShape.circle,
                                        ),
                                        child: const Icon(
                                          Icons.close,
                                          size: 12,
                                          color: Colors.white,
                                        ),
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            );
                          },
                        ),
                      ),
                    Container(
                      decoration: BoxDecoration(
                        color: const Color(0xFFF0F2F5),
                        borderRadius: BorderRadius.circular(22),
                      ),
                      padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
                      child: Row(
                        children: [
                          IconButton(
                            icon: const Icon(Icons.image_outlined, color: Color(0xFF0F766E), size: 22),
                            onPressed: _pickImages,
                            visualDensity: VisualDensity.compact,
                          ),
                          Expanded(
                            child: TextField(
                              controller: _messageController,
                              minLines: 1,
                              maxLines: 4,
                              textInputAction: TextInputAction.newline,
                              onChanged: (val) => setState(() {}),
                              decoration: const InputDecoration(
                                hintText: 'Aa',
                                hintStyle: TextStyle(color: Colors.grey, fontSize: 15, fontWeight: FontWeight.normal),
                                border: InputBorder.none,
                                contentPadding: EdgeInsets.symmetric(horizontal: 8, vertical: 6),
                              ),
                              style: const TextStyle(fontSize: 15, color: Color(0xFF24170D)),
                            ),
                          ),
                          IconButton(
                            icon: const Icon(Icons.send_rounded, color: Color(0xFF0F766E), size: 22),
                            onPressed: (chatController.isSending || (_messageController.text.trim().isEmpty && _selectedImages.isEmpty))
                                ? null
                                : _send,
                            visualDensity: VisualDensity.compact,
                          ),
                        ],
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
            if (message.body.isNotEmpty)
              Text(
                message.body,
                style: TextStyle(
                  color: isMine ? Colors.white : const Color(0xFF24170D),
                  fontWeight: FontWeight.bold,
                  height: 1.35,
                ),
              ),
            if (message.attachments.isNotEmpty) ...[
              if (message.body.isNotEmpty) const SizedBox(height: 8),
              GridView.builder(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: message.attachments.length > 1 ? 2 : 1,
                  crossAxisSpacing: 4,
                  mainAxisSpacing: 4,
                  childAspectRatio: 1.3,
                ),
                itemCount: message.attachments.length,
                itemBuilder: (context, idx) {
                  final url = message.attachments[idx];
                  final isLocal = !url.startsWith('http');
                  return ClipRRect(
                    borderRadius: BorderRadius.circular(12),
                    child: isLocal
                        ? Image.file(
                            File(url),
                            fit: BoxFit.cover,
                          )
                        : Image.network(
                            url,
                            fit: BoxFit.cover,
                            loadingBuilder: (context, child, progress) {
                              if (progress == null) return child;
                              return Container(
                                color: Colors.black12,
                                child: const Center(
                                  child: SizedBox(
                                    width: 20,
                                    height: 20,
                                    child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFF0F766E)),
                                  ),
                                ),
                              );
                            },
                            errorBuilder: (context, error, stackTrace) {
                              return Container(
                                color: Colors.black12,
                                child: const Icon(Icons.broken_image, color: Colors.grey),
                              );
                            },
                          ),
                  );
                },
              ),
            ],
            const SizedBox(height: 4),
            Text(
              message.optimistic ? 'Đang gửi...' : _formatTimeOnly(message.createdAt),
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

String _getChatDividerLabel(String? createdAtStr) {
  if (createdAtStr == null || createdAtStr.isEmpty) return '';
  try {
    final date = DateTime.parse(createdAtStr).toLocal();
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final yesterday = today.subtract(const Duration(days: 1));
    final messageDate = DateTime(date.year, date.month, date.day);

    if (messageDate == today) {
      return 'HÔM NAY';
    } else if (messageDate == yesterday) {
      return 'HÔM QUA';
    } else {
      final day = date.day.toString().padLeft(2, '0');
      final month = date.month.toString().padLeft(2, '0');
      final year = date.year;
      return '$day/$month/$year';
    }
  } catch (_) {
    return createdAtStr;
  }
}

String _formatTimeOnly(String? createdAtStr) {
  if (createdAtStr == null || createdAtStr.isEmpty) return '';
  try {
    final date = DateTime.parse(createdAtStr).toLocal();
    final hour = date.hour.toString().padLeft(2, '0');
    final minute = date.minute.toString().padLeft(2, '0');
    return '$hour:$minute';
  } catch (_) {
    return createdAtStr;
  }
}
