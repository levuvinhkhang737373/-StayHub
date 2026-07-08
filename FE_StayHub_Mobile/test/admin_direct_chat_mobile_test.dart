import 'dart:io';

import 'package:fe_stayhub_mobile/models/chat.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  final repoRoot = _findRepoRoot();
  final mobileRoot = Directory('${repoRoot.path}/FE_StayHub_Mobile');

  test('mobile chat conversation parses direct superadmin fields', () {
    final conversation = ChatConversation.fromJson({
      'id': 7,
      'conversation_type': 2,
      'building_id': null,
      'room_id': null,
      'tenant_id': null,
      'manager_admin_id': 12,
      'manager_name': 'Quản lý A',
      'manager_username': 'manager_a',
      'manager_phone': '0900000001',
      'manager_email': 'manager@stayhub.local',
      'manager_building_names': ['Tòa A', 'Tòa B'],
      'super_admin_id': 3,
      'super_admin_name': 'Super Admin',
      'super_admin_username': 'superadmin',
      'admin_unread_count': 2,
      'tenant_unread_count': 0,
      'status': 1,
    });

    expect(conversation.isDirect, isTrue);
    expect(conversation.conversationType, 2);
    expect(conversation.superAdminName, 'Super Admin');
    expect(conversation.superAdminUsername, 'superadmin');
    expect(conversation.managerBuildingNames, ['Tòa A', 'Tòa B']);
  });

  test('direct chat unread count follows current admin side', () {
    final conversation = ChatConversation.fromJson({
      'id': 8,
      'conversation_type': 2,
      'manager_admin_id': 12,
      'super_admin_id': 3,
      'admin_unread_count': 5,
      'tenant_unread_count': 2,
      'status': 1,
    });

    expect(conversation.unreadCountForAdmin(3), 5);
    expect(conversation.unreadCountForAdmin(12), 2);
    expect(conversation.unreadCountForAdmin(null), 0);
  });

  test('direct admin message ownership uses sender admin id', () {
    final message = ChatMessage.fromJson({
      'id': 91,
      'conversation_id': 8,
      'sender_type': 'admin',
      'sender_id': 3,
      'sender_role': 2,
      'body': 'Tin từ superadmin',
    });

    expect(message.isMineForAdmin(3), isTrue);
    expect(message.isMineForAdmin(12), isFalse);
    expect(message.isMineForAdmin(null), isFalse);
  });

  test('mobile admin chat controller uses direct chat endpoints', () {
    final controllerSource = File(
      '${mobileRoot.path}/lib/controllers/chat_controller.dart',
    ).readAsStringSync();

    expect(controllerSource, contains('/admin/chat/direct-conversations'));
    expect(
      controllerSource,
      contains('/admin/chat/direct-conversations/\$conversationId/messages'),
    );
    expect(
      controllerSource,
      contains('/admin/chat/direct-conversations/\$conversationId/read'),
    );
  });

  test('mobile admin chat screen exposes tenant and superadmin tabs', () {
    final screenSource = File(
      '${mobileRoot.path}/lib/views/admin/admin_realtime_chat_screen.dart',
    ).readAsStringSync();

    expect(screenSource, contains("enum _AdminChatTab"));
    expect(screenSource, contains("_AdminChatTab.tenants"));
    expect(screenSource, contains("_AdminChatTab.direct"));
    expect(screenSource, contains("Khách thuê"));
    expect(screenSource, contains("Superadmin"));
  });
}

Directory _findRepoRoot() {
  var current = Directory.current;
  while (!File('${current.path}/docker-compose.yml').existsSync()) {
    final parent = current.parent;
    if (parent.path == current.path) {
      throw StateError(
        'Cannot find repository root from ${Directory.current.path}',
      );
    }
    current = parent;
  }
  return current;
}
