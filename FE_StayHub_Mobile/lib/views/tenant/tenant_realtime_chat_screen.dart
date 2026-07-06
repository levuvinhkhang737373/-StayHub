import 'dart:async';
import 'dart:io';
import 'package:flutter/foundation.dart';
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
      final List<XFile> pickedFiles = await _picker.pickMultiImage(
        imageQuality: 70,
        maxWidth: 1024,
        maxHeight: 1024,
      );
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
    final conversationId = context
        .read<ChatController>()
        .activeConversation
        ?.id;
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
        isTenantSession: true,
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
    final conversationId = context
        .read<ChatController>()
        .activeConversation
        ?.id;

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
    await context.read<ChatController>().sendTenantMessage(
      text,
      images: imagesToSend,
    );
    _subscribeRealtime();
    _scrollToBottom();
  }

  @override
  Widget build(BuildContext context) {
    final chatController = context.watch<ChatController>();
    final conversation = chatController.activeConversation;

    return Scaffold(
      appBar: AppBar(
        backgroundColor: const Color(0xFF24170D),
        title: Row(
          children: [
            Container(
              width: 36,
              height: 36,
              decoration: const BoxDecoration(
                color: Color(0xFFF3C56B),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.chat_bubble_rounded,
                color: Color(0xFF24170D),
                size: 18,
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Chat với quản lý',
                    style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16),
                  ),
                  Text(
                    conversation?.managerName ?? 'StayHub',
                    style: TextStyle(
                      fontSize: 11,
                      color: Colors.white.withOpacity(0.7),
                      fontWeight: FontWeight.w700,
                    ),
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [Color(0xFFFFFAF1), Color(0xFFF4EFE6)],
          ),
        ),
        child: Column(
          children: [
            if (chatController.errorMessage != null)
              Container(
                width: double.infinity,
                margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 12,
                ),
                decoration: BoxDecoration(
                  color: const Color(0xFFFFF1F2),
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(
                    color: const Color(0xFF9F1239).withOpacity(0.1),
                  ),
                ),
                child: Text(
                  chatController.errorMessage!,
                  style: const TextStyle(
                    color: Color(0xFFBE123C),
                    fontWeight: FontWeight.bold,
                    fontSize: 13,
                  ),
                ),
              ),
            Expanded(
              child: chatController.isLoading
                  ? const Center(
                      child: CircularProgressIndicator(
                        color: Color(0xFF8B5E34),
                      ),
                    )
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
                        final prevMessage = index > 0
                            ? chatController.messages[index - 1]
                            : null;
                        final currentDividerLabel = _getChatDividerLabel(
                          message.createdAt,
                        );
                        final prevDividerLabel = prevMessage != null
                            ? _getChatDividerLabel(prevMessage.createdAt)
                            : null;
                        final showDateDivider =
                            prevMessage == null ||
                            currentDividerLabel != prevDividerLabel;

                        return Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            if (showDateDivider)
                              Padding(
                                padding: const EdgeInsets.symmetric(
                                  vertical: 16,
                                ),
                                child: Row(
                                  children: [
                                    Expanded(
                                      child: Divider(
                                        color: const Color(
                                          0xFF3D2A18,
                                        ).withOpacity(0.15),
                                        thickness: 1,
                                      ),
                                    ),
                                    Padding(
                                      padding: const EdgeInsets.symmetric(
                                        horizontal: 12,
                                      ),
                                      child: Text(
                                        currentDividerLabel,
                                        style: TextStyle(
                                          fontSize: 10,
                                          fontWeight: FontWeight.w900,
                                          letterSpacing: 1.5,
                                          color: const Color(
                                            0xFF8B5E34,
                                          ).withOpacity(0.7),
                                        ),
                                      ),
                                    ),
                                    Expanded(
                                      child: Divider(
                                        color: const Color(
                                          0xFF3D2A18,
                                        ).withOpacity(0.15),
                                        thickness: 1,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            _MessageBubble(
                              message: message,
                              isMine: isMine,
                              allMessages: chatController.messages,
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
                  border: Border(
                    top: BorderSide(
                      color: const Color(0xFF3D2A18).withOpacity(0.1),
                    ),
                  ),
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
                          border: Border.all(
                            color: const Color(0xFF8B5E34).withOpacity(0.15),
                          ),
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
                      padding: const EdgeInsets.symmetric(
                        horizontal: 4,
                        vertical: 2,
                      ),
                      child: Row(
                        children: [
                          IconButton(
                            icon: const Icon(
                              Icons.image_outlined,
                              color: Color(0xFF8B5E34),
                              size: 22,
                            ),
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
                                hintStyle: TextStyle(
                                  color: Colors.grey,
                                  fontSize: 15,
                                  fontWeight: FontWeight.normal,
                                ),
                                border: InputBorder.none,
                                contentPadding: EdgeInsets.symmetric(
                                  horizontal: 8,
                                  vertical: 6,
                                ),
                              ),
                              style: const TextStyle(
                                fontSize: 15,
                                color: Color(0xFF24170D),
                              ),
                            ),
                          ),
                          IconButton(
                            icon: const Icon(
                              Icons.send_rounded,
                              color: Color(0xFF8B5E34),
                              size: 22,
                            ),
                            onPressed:
                                (chatController.isSending ||
                                    (_messageController.text.trim().isEmpty &&
                                        _selectedImages.isEmpty))
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
              decoration: const BoxDecoration(
                color: Color(0xFFE0F2F1),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.chat_bubble_rounded,
                color: Color(0xFF8B5E34),
                size: 36,
              ),
            ),
            const SizedBox(height: 16),
            const Text(
              'Bạn cần hỗ trợ?',
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.w900,
                color: Color(0xFF1C1917),
              ),
            ),
            const SizedBox(height: 8),
            const Text(
              'Nhắn trực tiếp cho quản lý tòa nhà của bạn.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Color(0xFF57534E), height: 1.4),
            ),
          ],
        ),
      ),
    );
  }
}

class _MessageBubble extends StatelessWidget {
  final ChatMessage message;
  final bool isMine;
  final List<ChatMessage> allMessages;

  const _MessageBubble({
    required this.message,
    required this.isMine,
    required this.allMessages,
  });

  @override
  Widget build(BuildContext context) {
    final hasBody = message.body.isNotEmpty;
    return Align(
      alignment: isMine ? Alignment.centerRight : Alignment.centerLeft,
      child: Column(
        crossAxisAlignment: isMine
            ? CrossAxisAlignment.end
            : CrossAxisAlignment.start,
        children: [
          Container(
            constraints: BoxConstraints(
              maxWidth: MediaQuery.of(context).size.width * 0.78,
            ),
            padding: hasBody
                ? const EdgeInsets.symmetric(horizontal: 16, vertical: 12)
                : EdgeInsets.zero,
            decoration: BoxDecoration(
              color: hasBody
                  ? (isMine ? const Color(0xFF24170D) : Colors.white)
                  : Colors.transparent,
              borderRadius: BorderRadius.circular(23),
              border: (hasBody && !isMine)
                  ? Border.all(color: const Color(0xFF3D2A18).withOpacity(0.1))
                  : null,
            ),
            child: Column(
              crossAxisAlignment: isMine
                  ? CrossAxisAlignment.end
                  : CrossAxisAlignment.start,
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
                  if (message.attachments.length == 1)
                    Align(
                      alignment: isMine
                          ? Alignment.centerRight
                          : Alignment.centerLeft,
                      child: ConstrainedBox(
                        constraints: BoxConstraints(
                          maxWidth: MediaQuery.of(context).size.width * 0.76,
                          maxHeight: 400,
                        ),
                        child: Container(
                          decoration: BoxDecoration(
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(
                              color: const Color(0xFF3D2A18).withOpacity(0.1),
                            ),
                          ),
                          child: ClipRRect(
                            borderRadius: BorderRadius.circular(12),
                            child: GestureDetector(
                              onTap: () => _openImageGallery(
                                context,
                                message.attachments,
                                0,
                              ),
                              child: _buildImage(message.attachments[0]),
                            ),
                          ),
                        ),
                      ),
                    )
                  else
                    GridView.builder(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      gridDelegate:
                          const SliverGridDelegateWithFixedCrossAxisCount(
                            crossAxisCount: 2,
                            crossAxisSpacing: 4,
                            mainAxisSpacing: 4,
                            childAspectRatio: 1.3,
                          ),
                      itemCount: message.attachments.length,
                      itemBuilder: (context, idx) {
                        final url = message.attachments[idx];
                        return ClipRRect(
                          borderRadius: BorderRadius.circular(12),
                          child: GestureDetector(
                            onTap: () => _openImageGallery(
                              context,
                              message.attachments,
                              idx,
                            ),
                            child: _buildImage(url, fit: BoxFit.cover),
                          ),
                        );
                      },
                    ),
                ],
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.only(left: 4, right: 4, bottom: 8),
            child: Text(
              message.optimistic
                  ? 'Đang gửi...'
                  : _formatTimeOnly(message.createdAt),
              style: TextStyle(
                fontSize: 9,
                color: const Color(0xFF8B5E34).withOpacity(0.6),
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildImage(String url, {BoxFit? fit}) {
    final isLocal = !url.startsWith('http');
    if (kIsWeb || !isLocal) {
      return Image.network(
        url,
        fit: fit,
        loadingBuilder: (context, child, progress) {
          if (progress == null) return child;
          return Container(
            color: Colors.black12,
            width: 150,
            height: 150,
            child: const Center(
              child: SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  color: Color(0xFF8B5E34),
                ),
              ),
            ),
          );
        },
        errorBuilder: (context, error, stackTrace) {
          return Container(
            color: Colors.black12,
            width: 150,
            height: 150,
            child: const Icon(Icons.broken_image, color: Colors.grey),
          );
        },
      );
    }
    return Image.file(File(url), fit: fit);
  }

  void _openImageGallery(
    BuildContext context,
    List<String> urls,
    int initialIndex,
  ) {
    showDialog(
      context: context,
      useSafeArea: false,
      barrierColor: Colors.black.withOpacity(0.95),
      builder: (context) =>
          _ImageGalleryOverlay(urls: urls, initialIndex: initialIndex),
    );
  }
}

class _ImageGalleryOverlay extends StatefulWidget {
  final List<String> urls;
  final int initialIndex;

  const _ImageGalleryOverlay({required this.urls, required this.initialIndex});

  @override
  State<_ImageGalleryOverlay> createState() => _ImageGalleryOverlayState();
}

class _ImageGalleryOverlayState extends State<_ImageGalleryOverlay> {
  late int _currentIndex;
  final TransformationController _transformationController =
      TransformationController();

  @override
  void initState() {
    super.initState();
    _currentIndex = widget.initialIndex;
  }

  @override
  void dispose() {
    _transformationController.dispose();
    super.dispose();
  }

  void _resetZoom() {
    _transformationController.value = Matrix4.identity();
  }

  void _goTo(int index) {
    if (index < 0 || index >= widget.urls.length) return;
    setState(() {
      _currentIndex = index;
    });
    _resetZoom();
  }

  @override
  Widget build(BuildContext context) {
    final url = widget.urls[_currentIndex];
    final isLocal = !url.startsWith('http');

    return Material(
      color: Colors.transparent,
      child: Stack(
        children: [
          // Image area - tap background to dismiss
          GestureDetector(
            onTap: () => Navigator.of(context).pop(),
            child: Container(color: Colors.transparent),
          ),

          // InteractiveViewer for zoom/pan
          Center(
            child: InteractiveViewer(
              transformationController: _transformationController,
              minScale: 0.5,
              maxScale: 5.0,
              child: (kIsWeb || !isLocal)
                  ? Image.network(
                      url,
                      fit: BoxFit.contain,
                      errorBuilder: (context, error, stackTrace) {
                        return const Center(
                          child: Icon(
                            Icons.broken_image,
                            color: Colors.white54,
                            size: 48,
                          ),
                        );
                      },
                    )
                  : Image.file(File(url), fit: BoxFit.contain),
            ),
          ),

          // Top controls
          Positioned(
            top: MediaQuery.of(context).padding.top + 8,
            right: 12,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                // Zoom controls
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: Colors.white.withOpacity(0.1)),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      GestureDetector(
                        onTap: () {
                          final currentScale = _transformationController.value
                              .getMaxScaleOnAxis();
                          final newScale = (currentScale - 0.3).clamp(0.5, 5.0);
                          _transformationController.value = Matrix4.identity()
                            ..scale(newScale);
                        },
                        child: const Icon(
                          Icons.zoom_out,
                          color: Colors.white,
                          size: 18,
                        ),
                      ),
                      const SizedBox(width: 8),
                      GestureDetector(
                        onTap: _resetZoom,
                        child: const Text(
                          'Reset',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 10,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      GestureDetector(
                        onTap: () {
                          final currentScale = _transformationController.value
                              .getMaxScaleOnAxis();
                          final newScale = (currentScale + 0.3).clamp(0.5, 5.0);
                          _transformationController.value = Matrix4.identity()
                            ..scale(newScale);
                        },
                        child: const Icon(
                          Icons.zoom_in,
                          color: Colors.white,
                          size: 18,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                // Close button
                GestureDetector(
                  onTap: () => Navigator.of(context).pop(),
                  child: Container(
                    width: 36,
                    height: 36,
                    decoration: BoxDecoration(
                      color: Colors.white.withOpacity(0.1),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.close,
                      color: Colors.white,
                      size: 20,
                    ),
                  ),
                ),
              ],
            ),
          ),

          // Left arrow
          if (widget.urls.length > 1 && _currentIndex > 0)
            Positioned(
              left: 12,
              top: 0,
              bottom: 0,
              child: Center(
                child: GestureDetector(
                  onTap: () => _goTo(_currentIndex - 1),
                  child: Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: Colors.white.withOpacity(0.1),
                      shape: BoxShape.circle,
                      border: Border.all(color: Colors.white.withOpacity(0.05)),
                    ),
                    child: const Icon(
                      Icons.chevron_left,
                      color: Colors.white,
                      size: 28,
                    ),
                  ),
                ),
              ),
            ),

          // Right arrow
          if (widget.urls.length > 1 && _currentIndex < widget.urls.length - 1)
            Positioned(
              right: 12,
              top: 0,
              bottom: 0,
              child: Center(
                child: GestureDetector(
                  onTap: () => _goTo(_currentIndex + 1),
                  child: Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: Colors.white.withOpacity(0.1),
                      shape: BoxShape.circle,
                      border: Border.all(color: Colors.white.withOpacity(0.05)),
                    ),
                    child: const Icon(
                      Icons.chevron_right,
                      color: Colors.white,
                      size: 28,
                    ),
                  ),
                ),
              ),
            ),

          // Image counter
          if (widget.urls.length > 1)
            Positioned(
              bottom: MediaQuery.of(context).padding.bottom + 16,
              left: 0,
              right: 0,
              child: Center(
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: Text(
                    '${_currentIndex + 1} / ${widget.urls.length}',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ),
            ),
        ],
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
