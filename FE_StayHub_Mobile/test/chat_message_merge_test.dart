import 'package:fe_stayhub_mobile/controllers/chat_controller.dart';
import 'package:fe_stayhub_mobile/models/chat.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test(
    'mergeChatMessages replaces optimistic and keeps server message unique',
    () {
      final optimistic = ChatMessage(
        id: -1,
        conversationId: 10,
        senderType: 'admin',
        senderId: 3,
        senderRole: 2,
        body: 'Xin chào',
        createdAt: '2026-07-09T15:00:00Z',
        optimistic: true,
      );
      final serverMessage = ChatMessage(
        id: 99,
        conversationId: 10,
        senderType: 'admin',
        senderId: 3,
        senderRole: 2,
        body: 'Xin chào',
        createdAt: '2026-07-09T15:00:01Z',
      );

      final afterRealtime = mergeChatMessages(
        [optimistic],
        serverMessage,
        replaceOptimisticId: optimistic.id,
      );
      expect(afterRealtime.map((message) => message.id), [serverMessage.id]);

      final afterApiResponse = mergeChatMessages(
        afterRealtime,
        serverMessage,
        replaceOptimisticId: optimistic.id,
      );

      expect(afterApiResponse.map((message) => message.id), [serverMessage.id]);
    },
  );

  test('mergeChatMessages replaces matching optimistic realtime echo', () {
    final optimistic = ChatMessage(
      id: -2,
      conversationId: 10,
      senderType: 'tenant',
      senderId: 7,
      senderRole: 1,
      body: 'Tôi cần hỗ trợ',
      createdAt: '2026-07-09T15:05:00Z',
      optimistic: true,
    );
    final serverMessage = ChatMessage(
      id: 100,
      conversationId: 10,
      senderType: 'tenant',
      senderId: 7,
      senderRole: 1,
      body: 'Tôi cần hỗ trợ',
      createdAt: '2026-07-09T15:05:01Z',
    );

    final afterRealtime = mergeChatMessages([optimistic], serverMessage);

    expect(afterRealtime.map((message) => message.id), [serverMessage.id]);
  });
}
