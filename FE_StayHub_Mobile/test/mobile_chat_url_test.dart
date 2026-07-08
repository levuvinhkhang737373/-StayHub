import 'package:fe_stayhub_mobile/models/chat.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test(
    'chat message normalizes localhost attachment urls from realtime payload',
    () {
      final message = ChatMessage.fromJson({
        'id': 1,
        'conversation_id': 2,
        'sender_type': 'admin',
        'sender_id': 3,
        'sender_role': 2,
        'body': '',
        'attachments': [
          'http://localhost:8080/upload/chats/photo.jpg',
          'http://127.0.0.1:8080/upload/chats/photo-2.jpg',
          'http://10.0.2.2:8080/upload/chats/photo-3.jpg',
        ],
      });

      expect(
        message.attachments,
        everyElement(startsWith('https://api.stayhub.id.vn/upload/chats/')),
      );
    },
  );
}
