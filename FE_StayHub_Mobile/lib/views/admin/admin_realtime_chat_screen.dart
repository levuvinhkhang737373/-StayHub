import 'dart:io';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:image_picker/image_picker.dart';
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
    final ws = context.read<WebSocketService>();
    final active = context.read<ChatController>().activeConversation;
    if (active != null) {
      ws.unsubscribeFromChatConversation(active.id);
    }
    final adminId = context.read<AuthController>().currentAdmin?.id;
    if (adminId != null) {
      ws.unsubscribeFromAdminChat(adminId);
    }

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
    if (conversation == null) return;
    if (text.isEmpty && _selectedImages.isEmpty) return;
    _messageController.clear();
    final imagesToSend = List<File>.from(_selectedImages);
    setState(() {
      _selectedImages.clear();
    });
    await context.read<ChatController>().sendAdminMessage(conversation.id, text, images: imagesToSend);
    _scrollToBottom();
  }

  @override
  Widget build(BuildContext context) {
    final chatController = context.watch<ChatController>();
    final active = chatController.activeConversation;

    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        leading: const BackButton(),
        title: Builder(
          builder: (context) => GestureDetector(
            onTap: () => Scaffold.of(context).openDrawer(),
            behavior: HitTestBehavior.opaque,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Flexible(
                  child: Text(
                    active != null
                        ? (active.roomNumber != null ? 'Phòng ${active.roomNumber} - ${active.tenantName}' : active.tenantName ?? 'Đoạn chat')
                        : 'Đoạn chat',
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                  ),
                ),
                const SizedBox(width: 4),
                const Icon(Icons.arrow_drop_down, color: Colors.white, size: 20),
              ],
            ),
          ),
        ),
        backgroundColor: const Color(0xFF1C1917),
      ),
      drawer: Drawer(
        backgroundColor: const Color(0xFFF7F6F0),
        child: SafeArea(
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                child: Row(
                  children: [
                    const Icon(Icons.chat_bubble_outline, color: Color(0xFF1C1917)),
                    const SizedBox(width: 8),
                    const Text(
                      'Đoạn chat',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                        color: Color(0xFF1C1917),
                      ),
                    ),
                    const Spacer(),
                    IconButton(
                      icon: const Icon(Icons.close, color: Color(0xFF1C1917)),
                      onPressed: () => Navigator.pop(context),
                    ),
                  ],
                ),
              ),
              const Divider(height: 1, color: Color(0xFFE4E2D7)),
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
              Expanded(
                child: chatController.isLoading && chatController.conversations.isEmpty
                    ? const Center(child: CircularProgressIndicator(color: Color(0xFF1C1917)))
                    : ListView.builder(
                        padding: const EdgeInsets.symmetric(vertical: 8),
                        itemCount: chatController.conversations.length,
                        itemBuilder: (context, index) {
                          final item = chatController.conversations[index];
                          final selected = active?.id == item.id;
                          return Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                            child: InkWell(
                              onTap: () async {
                                Navigator.pop(context);
                                final oldActive = context.read<ChatController>().activeConversation;
                                if (oldActive != null) {
                                  context.read<WebSocketService>().unsubscribeFromChatConversation(oldActive.id);
                                }
                                await context.read<ChatController>().selectAdminConversation(item);
                                _subscribeConversation(item.id);
                                _scrollToBottom();
                              },
                              borderRadius: BorderRadius.circular(16),
                              child: Container(
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: selected ? const Color(0xFF1C1917) : Colors.white,
                                  borderRadius: BorderRadius.circular(16),
                                  border: Border.all(color: selected ? const Color(0xFFEAB308) : const Color(0xFFE4E2D7)),
                                ),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Row(
                                      children: [
                                        Expanded(
                                          child: Text(
                                            item.roomNumber != null ? 'Phòng ${item.roomNumber}' : 'Chưa có phòng',
                                            style: TextStyle(
                                              color: selected ? Colors.white : const Color(0xFF1C1917),
                                              fontWeight: FontWeight.w900,
                                            ),
                                          ),
                                        ),
                                        if (item.adminUnreadCount > 0)
                                          Badge(label: Text('${item.adminUnreadCount}'), backgroundColor: Colors.redAccent),
                                      ],
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      item.tenantName ?? 'Khách thuê',
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: TextStyle(
                                        color: selected ? Colors.white70 : const Color(0xFF57534E),
                                        fontWeight: FontWeight.bold,
                                      ),
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      item.lastMessage?.body ?? item.buildingName ?? 'Chưa có tin nhắn',
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: TextStyle(
                                        color: selected ? Colors.white60 : Colors.grey,
                                        fontSize: 11,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          );
                        },
                      ),
              ),
            ],
          ),
        ),
      ),
      body: Column(
        children: [
          Expanded(
            child: active == null
                ? const Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.chat_bubble_outline, size: 64, color: Colors.grey),
                        SizedBox(height: 16),
                        Text(
                          'Chọn một đoạn chat để bắt đầu.',
                          style: TextStyle(fontWeight: FontWeight.bold, color: Colors.grey),
                        ),
                      ],
                    ),
                  )
                : ListView.builder(
                    controller: _scrollController,
                    padding: const EdgeInsets.all(16),
                    itemCount: chatController.messages.length,
                    itemBuilder: (context, index) {
                      final message = chatController.messages[index];
                      final isMine = message.senderRole == 2;

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
                                  Expanded(child: Divider(color: const Color(0xFF3D2A18).withOpacity(0.15), thickness: 1)),
                                  Padding(
                                    padding: const EdgeInsets.symmetric(horizontal: 12),
                                    child: Text(
                                      currentDividerLabel,
                                      style: TextStyle(
                                        fontSize: 10,
                                        fontWeight: FontWeight.w900,
                                        letterSpacing: 1.5,
                                        color: const Color(0xFF8B5E34).withOpacity(0.7),
                                      ),
                                    ),
                                  ),
                                  Expanded(child: Divider(color: const Color(0xFF3D2A18).withOpacity(0.15), thickness: 1)),
                                ],
                              ),
                            ),
                          _AdminMessageBubble(message: message, isMine: isMine),
                        ],
                      );
                    },
                  ),
          ),
          if (active != null)
            SafeArea(
              top: false,
              child: Container(
                padding: const EdgeInsets.fromLTRB(12, 10, 12, 12),
                color: Colors.white,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (_selectedImages.isNotEmpty)
                      Container(
                        margin: const EdgeInsets.only(bottom: 8),
                        height: 76,
                        decoration: BoxDecoration(
                          color: const Color(0xFFFDFBF7),
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: const Color(0xFF3D2A18).withOpacity(0.15)),
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
                            icon: const Icon(Icons.image_outlined, color: Color(0xFF8B5E34), size: 22),
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
                              decoration: InputDecoration(
                                hintText: 'Nhập tin nhắn cho ${active.tenantName ?? 'tenant'}...',
                                hintStyle: const TextStyle(color: Colors.grey, fontSize: 14, fontWeight: FontWeight.normal),
                                border: InputBorder.none,
                                contentPadding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
                              ),
                              style: const TextStyle(fontSize: 15, color: Color(0xFF24170D)),
                            ),
                          ),
                          IconButton(
                            icon: const Icon(Icons.send_rounded, color: Color(0xFF8B5E34), size: 22),
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
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            if (message.body.isNotEmpty)
              Text(
                message.body,
                style: TextStyle(
                  color: isMine ? Colors.white : const Color(0xFF1C1917),
                  fontWeight: FontWeight.w700,
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
                                    child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFF8B5E34)),
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
                fontSize: 10,
                color: isMine ? Colors.white70 : Colors.grey,
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
