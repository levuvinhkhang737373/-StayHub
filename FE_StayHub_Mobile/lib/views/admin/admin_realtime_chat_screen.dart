import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:image_picker/image_picker.dart';
import '../../controllers/auth_controller.dart';
import '../../controllers/chat_controller.dart';
import '../../models/chat.dart';
import '../../services/websocket_service.dart';

const _kMessagesPerPage = 30;
const _kScrollUpThreshold = 80.0;

enum _AdminChatTab { tenants, direct }

class AdminChatScreen extends StatefulWidget {
  final bool isEmbedded;
  const AdminChatScreen({super.key, this.isEmbedded = false});

  @override
  State<AdminChatScreen> createState() => _AdminChatScreenState();
}

class _AdminChatScreenState extends State<AdminChatScreen> {
  final TextEditingController _searchController = TextEditingController();
  final TextEditingController _messageController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  List<File> _selectedImages = [];
  final ImagePicker _picker = ImagePicker();
  bool _isLoadingMore = false;
  bool _hasMore = false;
  _AdminChatTab _activeTab = _AdminChatTab.tenants;

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
    _scrollController.addListener(_onScroll);
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final chatController = context.read<ChatController>();
      final authController = context.read<AuthController>();
      final webSocketService = context.read<WebSocketService>();
      final route = ModalRoute.of(context);
      final currentAdminId = authController.currentAdmin?.id;

      await _loadConversationsForActiveTab();
      if (!mounted) return;

      if (currentAdminId != null) {
        webSocketService.subscribeToAdminChat(
          currentAdminId,
          onMessage: (payload) {
            if (!mounted) return;
            chatController.handleRealtimeMessage(
              payload,
              currentAdminId: currentAdminId,
              currentAdminRole: authController.currentAdmin?.role,
            );
          },
          onRead: (payload) {
            if (!mounted) return;
            chatController.handleRealtimeRead(
              payload,
              currentAdminId: currentAdminId,
              currentAdminRole: authController.currentAdmin?.role,
            );
          },
        );
      }

      final tenantId = route?.settings.arguments as int?;
      ChatConversation? targetConv;
      if (tenantId != null) {
        try {
          targetConv = chatController.tenantConversations.firstWhere(
            (c) => c.tenantId == tenantId,
          );
        } catch (_) {}
      }

      final active = targetConv ?? chatController.activeConversation;
      if (active != null) {
        await chatController.selectAdminConversation(
          active,
          currentAdminId: currentAdminId,
        );
        if (!mounted) return;
        _activeTab = active.isDirect
            ? _AdminChatTab.direct
            : _AdminChatTab.tenants;
        _subscribeConversation(active.id);
        _hasMore = chatController.messages.length >= _kMessagesPerPage;
        _scrollToBottomAfterImages();
      }
    });
  }

  void _onScroll() {
    if (!_scrollController.hasClients) return;
    if (_scrollController.position.pixels <= _kScrollUpThreshold &&
        _hasMore &&
        !_isLoadingMore) {
      _loadMoreMessages();
    }
  }

  Future<void> _loadConversationsForActiveTab({String? keyword}) async {
    final chatController = context.read<ChatController>();
    if (_activeTab == _AdminChatTab.direct) {
      await chatController.fetchAdminDirectConversations(keyword: keyword);
      final currentAdmin = context.read<AuthController>().currentAdmin;
      chatController.filterAdminDirectConversations(
        currentAdminId: currentAdmin?.id,
        currentAdminRole: currentAdmin?.role,
      );
      return;
    }

    await chatController.fetchAdminTenantConversations(keyword: keyword);
  }

  Future<void> _loadMoreMessages() async {
    final chatController = context.read<ChatController>();
    final active = chatController.activeConversation;
    if (active == null || chatController.messages.isEmpty) return;

    setState(() => _isLoadingMore = true);

    final oldScrollHeight = _scrollController.position.maxScrollExtent;
    final oldScrollPos = _scrollController.position.pixels;

    try {
      final oldestId = chatController.messages.first.id;
      if (active.isDirect) {
        await chatController.fetchMoreAdminDirectMessages(
          active.id,
          beforeId: oldestId,
          perPage: _kMessagesPerPage,
        );
      } else {
        await chatController.fetchMoreAdminMessages(
          active.id,
          beforeId: oldestId,
          perPage: _kMessagesPerPage,
        );
      }

      // Check if we got fewer messages than requested (= no more)
      _hasMore = chatController.lastFetchCount >= _kMessagesPerPage;

      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!_scrollController.hasClients) return;
        final newScrollHeight = _scrollController.position.maxScrollExtent;
        _scrollController.jumpTo(
          oldScrollPos + (newScrollHeight - oldScrollHeight),
        );
      });
    } catch (e) {
      debugPrint('Error loading more messages: $e');
    }

    if (mounted) setState(() => _isLoadingMore = false);
  }

  Future<void> _switchTab(_AdminChatTab tab) async {
    if (_activeTab == tab) return;
    final oldActive = context.read<ChatController>().activeConversation;
    if (oldActive != null) {
      context.read<WebSocketService>().unsubscribeFromChatConversation(
        oldActive.id,
      );
    }
    setState(() {
      _activeTab = tab;
      _hasMore = false;
      _selectedImages.clear();
    });
    _messageController.clear();
    final chatController = context.read<ChatController>();
    final currentAdminId = context.read<AuthController>().currentAdmin?.id;
    await _loadConversationsForActiveTab(keyword: _searchController.text);
    if (!mounted) return;

    final active = chatController.activeConversation;
    if (active != null) {
      await chatController.selectAdminConversation(
        active,
        currentAdminId: currentAdminId,
      );
      if (!mounted) return;
      _subscribeConversation(active.id);
      _hasMore = chatController.messages.length >= _kMessagesPerPage;
      _scrollToBottomAfterImages();
    }
  }

  void _subscribeConversation(int conversationId) {
    final currentAdmin = context.read<AuthController>().currentAdmin;
    context.read<WebSocketService>().subscribeToChatConversation(
      conversationId,
      onMessage: (payload) {
        if (!mounted) return;
        context.read<ChatController>().handleRealtimeMessage(
          payload,
          currentAdminId: currentAdmin?.id,
          currentAdminRole: currentAdmin?.role,
        );
        _scrollToBottom();
      },
      onRead: (payload) {
        if (!mounted) return;
        context.read<ChatController>().handleRealtimeRead(
          payload,
          currentAdminId: currentAdmin?.id,
          currentAdminRole: currentAdmin?.role,
        );
      },
    );
  }

  @override
  void dispose() {
    _scrollController.removeListener(_onScroll);
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

  void _scrollToBottom({bool jump = false}) {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scrollController.hasClients) return;
      final target = _scrollController.position.maxScrollExtent;
      if (jump) {
        _scrollController.jumpTo(target);
        return;
      }
      _scrollController.animateTo(
        target,
        duration: const Duration(milliseconds: 220),
        curve: Curves.easeOut,
      );
    });
  }

  void _scrollToBottomAfterImages() {
    _scrollToBottom(jump: true);
    Future.delayed(const Duration(milliseconds: 80), () {
      if (!mounted) return;
      _scrollToBottom(jump: true);
    });
    Future.delayed(const Duration(milliseconds: 260), () {
      if (!mounted) return;
      _scrollToBottom(jump: true);
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
    await context.read<ChatController>().sendAdminMessage(
      conversation.id,
      text,
      images: imagesToSend,
    );
    _scrollToBottom();
  }

  @override
  Widget build(BuildContext context) {
    final chatController = context.watch<ChatController>();
    final active = chatController.activeConversation;
    final currentAdminId = context.watch<AuthController>().currentAdmin?.id;

    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        leading: widget.isEmbedded
            ? Builder(
                builder: (context) => IconButton(
                  icon: const Icon(Icons.menu, color: Colors.white),
                  onPressed: () => Scaffold.of(context).openDrawer(),
                ),
              )
            : const BackButton(color: Colors.white),
        title: Builder(
          builder: (context) => GestureDetector(
            onTap: () => Scaffold.of(context).openDrawer(),
            behavior: HitTestBehavior.opaque,
            child: Row(
              children: [
                // Room number badge
                if (active != null)
                  Container(
                    width: 36,
                    height: 36,
                    decoration: BoxDecoration(
                      color: const Color(0xFFF3C56B),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    alignment: Alignment.center,
                    child: Text(
                      active.listLeadingTextForAdmin(currentAdminId),
                      style: const TextStyle(
                        color: Color(0xFF24170D),
                        fontWeight: FontWeight.w900,
                        fontSize: 11,
                      ),
                    ),
                  ),
                if (active != null) const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        active != null
                            ? (active.isDirect
                                  ? active.displayTitleForAdmin(currentAdminId)
                                  : (active.roomNumber != null
                                        ? 'Phòng ${active.roomNumber} - ${active.tenantName}'
                                        : active.tenantName ?? 'Đoạn chat'))
                            : 'Đoạn chat',
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontWeight: FontWeight.w900,
                          fontSize: 15,
                        ),
                      ),
                      if (active != null)
                        Text(
                          active.isDirect
                              ? active.displaySubtitleForAdmin(currentAdminId)
                              : '${active.buildingName ?? 'Tòa nhà'} · ${active.tenantPhone ?? 'Chưa có SĐT'}',
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 0.5,
                            color: const Color(0xFF8B5E34).withOpacity(0.9),
                          ),
                        ),
                    ],
                  ),
                ),
                const Icon(
                  Icons.arrow_drop_down,
                  color: Colors.white,
                  size: 20,
                ),
              ],
            ),
          ),
        ),
        backgroundColor: const Color(0xFF24170D),
      ),
      drawer: _buildDrawer(context, chatController, active, currentAdminId),
      body: Column(
        children: [
          Expanded(
            child: active == null
                ? const Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          Icons.chat_bubble_outline,
                          size: 64,
                          color: Colors.grey,
                        ),
                        SizedBox(height: 16),
                        Text(
                          'Chọn một đoạn chat để bắt đầu.',
                          style: TextStyle(
                            fontWeight: FontWeight.bold,
                            color: Colors.grey,
                          ),
                        ),
                      ],
                    ),
                  )
                : chatController.isLoading && chatController.messages.isEmpty
                ? const Center(
                    child: CircularProgressIndicator(color: Color(0xFF24170D)),
                  )
                : ListView.builder(
                    controller: _scrollController,
                    padding: const EdgeInsets.all(16),
                    itemCount:
                        chatController.messages.length +
                        (_isLoadingMore ? 1 : 0),
                    itemBuilder: (context, index) {
                      // Loading spinner at the top
                      if (_isLoadingMore && index == 0) {
                        return const Padding(
                          padding: EdgeInsets.symmetric(vertical: 12),
                          child: Center(
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                SizedBox(
                                  width: 16,
                                  height: 16,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                    color: Color(0xFF8B5E34),
                                  ),
                                ),
                                SizedBox(width: 8),
                                Text(
                                  'Đang tải tin nhắn cũ...',
                                  style: TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.bold,
                                    color: Color(0xFF8B5E34),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        );
                      }

                      final msgIndex = _isLoadingMore ? index - 1 : index;
                      final message = chatController.messages[msgIndex];
                      final isMine = active.isDirect
                          ? message.isMineForAdmin(currentAdminId)
                          : message.senderRole == 2;

                      // Date separator logic
                      final prevMessage = msgIndex > 0
                          ? chatController.messages[msgIndex - 1]
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
                              padding: const EdgeInsets.symmetric(vertical: 16),
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
                          _AdminMessageBubble(
                            message: message,
                            isMine: isMine,
                            allMessages: chatController.messages,
                            onImageLoaded: () => _scrollToBottom(jump: true),
                          ),
                        ],
                      );
                    },
                  ),
          ),
          if (chatController.errorMessage != null)
            Container(
              width: double.infinity,
              margin: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
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
                          border: Border.all(
                            color: const Color(0xFF3D2A18).withOpacity(0.15),
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
                              decoration: InputDecoration(
                                hintText:
                                    'Nhập tin nhắn cho ${active.displayTitleForAdmin(currentAdminId)}...',
                                hintStyle: const TextStyle(
                                  color: Colors.grey,
                                  fontSize: 14,
                                  fontWeight: FontWeight.normal,
                                ),
                                border: InputBorder.none,
                                contentPadding: const EdgeInsets.symmetric(
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
    );
  }

  Widget _buildChatTabButton({
    required _AdminChatTab tab,
    required String label,
    required IconData icon,
    required int count,
  }) {
    final selected = _activeTab == tab;
    return Expanded(
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: () => _switchTab(tab),
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 180),
            curve: Curves.easeOut,
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
            decoration: BoxDecoration(
              color: selected ? const Color(0xFFF3C56B) : Colors.transparent,
              borderRadius: BorderRadius.circular(14),
              boxShadow: selected
                  ? [
                      BoxShadow(
                        color: const Color(0xFFF3C56B).withOpacity(0.22),
                        blurRadius: 14,
                        offset: const Offset(0, 5),
                      ),
                    ]
                  : null,
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(
                  icon,
                  size: 16,
                  color: selected ? const Color(0xFF24170D) : Colors.white70,
                ),
                const SizedBox(width: 6),
                Flexible(
                  child: Text(
                    label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: selected ? const Color(0xFF24170D) : Colors.white,
                      fontSize: 12,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                if (count > 0) ...[
                  const SizedBox(width: 6),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 6,
                      vertical: 2,
                    ),
                    decoration: BoxDecoration(
                      color: selected
                          ? const Color(0xFF24170D)
                          : const Color(0xFF006DFF),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Text(
                      '$count',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 9,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildDrawer(
    BuildContext context,
    ChatController chatController,
    ChatConversation? active,
    int? currentAdminId,
  ) {
    final visibleConversations = _activeTab == _AdminChatTab.direct
        ? chatController.directConversations
        : chatController.tenantConversations;
    final totalUnread = visibleConversations.fold<int>(
      0,
      (sum, c) => sum + c.unreadCountForAdmin(currentAdminId),
    );

    return Drawer(
      backgroundColor: const Color(0xFF24170D),
      child: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 20, 16, 8),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      'Đoạn chat',
                      style: const TextStyle(
                        fontSize: 24,
                        fontWeight: FontWeight.w900,
                        color: Colors.white,
                        letterSpacing: -0.5,
                      ),
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 6,
                    ),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF3C56B),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: Text(
                      '$totalUnread',
                      style: const TextStyle(
                        color: Color(0xFF24170D),
                        fontWeight: FontWeight.w900,
                        fontSize: 13,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 10),
              child: Container(
                padding: const EdgeInsets.all(4),
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.08),
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: Colors.white.withOpacity(0.1)),
                ),
                child: Row(
                  children: [
                    _buildChatTabButton(
                      tab: _AdminChatTab.tenants,
                      label: 'Khách thuê',
                      icon: Icons.meeting_room_rounded,
                      count: chatController.tenantConversations.fold<int>(
                        0,
                        (sum, item) => sum + item.adminUnreadCount,
                      ),
                    ),
                    const SizedBox(width: 6),
                    _buildChatTabButton(
                      tab: _AdminChatTab.direct,
                      label: 'Superadmin',
                      icon: Icons.admin_panel_settings_rounded,
                      count: chatController.directConversations.fold<int>(
                        0,
                        (sum, item) =>
                            sum + item.unreadCountForAdmin(currentAdminId),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            // Search bar
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
              child: Container(
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: Colors.white.withOpacity(0.1)),
                ),
                padding: const EdgeInsets.symmetric(horizontal: 12),
                child: Row(
                  children: [
                    const Icon(
                      Icons.search,
                      color: Color(0xFFF3C56B),
                      size: 18,
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: TextField(
                        controller: _searchController,
                        onSubmitted: (value) =>
                            _loadConversationsForActiveTab(keyword: value),
                        style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.bold,
                          fontSize: 14,
                        ),
                        decoration: InputDecoration(
                          hintText: _activeTab == _AdminChatTab.direct
                              ? 'Tìm superadmin...'
                              : 'Tìm phòng hoặc khách thuê...',
                          hintStyle: TextStyle(
                            color: Colors.white.withOpacity(0.45),
                            fontWeight: FontWeight.bold,
                            fontSize: 13,
                          ),
                          border: InputBorder.none,
                          contentPadding: const EdgeInsets.symmetric(
                            vertical: 12,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            // Conversation list
            Expanded(
              child: chatController.isLoading && visibleConversations.isEmpty
                  ? Center(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: List.generate(
                          5,
                          (_) => Container(
                            margin: const EdgeInsets.symmetric(
                              horizontal: 12,
                              vertical: 4,
                            ),
                            height: 72,
                            decoration: BoxDecoration(
                              color: Colors.white.withOpacity(0.1),
                              borderRadius: BorderRadius.circular(20),
                            ),
                          ),
                        ),
                      ),
                    )
                  : visibleConversations.isEmpty
                  ? Center(
                      child: Container(
                        margin: const EdgeInsets.all(16),
                        padding: const EdgeInsets.all(20),
                        decoration: BoxDecoration(
                          color: Colors.white.withOpacity(0.08),
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(
                            color: Colors.white.withOpacity(0.1),
                          ),
                        ),
                        child: Text(
                          _activeTab == _AdminChatTab.direct
                              ? 'Chưa có superadmin nào để chat.'
                              : 'Chưa có đoạn chat nào.',
                          style: TextStyle(
                            color: Colors.white.withOpacity(0.65),
                            fontWeight: FontWeight.bold,
                            fontSize: 13,
                          ),
                        ),
                      ),
                    )
                  : ListView.builder(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 4,
                      ),
                      itemCount: visibleConversations.length,
                      itemBuilder: (context, index) {
                        final item = visibleConversations[index];
                        final selected = active?.id == item.id;
                        return Padding(
                          padding: const EdgeInsets.only(bottom: 6),
                          child: InkWell(
                            onTap: () async {
                              Navigator.pop(context);
                              final oldActive = context
                                  .read<ChatController>()
                                  .activeConversation;
                              if (oldActive != null) {
                                context
                                    .read<WebSocketService>()
                                    .unsubscribeFromChatConversation(
                                      oldActive.id,
                                    );
                              }
                              final chatController = context
                                  .read<ChatController>();
                              await chatController.selectAdminConversation(
                                item,
                                currentAdminId: currentAdminId,
                              );
                              if (!mounted) return;
                              _subscribeConversation(item.id);
                              _hasMore =
                                  chatController.messages.length >=
                                  _kMessagesPerPage;
                              _scrollToBottomAfterImages();
                            },
                            borderRadius: BorderRadius.circular(20),
                            child: Container(
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                color: selected
                                    ? const Color(0xFFFFFAF1)
                                    : Colors.transparent,
                                borderRadius: BorderRadius.circular(20),
                                boxShadow: selected
                                    ? [
                                        BoxShadow(
                                          color: Colors.black.withOpacity(0.2),
                                          blurRadius: 12,
                                          offset: const Offset(0, 4),
                                        ),
                                      ]
                                    : null,
                              ),
                              child: Row(
                                children: [
                                  // Room number badge
                                  Container(
                                    width: 44,
                                    height: 44,
                                    decoration: BoxDecoration(
                                      color: const Color(0xFFF3C56B),
                                      borderRadius: BorderRadius.circular(14),
                                      boxShadow: [
                                        BoxShadow(
                                          color: Colors.black.withOpacity(0.15),
                                          blurRadius: 6,
                                          offset: const Offset(0, 2),
                                        ),
                                      ],
                                    ),
                                    alignment: Alignment.center,
                                    child: Text(
                                      item.listLeadingTextForAdmin(
                                        currentAdminId,
                                      ),
                                      style: const TextStyle(
                                        color: Color(0xFF24170D),
                                        fontWeight: FontWeight.w900,
                                        fontSize: 12,
                                      ),
                                    ),
                                  ),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Row(
                                          children: [
                                            Expanded(
                                              child: Text(
                                                item.listTitleForAdmin(
                                                  currentAdminId,
                                                ),
                                                maxLines: 1,
                                                overflow: TextOverflow.ellipsis,
                                                style: TextStyle(
                                                  color: selected
                                                      ? const Color(0xFF24170D)
                                                      : Colors.white,
                                                  fontWeight: FontWeight.w900,
                                                  fontSize: 13,
                                                ),
                                              ),
                                            ),
                                            if (item.unreadCountForAdmin(
                                                  currentAdminId,
                                                ) >
                                                0)
                                              Container(
                                                margin: const EdgeInsets.only(
                                                  left: 6,
                                                ),
                                                padding:
                                                    const EdgeInsets.symmetric(
                                                      horizontal: 6,
                                                      vertical: 2,
                                                    ),
                                                decoration: BoxDecoration(
                                                  color: const Color(
                                                    0xFF006DFF,
                                                  ),
                                                  borderRadius:
                                                      BorderRadius.circular(10),
                                                ),
                                                child: Text(
                                                  '${item.unreadCountForAdmin(currentAdminId)}',
                                                  style: const TextStyle(
                                                    color: Colors.white,
                                                    fontSize: 10,
                                                    fontWeight: FontWeight.w900,
                                                  ),
                                                ),
                                              ),
                                          ],
                                        ),
                                        const SizedBox(height: 3),
                                        Text(
                                          item.listSubtitleForAdmin(
                                            currentAdminId,
                                          ),
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis,
                                          style: TextStyle(
                                            color: selected
                                                ? const Color(
                                                    0xFF24170D,
                                                  ).withOpacity(0.7)
                                                : Colors.white.withOpacity(0.6),
                                            fontWeight: FontWeight.bold,
                                            fontSize: 11,
                                          ),
                                        ),
                                      ],
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
    );
  }
}

class _AdminMessageBubble extends StatelessWidget {
  final ChatMessage message;
  final bool isMine;
  final List<ChatMessage> allMessages;
  final VoidCallback? onImageLoaded;

  const _AdminMessageBubble({
    required this.message,
    required this.isMine,
    required this.allMessages,
    this.onImageLoaded,
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
                ? const EdgeInsets.symmetric(horizontal: 14, vertical: 10)
                : EdgeInsets.zero,
            decoration: BoxDecoration(
              color: hasBody
                  ? (isMine ? const Color(0xFF24170D) : Colors.white)
                  : Colors.transparent,
              borderRadius: BorderRadius.circular(23),
              border: (hasBody && !isMine)
                  ? Border.all(color: const Color(0xFFE4E2D7))
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
                      fontWeight: FontWeight.w700,
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
                fontSize: 10,
                color: const Color(0xFF8B5E34).withOpacity(0.7),
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
        gaplessPlayback: true,
        loadingBuilder: (context, child, progress) {
          if (progress == null) {
            WidgetsBinding.instance.addPostFrameCallback(
              (_) => onImageLoaded?.call(),
            );
            return child;
          }
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
          // Background dismiss
          GestureDetector(
            onTap: () => Navigator.of(context).pop(),
            child: Container(color: Colors.transparent),
          ),

          // InteractiveViewer
          Center(
            child: InteractiveViewer(
              transformationController: _transformationController,
              minScale: 0.5,
              maxScale: 5.0,
              child: (kIsWeb || !isLocal)
                  ? Image.network(
                      url,
                      fit: BoxFit.contain,
                      gaplessPlayback: true,
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
